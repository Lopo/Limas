<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


/**
 * Per-attachment metadata wrapper around a Blob. The actual file content +
 * hash + storage path lives on Blob; this class owns ONLY the metadata
 * that is specific to a single attachment row: description, originalFilename
 * (what the user sees on download — may differ between two attachments
 * pointing at the same Blob), and the back-reference to its owning entity
 * (set on each concrete subclass).
 *
 * `blob` is nullable to support URL-only attachments: the aggregator
 * persisted a sourceUrl that didn't download (Cloudflare, dead origin) and
 * is waiting for the retry CLI to fetch and attach a Blob later. URL-only
 * attachments still record provenance via BlobSource — those rows have a
 * Blob FK set once download succeeds.
 */
#[ORM\MappedSuperclass]
abstract class UploadedFile
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $type;
	#[ORM\Column(name: 'originalname', type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $originalFilename = null;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $description = null;
	/**
	 * Pending download URL — set only while no Blob is attached, used by
	 * the retry CLI to find URL-only attachments that need a second-chance
	 * fetch. Once the download succeeds the URL moves to a BlobSource row
	 * (which can hold multiple URLs per Blob) and this column is cleared.
	 *
	 * NOT exposed in `default` serialization on its own — getSourceUrls()
	 * folds it into the union so the frontend sees one shape regardless.
	 */
	#[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
	private ?string $sourceUrl = null;
	/**
	 * Adapter that contributed the pending sourceUrl ('farnell', 'digikey',
	 * 'mfr-direct', …). Set together with sourceUrl when saveUrlOnly defers
	 * the download; consumed by the retry pass to seed BlobSource.adapter
	 * with the correct provenance once the download succeeds. NULL for
	 * user-uploaded attachments that never went through an adapter.
	 */
	#[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
	private ?string $sourceAdapter = null;
	#[Groups(['default'])]
	private mixed $replacement = null;
	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
	private \DateTimeInterface $created;
	#[ORM\ManyToOne(targetEntity: Blob::class)]
	#[ORM\JoinColumn(name: 'blob_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
	#[Groups(['default'])]
	private ?Blob $blob = null;


	public function __construct()
	{
		$this->setCreated(new \DateTime);
	}

	public function getBlob(): ?Blob
	{
		return $this->blob;
	}

	public function setBlob(?Blob $blob): self
	{
		$this->blob = $blob;
		return $this;
	}

	/**
	 * True when a Blob is attached (file is on disk and downloadable).
	 * Replaces the old `downloaded` boolean — the Blob FK is the source of
	 * truth, no second flag needed. Serialised as `downloaded` so FE
	 * code that pre-CAS checked `record.get('downloaded')` keeps working.
	 */
	#[Groups(['default'])]
	public function isDownloaded(): bool
	{
		return $this->blob !== null;
	}

	public function getCreated(): \DateTimeInterface
	{
		return $this->created;
	}

	public function setCreated(\DateTimeInterface $created): self
	{
		$this->created = $created;
		return $this;
	}

	public function getReplacement(): mixed
	{
		return $this->replacement;
	}

	public function setReplacement(mixed $replacement): self
	{
		$this->replacement = $replacement;
		return $this;
	}

	public function getOriginalFilename(): ?string
	{
		return $this->originalFilename;
	}

	public function setOriginalFilename(?string $originalFilename): self
	{
		$this->originalFilename = $originalFilename !== null ? self::sanitizeOriginalFilename($originalFilename) : null;
		return $this;
	}

	/**
	 * Rename executable / script extensions to .txt. Storage path is now
	 * sha256-based (CAS), but originalFilename is what the browser sees on
	 * download — if a user re-uploads that file elsewhere (or a sloppy host
	 * Content-Disposition's it inline) the original extension becomes a
	 * stored XSS / RCE vector. Strip the risk at the source.
	 *
	 * SVG stays whitelisted because UploadedFileService sanitises its
	 * contents on write (enshrined/svg-sanitize).
	 */
	private static function sanitizeOriginalFilename(string $name): string
	{
		$blacklist = [
			'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
			'htaccess', 'htpasswd',
			'jsp', 'asp', 'aspx', 'cgi',
			'exe', 'bat', 'cmd', 'sh', 'ps1',
			'html', 'htm', 'shtml',
		];
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if ($ext === '' || !in_array($ext, $blacklist, true)) {
			return $name;
		}
		$base = pathinfo($name, PATHINFO_FILENAME);
		return ($base !== '' ? $base . '.' : '') . $ext . '.txt';
	}

	public function getType(): string
	{
		return $this->type;
	}

	protected function setType(string $type): self
	{
		$this->type = $type;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;
		return $this;
	}

	/**
	 * Pass-throughs to the attached Blob — kept on UploadedFile for backwards
	 * compatibility with existing callers (Twig templates, frontend stores,
	 * controllers). Each returns null when no Blob is attached (URL-only
	 * attachment). New code should prefer reading `getBlob()->getXxx()`
	 * directly.
	 */
	#[Groups(['default'])]
	public function getMimetype(): ?string
	{
		return $this->blob?->getMimetype();
	}

	#[Groups(['default'])]
	public function getSize(): ?int
	{
		return $this->blob?->getSize();
	}

	#[Groups(['default'])]
	public function getSha256(): ?string
	{
		return $this->blob?->getSha256();
	}

	#[Groups(['default'])]
	public function getFilename(): ?string
	{
		return $this->blob?->getFilename();
	}

	/**
	 * Union of all source URLs known for this attachment:
	 *  - Blob's BlobSource rows (full provenance after a successful
	 *    download — e.g. same PDF seen at Farnell AND DigiKey)
	 *  - the per-row `sourceUrl` (URL-only pending download — Blob is
	 *    null until the retry CLI catches up)
	 *
	 * Frontend sees one shape regardless of which state we're in.
	 *
	 * @return string[]
	 */
	#[Groups(['default'])]
	public function getSourceUrls(): array
	{
		$urls = [];
		if ($this->sourceUrl !== null && $this->sourceUrl !== '') {
			$urls[] = $this->sourceUrl;
		}
		if ($this->blob !== null) {
			foreach ($this->blob->getSources() as $s) {
				$urls[] = $s->getSourceUrl();
			}
		}
		return array_values(array_unique($urls));
	}

	public function getSourceUrl(): ?string
	{
		return $this->sourceUrl;
	}

	public function setSourceUrl(?string $sourceUrl): self
	{
		$this->sourceUrl = $sourceUrl;
		return $this;
	}

	public function getSourceAdapter(): ?string
	{
		return $this->sourceAdapter;
	}

	public function setSourceAdapter(?string $sourceAdapter): self
	{
		$this->sourceAdapter = $sourceAdapter;
		return $this;
	}
}
