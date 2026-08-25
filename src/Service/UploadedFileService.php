<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use enshrined\svgSanitize\Sanitizer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use League\Flysystem\FilesystemOperator;
use Limas\Entity\Blob;
use Limas\Entity\BlobSource;
use Limas\Entity\UploadedFile;
use Limas\Exceptions\DiskSpaceExhaustedException;
use Nette\Utils\FileSystem;
use Nette\Utils\Validators;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Validator\Constraints\Hostname;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;


/**
 * Owns the write/read/delete pipeline for attached files. Post-CAS refactor
 * the per-attachment row no longer carries file content; it points to a
 * Blob via FK. This service is the single chokepoint that:
 *
 *   - hashes the incoming bytes
 *   - finds/creates the Blob (write-through dedup)
 *   - writes the file to the `blob` Gaufrette pool at <sha-prefix>/<sha256>
 *     (skip if already there — content-addressable storage by definition)
 *   - links the UploadedFile row to the Blob
 *   - optionally seeds a BlobSource for provenance
 *   - on delete: drops the attachment row, then counts remaining references
 *     and removes the Blob (+ file) when its refcount hits zero.
 *
 * URL-only attachments (saveUrlOnly): blob_id stays null; sourceUrl is held
 * via a stand-alone BlobSource so provenance is preserved even before the
 * retry CLI completes the download.
 */
class UploadedFileService
{
	public function __construct(
		private readonly SystemService            $systemService,
		protected readonly LoggerInterface        $logger,
		private readonly FilesystemOperator       $blobStorage,
		protected readonly EntityManagerInterface $entityManager,
		protected readonly array                  $limas,
	)
	{
	}

	public function replace(UploadedFile $file, File $filesystemFile): void
	{
		$this->replaceFromFilesystem($file, $filesystemFile);
	}

	/**
	 * @throws DiskSpaceExhaustedException
	 */
	public function replaceFromFilesystem(UploadedFile $file, File $filesystemFile): void
	{
		if ($filesystemFile->getSize() > $this->systemService->getFreeDiskSpace()) {
			throw new DiskSpaceExhaustedException;
		}
		$bytes = FileSystem::read($filesystemFile->getPathname());
		if ($this->looksLikeSvg($filesystemFile->getBasename(), $bytes)) {
			$bytes = $this->sanitizeSvg($bytes);
		}
		$mt = $filesystemFile->getMimeType();
		$blob = $this->getOrCreateBlob($bytes, ($mt !== null && $mt !== '') ? $mt : 'application/octet-stream');
		$file->setOriginalFilename($filesystemFile->getBasename());
		$file->setBlob($blob);
	}

	public function replaceFromData(UploadedFile $file, $data, $filename): void
	{
		$bytes = is_string($data) ? $data : (string)$data;
		if ($this->looksLikeSvg($filename, $bytes)) {
			$bytes = $this->sanitizeSvg($bytes);
		}
		$mimetype = $this->detectMimetype($bytes, $filename);
		$blob = $this->getOrCreateBlob($bytes, $mimetype);
		$file->setOriginalFilename($filename);
		$file->setBlob($blob);
	}

	public function delete(UploadedFile $file): void
	{
		// Blob refcount + on-disk file prune is owned by the FileRemoval
		// postFlush listener so it runs for ANY delete path (explicit
		// service call here, controller em->remove, cascade orphan-removal
		// from Part.attachments collections, …). Service::delete is just
		// the convenience entry point — em ops only.
		$this->entityManager->remove($file);
		$this->entityManager->flush();
	}

	public function replaceFromUploadedFile(UploadedFile $target, UploadedFile $source): void
	{
		// CAS makes attachment-to-attachment copy cheap: both rows just
		// point at the same Blob. No bytes shuffled, no second file on
		// disk.
		$target->setOriginalFilename($source->getOriginalFilename());
		$target->setBlob($source->getBlob());
		// Carry the URL-only pending-download provenance too. When the source is
		// a link-only temp (Blob null, sourceUrl set because the proxy download
		// was blocked), the permanent copy must keep sourceUrl/sourceAdapter —
		// otherwise the attachment lands with both Blob and sourceUrl null and
		// the retry cron (which queries `blob IS NULL AND sourceUrl IS NOT NULL`)
		// can never pick it up, so the deferred download is lost forever.
		$target->setSourceUrl($source->getSourceUrl());
		$target->setSourceAdapter($source->getSourceAdapter());
	}

	public function replaceFromURL(UploadedFile $file, string $url, ?HeaderBag $headers = null, ?string $adapter = null): void
	{
		// A data: URI carries the payload inline, so there is no network fetch
		// and nothing for the URL/SSRF guards to check (no host to resolve).
		// Decode it in-process and funnel into the same replaceFromData() sink
		// every other upload path uses — SVG sanitisation, MIME detection, CAS
		// and the executable-extension blacklist (Entity\UploadedFile::
		// setOriginalFilename) all still apply. Capability-equivalent to a
		// plain userfile upload; it grants the caller nothing new.
		if (preg_match('#^\s*data:#i', $url) === 1) {
			$this->replaceFromDataUri($file, $url);
			return;
		}

		Validators::assert($url, 'url');
		// `requireTld: true` becomes the default in symfony/validator 8.0;
		// pass it explicitly so the 7.x deprecation warning stays quiet.
		// Distributor datasheet / image URLs all come from public CDNs so a
		// TLD requirement is the right semantic anyway.
		Validation::createCallable(new Url(requireTld: true))($url);
		Validation::createCallable(new NotBlank, new Hostname)($host = rawurldecode(parse_url($url)['host'] ?? ''));
		$vettedIps = $this->assertHostIsPublic($host);

		[$data, $contentType, $contentDisposition] = $this->downloadUrlBody($url, $host, $vettedIps, $headers);
		$this->replaceFromData($file, $data, $this->resolveDownloadFilename($url, $contentType, $contentDisposition));
		$blob = $file->getBlob();
		if ($blob !== null) {
			$this->ensureBlobSource($blob, $url, $adapter);
			// Clear the pending URL + adapter columns — provenance is now
			// on the Blob's BlobSource set. Without this, a retried
			// download would leave both stuck on the row even though
			// they're already attached.
			$file->setSourceUrl(null);
			$file->setSourceAdapter(null);
		}
	}

	/**
	 * Download the body of an already-vetted URL and return
	 * [bytes, contentType, contentDisposition]. Split out of replaceFromURL so
	 * the fetch is unit-testable without the CAS/storage half (and without the
	 * SSRF host check, which stays in the caller). $vettedIps are the public IPs
	 * assertHostIsPublic() returned; they pin the connection against DNS rebind.
	 *
	 * @param string[] $vettedIps
	 * @return array{0: string, 1: string, 2: string}
	 */
	protected function downloadUrlBody(string $url, string $host, array $vettedIps, ?HeaderBag $headers = null): array
	{
		$origin = isset(parse_url($url)['scheme'], parse_url($url)['host'])
			? parse_url($url)['scheme'] . '://' . parse_url($url)['host'] . '/'
			: null;

		$options = [
			RequestOptions::TIMEOUT => 30,
			// Stream the body so we can enforce a byte ceiling as it arrives
			// (see the capped read below) instead of buffering an unbounded /
			// decompression-bomb response into memory first
			RequestOptions::STREAM => true,
			RequestOptions::ALLOW_REDIRECTS => [
				'max' => 5,
				'referer' => true,
				'protocols' => ['http', 'https'],
				// Re-run the SSRF guard on EVERY redirect hop. Validating only
				// the initial host is useless if a public host can 302 us to
				// an internal target (cloud metadata, 127.0.0.1, RFC1918).
				// Throwing here aborts the transfer before the internal
				// response is ever read.
				'on_redirect' => function (
					\Psr\Http\Message\RequestInterface  $request,
					\Psr\Http\Message\ResponseInterface $response,
					\Psr\Http\Message\UriInterface      $uri
				): void {
					$this->assertHostIsPublic(rawurldecode($uri->getHost()));
				}
			],
			RequestOptions::HEADERS => array_filter([
				'User-Agent' => $headers?->get('User-Agent') ?? 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
				'Accept' => 'application/pdf,image/avif,image/webp,image/png,image/*;q=0.8,*/*;q=0.5',
				'Accept-Encoding' => 'gzip, deflate, br',
				'Accept-Charset' => $headers?->get('Accept-Charset') ?? 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
				'Accept-Language' => $headers?->get('Accept-Language') ?? 'en-us,en;q=0.5',
				'Referer' => $headers?->get('Referer') ?? $origin,
				'sec-ch-ua' => '"Chromium";v="121", "Not A(Brand";v="99"',
				'sec-ch-ua-mobile' => '?0',
				'sec-ch-ua-platform' => '"Linux"',
				'Sec-Fetch-Dest' => 'document',
				'Sec-Fetch-Mode' => 'navigate',
				'Sec-Fetch-Site' => 'none',
				'Sec-Fetch-User' => '?1',
				'Upgrade-Insecure-Requests' => '1',
				'Cache-Control' => 'max-age=0',
				'Connection' => 'keep-alive',
				'Keep-Alive' => '300',
				'Pragma' => ''
			], static fn($v) => $v !== null)
		];

		// Pin the connection to an already-vetted IP so curl cannot re-resolve
		// the host to an internal address between the SSRF check and the
		// connect (DNS rebinding). Only for hostnames — a literal-IP host is
		// already the concrete target.
		if ($vettedIps !== [] && filter_var($host, FILTER_VALIDATE_IP) === false) {
			$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
			$port = parse_url($url, PHP_URL_PORT) ?? ($scheme === 'http' ? 80 : 443);
			// Prefer IPv4 in the DNS-rebind pin. On a host with no working IPv6
			// route, pinning a dual-stack CDN's AAAA next to its A makes cURL
			// fail the connect outright ("cURL error 7 … after 0 ms") instead of
			// falling back to IPv4 — so images from dual-stack CDNs (DigiKey,
			// LCSC) silently became URL-only attachments. Pin the vetted IPv4
			// addresses when any exist; fall back to IPv6 only for IPv6-only
			// hosts. SSRF safety is unchanged — assertHostIsPublic() still vets
			// every A and AAAA; we only narrow which of them we connect to.
			$options['curl'][CURLOPT_RESOLVE] = array_map(
				static fn(string $ip): string => sprintf('%s:%d:%s', $host, $port, $ip),
				$this->preferIpv4Pin($vettedIps)
			);
		}

		// Force the cURL handler. With stream:true and allow_url_fopen on, Guzzle
		// otherwise routes to the PHP StreamHandler, which reads the body via the
		// http:// wrapper: on a keep-alive connection its read loop blocks for the
		// entire timeout and then dies "Unable to read from stream", and it
		// silently IGNORES the CURLOPT_RESOLVE DNS-rebind pin set above. cURL
		// streams the body cleanly, honours the pin, and respects the timeout.
		$client = new Client(['handler' => HandlerStack::create(new CurlHandler())]);

		try {
			$response = $client->request('GET', $url, $options);
		} catch (GuzzleException $e) {
			if ($this->shouldRetryWithTls13($e)) {
				try {
					$retryOptions = $options;
					// Preserve the CURLOPT_RESOLVE pin (and any other curl opts)
					// while adding the TLS 1.3 fallback.
					$retryOptions['curl'] = ($options['curl'] ?? []) + [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3];
					$response = $client->request('GET', $url, $retryOptions);
				} catch (GuzzleException $e2) {
					// Log the upstream detail (URL, DNS/connection error) server
					// side; return a generic message so the client can't use the
					// error as an SSRF probe / internal-path oracle. Keep the
					// code so isRecoverableDownloadError() still classifies it.
					$this->logger->warning('replaceFromURL failed for {url}: {err}', ['url' => $url, 'err' => $e2->getMessage()]);
					throw new \RuntimeException('Could not download the file from the given URL.', $e2->getCode(), $e2);
				}
			} else {
				$this->logger->warning('replaceFromURL failed for {url}: {err}', ['url' => $url, 'err' => $e->getMessage()]);
				throw new \RuntimeException('Could not download the file from the given URL.', $e->getCode(), $e);
			}
		}

		// Read the (already gzip/br-decoded) body in chunks with a hard cap so
		// a hostile origin can't exhaust worker memory with a huge or
		// decompression-bomb response. Reuse the configured upload limit;
		// fall back to 64 MiB when uploads are unlimited.
		$maxBytes = (isset($this->limas['upload']['limit']) && $this->limas['upload']['limit'] !== false)
			? (int)$this->limas['upload']['limit']
			: 64 * 1024 * 1024;
		$stream = $response->getBody();
		$data = '';
		while (!$stream->eof()) {
			$data .= $stream->read(65536);
			if (strlen($data) > $maxBytes) {
				throw new \RuntimeException('The downloaded file exceeds the maximum allowed size.');
			}
		}

		return [$data, $response->getHeaderLine('Content-Type'), $response->getHeaderLine('Content-Disposition')];
	}

	/**
	 * Which vetted IPs to feed the CURLOPT_RESOLVE DNS-rebind pin. Returns the
	 * IPv4 addresses when any exist, else the input unchanged (IPv6-only host).
	 * Pinning a dual-stack host's IPv6 next to its IPv4 breaks the connect on a
	 * box without a working IPv6 route (cURL error 7), so narrow the pin to the
	 * family that reliably connects. Vetting still covers every A and AAAA — this
	 * only chooses which vetted addresses we actually dial.
	 *
	 * @param list<string> $vettedIps
	 * @return list<string>
	 */
	private function preferIpv4Pin(array $vettedIps): array
	{
		$ipv4 = array_values(array_filter(
			$vettedIps,
			static fn(string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
		));
		return $ipv4 !== [] ? $ipv4 : $vettedIps;
	}

	/**
	 * Decode an RFC 2397 data: URI (`data:[<mediatype>][;base64],<data>`) into
	 * bytes and store them like any other upload. Parsed by hand + base64
	 * (never handed to a stream wrapper) so there is no data://, php:// or
	 * file:// exposure. No network, so no SSRF surface. All downstream content
	 * safety — SVG sanitisation, MIME sniffing, CAS dedup, executable-extension
	 * stripping — runs via replaceFromData()/setOriginalFilename() exactly as
	 * for a userfile upload.
	 */
	private function replaceFromDataUri(UploadedFile $file, string $url): void
	{
		if (preg_match('#^\s*data:([^,]*),(.*)$#is', $url, $m) !== 1) {
			throw new \RuntimeException('Malformed data: URI');
		}

		$parameters = $m[1] === '' ? [] : explode(';', $m[1]);
		$mimetype = (isset($parameters[0]) && str_contains($parameters[0], '/'))
			? strtolower(trim($parameters[0]))
			: 'application/octet-stream';
		$isBase64 = in_array('base64', array_map('strtolower', array_map('trim', $parameters)), true);

		if ($isBase64) {
			$bytes = base64_decode($m[2], true);
			if ($bytes === false) {
				throw new \RuntimeException('Invalid base64 payload in data: URI');
			}
		} else {
			$bytes = rawurldecode($m[2]);
		}

		$extension = $this->extensionFromMime($mimetype);
		$this->replaceFromData($file, $bytes, 'upload' . ($extension !== null ? '.' . $extension : ''));
	}

	/**
	 * Stash a source URL on the attachment without writing any file blob.
	 * Used when the upstream download failed but we still want to record
	 * the URL so the retry CLI can pick it up later. blob_id stays null;
	 * the URL lives in the per-row sourceUrl column. Once the retry
	 * succeeds the URL moves into a BlobSource (full provenance) and
	 * sourceUrl is cleared.
	 *
	 * `$adapter` is kept on the signature for future use — once we wire
	 * adapter-tagged provenance for pending URLs (separate column on
	 * UploadedFile or a stand-alone PendingSourceUrl table) we can stash
	 * it here. For now we accept and ignore it.
	 */
	/**
	 * Walks every PartAttachment + ProjectAttachment row that's URL-only
	 * (`blob` FK null + `sourceUrl` set) and retries the proxy download via
	 * `replaceFromURL`. Manufacturer datasheets routinely 502 / Cloudflare-
	 * block, so a later second attempt often succeeds without user help.
	 *
	 * Pure backend method — no IO formatting. Both the CLI command
	 * (`limas:attachments:retry-downloads`) and the scheduled message
	 * handler (`RetryAttachmentDownloadsMessageHandler`) delegate here.
	 *
	 * @param int $limit Stop after N successful retries (0 = unlimited)
	 * @return array{ok: int, fail: int, pending: int}
	 *         pending = number of candidates considered (matches `--dry-run` count from the CLI); ok+fail ≤ pending
	 */
	public function retryPendingDownloads(int $limit = 0): array
	{
		$pending = [];
		foreach ([\Limas\Entity\PartAttachment::class, \Limas\Entity\ProjectAttachment::class] as $class) {
			$rows = $this->entityManager->getRepository($class)->createQueryBuilder('a')
				->where('a.blob IS NULL')
				->andWhere('a.sourceUrl IS NOT NULL')
				->getQuery()
				->getResult();
			foreach ($rows as $row) {
				$pending[] = $row;
			}
		}
		$ok = 0;
		$fail = 0;
		foreach ($pending as $row) {
			if ($limit > 0 && $ok >= $limit) {
				break;
			}
			$url = $row->getSourceUrl();
			if ($url === null || $url === '') {
				continue;
			}
			try {
				$this->replaceFromURL($row, $url, null, $row->getSourceAdapter());
				$this->entityManager->flush();
				$ok++;
			} catch (\Throwable $e) {
				// Keep the row URL-only; next tick will try again. Some
				// origins recover after a day; some never will and a human
				// eventually has to clean these up.
				$this->logger->info('retryPendingDownloads: skip {url}: {err}', [
					'url' => $url,
					'err' => substr($e->getMessage(), 0, 200),
				]);
				$fail++;
			}
		}
		return ['ok' => $ok, 'fail' => $fail, 'pending' => count($pending)];
	}

	/**
	 * Prune temp upload rows older than $ttlDays. Temp files are transient: a
	 * saved part gets its own permanent copy (they carry no FK back to the
	 * part), so once a temp is past the retention window it is either abandoned
	 * or already copied and safe to drop. Deletion goes through em->remove so
	 * the FileRemoval listener refcounts + prunes the shared Blob — CAS keeps
	 * the permanent copy's bytes, only genuinely-orphaned blobs are removed.
	 *
	 * NOTE: this also drops link-only temps (Blob null, sourceUrl set) once
	 * they age out — those are the source the one-off orphan-relink recovery
	 * reads, so run that recovery BEFORE the retention window elapses if you
	 * have pre-`replaceFromUploadedFile`-fix attachments to rescue.
	 *
	 * @return array{deleted: int, considered: int}
	 */
	public function purgeExpiredTempUploads(int $ttlDays, bool $dryRun = false): array
	{
		$cutoff = new \DateTimeImmutable(sprintf('-%d days', max(0, $ttlDays)));
		$deleted = 0;
		$considered = 0;
		foreach ([\Limas\Entity\TempUploadedFile::class, \Limas\Entity\TempImage::class] as $class) {
			$rows = $this->entityManager->getRepository($class)->createQueryBuilder('t')
				->where('t.created < :cutoff')
				->setParameter('cutoff', $cutoff)
				->getQuery()
				->getResult();
			foreach ($rows as $row) {
				$considered++;
				if (!$dryRun) {
					$this->entityManager->remove($row);
					$deleted++;
				}
			}
		}
		if (!$dryRun) {
			$this->entityManager->flush();
		}
		return ['deleted' => $deleted, 'considered' => $considered];
	}

	/**
	 * Stash a source URL on the attachment without writing any file blob.
	 * Used when the upstream download failed but we still want to record
	 * the URL so the retry pass can pick it up later. blob_id stays null;
	 * the URL lives in the per-row sourceUrl column, the adapter that
	 * contributed it goes into sourceAdapter. Once the retry succeeds both
	 * columns are cleared and the URL+adapter move into a BlobSource for
	 * full provenance.
	 */
	public function saveUrlOnly(UploadedFile $file, string $url, ?string $adapter = null): void
	{
		Validators::assert($url, 'url');
		// `requireTld: true` becomes the default in symfony/validator 8.0;
		// pass it explicitly so the 7.x deprecation warning stays quiet.
		// Distributor datasheet / image URLs all come from public CDNs so a
		// TLD requirement is the right semantic anyway.
		Validation::createCallable(new Url(requireTld: true))($url);
		if ($file->getOriginalFilename() === null || $file->getOriginalFilename() === '') {
			$path = parse_url($url, PHP_URL_PATH);
			$basename = is_string($path) && $path !== '' ? basename($path) : 'download';
			$file->setOriginalFilename($basename !== '' ? $basename : 'download');
		}
		$file->setBlob(null);
		$file->setSourceUrl($url);
		$file->setSourceAdapter($adapter);
	}

	/**
	 * Write-through CAS: hash bytes, look up Blob by (sha256, size). On
	 * hit: reuse — bytes never touch the disk again. On miss: write to
	 * <sha-prefix>/<sha256> in the blob pool and INSERT the Blob row.
	 */
	public function getOrCreateBlob(string $bytes, string $mimetype): Blob
	{
		$sha = hash('sha256', $bytes);
		$size = strlen($bytes);

		$existing = $this->entityManager->getRepository(Blob::class)
			->findOneBy(['sha256' => $sha, 'size' => $size]);
		if ($existing !== null) {
			return $existing;
		}

		$blob = (new Blob)
			->setSha256($sha)
			->setSize($size)
			->setFilename($this->blobPath($sha))
			->setMimetype($mimetype);

		// Belt-and-suspenders: if the file already exists on disk (orphan
		// from a previous run, or hand-restored), don't truncate it —
		// content is by definition identical.
		if (!$this->blobStorage->fileExists($blob->getFilename())) {
			$this->blobStorage->write($blob->getFilename(), $bytes);
		}

		$this->entityManager->persist($blob);
		// Flush immediately so the INSERT happens NOW. Two reasons:
		//  - A sibling getOrCreateBlob() call later in the same request
		//    needs to find this Blob via findOneBy and reuse the row
		//    instead of trying to insert a duplicate.
		//  - The cascade walker on the caller's outer flush would
		//    otherwise re-discover this Blob as "new" and attempt a
		//    second INSERT → unique-key conflict.
		$this->entityManager->flush();
		return $blob;
	}

	/**
	 * Ensure (blob, sourceUrl) pair exists in BlobSource. Idempotent —
	 * duplicate add is a no-op. Called by adapter code that wants to
	 * record "this Blob was also seen at URL X".
	 */
	public function ensureBlobSource(Blob $blob, string $sourceUrl, ?string $adapter = null): void
	{
		foreach ($blob->getSources() as $existing) {
			if ($existing->getSourceUrl() === $sourceUrl) {
				return;
			}
		}
		$src = (new BlobSource)
			->setBlob($blob)
			->setSourceUrl($sourceUrl)
			->setAdapter($adapter);
		$blob->addSource($src);
		$this->entityManager->persist($src);
	}

	/**
	 * SSRF guard. Reject URLs whose host resolves to any non-public IP —
	 * loopback, private RFC1918, link-local, CGNAT, multicast, IPv6 ULA,
	 * IPv4-mapped IPv6, etc. Literal-IP host is checked directly; a
	 * hostname is DNS-resolved (A + AAAA) and every returned address must
	 * be public.
	 *
	 * Note the TOCTOU caveat: an attacker controlling DNS could return a
	 * public IP at check time and a private IP at fetch time. Fully
	 * eliminating that would need a curl_opensocket_function hook that
	 * gates the resolved IP per-connection.
	 */
	/**
	 * Validate that every address $host resolves to is public, and return the
	 * vetted addresses so the caller can pin the connection to one of them
	 * (closing the DNS-rebinding TOCTOU — otherwise curl re-resolves at
	 * connect time and could land on an internal IP)
	 *
	 * @return list<string> the vetted public IP(s) (the literal itself for an IP host)
	 */
	private function assertHostIsPublic(string $host): array
	{
		if ($host === '') {
			throw new \InvalidArgumentException('Cannot upload files: empty host');
		}

		$candidates = [];
		if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
			$candidates[] = $host;
		} else {
			$lower = strtolower($host);
			if ($lower === 'localhost' || $lower === 'localhost.localdomain') {
				throw new \InvalidArgumentException('Cannot upload files from a private/loopback host');
			}
			$ipv4 = gethostbynamel($host);
			if (is_array($ipv4)) {
				$candidates = array_merge($candidates, $ipv4);
			}
			$aaaa = @dns_get_record($host, DNS_AAAA);
			if (is_array($aaaa)) {
				foreach ($aaaa as $record) {
					if (isset($record['ipv6']) && is_string($record['ipv6'])) {
						$candidates[] = $record['ipv6'];
					}
				}
			}
			if ($candidates === []) {
				throw new \InvalidArgumentException(sprintf('Cannot upload files: host %s did not resolve', $host));
			}
		}

		foreach ($candidates as $ip) {
			if ($this->isPrivateIp($ip)) {
				throw new \InvalidArgumentException('Cannot upload files from a private/loopback host');
			}
		}

		return $candidates;
	}

	private function isPrivateIp(string $ip): bool
	{
		// Unwrap IPv4-mapped IPv6 in BOTH notations — dotted (::ffff:127.0.0.1)
		// and hextet (::ffff:7f00:1) — before the IPv4 range checks. The old
		// dotted-decimal-only regex let the hextet form (a valid IPv6 that
		// routes to the embedded IPv4 on Linux) slip past as "public".
		$packed = @inet_pton($ip);
		if ($packed !== false && strlen($packed) === 16 && str_starts_with($packed, "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff")) {
			$unwrapped = @inet_ntop(substr($packed, 12));
			if ($unwrapped !== false) {
				$ip = $unwrapped;
			}
		}
		if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
			return true;
		}
		$privateRanges = [
			'0.0.0.0/8',
			'10.0.0.0/8',
			'100.64.0.0/10',
			'127.0.0.0/8',
			'169.254.0.0/16',
			'172.16.0.0/12',
			'192.0.0.0/24',
			'192.0.2.0/24',
			'192.168.0.0/16',
			'198.18.0.0/15',
			'198.51.100.0/24',
			'203.0.113.0/24',
			'224.0.0.0/4',
			'240.0.0.0/4',
			'::/128',
			'::1/128',
			'fc00::/7',
			'fe80::/10',
			'ff00::/8'
		];
		return IpUtils::checkIp($ip, $privateRanges);
	}

	private function looksLikeSvg(?string $filename, string $bytes): bool
	{
		if ($filename !== null && strcasecmp(pathinfo($filename, PATHINFO_EXTENSION), 'svg') === 0) {
			return true;
		}
		$head = ltrim(substr($bytes, 0, 512));
		return stripos($head, '<svg') !== false;
	}

	private function sanitizeSvg(string $bytes): string
	{
		$sanitizer = new Sanitizer();
		$sanitizer->removeRemoteReferences(true);
		$clean = $sanitizer->sanitize($bytes);
		if (!is_string($clean) || $clean === '') {
			throw new \RuntimeException('SVG sanitisation failed — refusing to store untrusted bytes');
		}
		return $clean;
	}

	private function shouldRetryWithTls13(GuzzleException $e): bool
	{
		if (!method_exists($e, 'getResponse')) {
			return false;
		}
		$response = $e->getResponse();
		return $response !== null && $response->getStatusCode() === 403;
	}

	public function isRecoverableDownloadError(\Throwable $e): bool
	{
		$code = $e->getCode();
		if ($code === 0) {
			return true;
		}
		return in_array($code, [403, 408, 425, 429, 500, 502, 503, 504], true);
	}

	private function resolveDownloadFilename(string $url, string $contentType, string $contentDisposition): string
	{
		if ($contentDisposition !== '' && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $contentDisposition, $m)) {
			$cd = rawurldecode(trim($m[1]));
			if ($cd !== '') {
				return basename($cd);
			}
		}
		$path = parse_url($url, PHP_URL_PATH);
		$basename = basename(is_string($path) && $path !== '' ? $path : $url);
		if ($basename === '' || $basename === '/') {
			$basename = 'download';
		}
		if (pathinfo($basename, PATHINFO_EXTENSION) === '') {
			$ext = $this->extensionFromMime(strtolower(trim(explode(';', $contentType, 2)[0])));
			if ($ext !== null) {
				$basename .= '.' . $ext;
			}
		}
		return $basename;
	}

	private function extensionFromMime(string $mime): ?string
	{
		return match ($mime) {
			'application/pdf' => 'pdf',
			'image/jpeg', 'image/jpg' => 'jpg',
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			'image/svg+xml' => 'svg',
			'application/zip' => 'zip',
			'application/octet-stream' => null,
			default => null,
		};
	}

	private function detectMimetype(string $bytes, ?string $filename): string
	{
		// finfo is the most reliable cheap detector. Fall back to a guess
		// based on extension if finfo refuses or the binary content sniffs
		// as octet-stream (common for distributor PDFs with stripped magic).
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->buffer($bytes);
		if (is_string($mime) && $mime !== '' && $mime !== 'application/octet-stream') {
			return $mime;
		}
		if ($filename !== null) {
			$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
			$byExt = match ($ext) {
				'pdf' => 'application/pdf',
				'jpg', 'jpeg' => 'image/jpeg',
				'png' => 'image/png',
				'gif' => 'image/gif',
				'webp' => 'image/webp',
				'svg' => 'image/svg+xml',
				'zip' => 'application/zip',
				default => null,
			};
			if ($byExt !== null) {
				return $byExt;
			}
		}
		return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
	}

	private function blobPath(string $sha): string
	{
		return substr($sha, 0, 2) . '/' . $sha;
	}

	public function getBlobStorage(): FilesystemOperator
	{
		return $this->blobStorage;
	}

	/**
	 * Legacy pass-through: callers that pre-CAS asked for `getStorage($file)`
	 * to read by `$file->getFilename()` now read the CAS pool instead.
	 * `$file->getFilename()` resolves to the Blob's sha-prefixed path.
	 */
	public function getStorage(UploadedFile $file): FilesystemOperator
	{
		return $this->blobStorage;
	}
}
