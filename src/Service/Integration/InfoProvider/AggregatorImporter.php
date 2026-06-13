<?php

namespace Limas\Service\Integration\InfoProvider;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Distributor;
use Limas\Entity\Manufacturer;
use Limas\Entity\Part;
use Limas\Entity\PartAttachment;
use Limas\Entity\PartCategory;
use Limas\Entity\PartDistributor;
use Limas\Entity\PartManufacturer;
use Limas\Entity\PartParameter;
use Limas\Entity\StorageLocation;
use Limas\Service\Integration\InfoProvider\Dto\AggregatedPartCandidate;
use Limas\Service\Integration\InfoProvider\Dto\FieldWithProvenance;
use Limas\Service\ManufacturerCanonicalizer;
use Limas\Service\UploadedFileService;
use Psr\Log\LoggerInterface;


/**
 * Converts an AggregatedPartCandidate into a fully-linked Part entity:
 *
 *   Part (name=MPN, description, category, storageLocation)
 *     ├── PartManufacturer  → canonical Manufacturer (via ManufacturerCanonicalizer; created if new)
 *     ├── PartDistributor[] → one per contributing source (Distributor created if new)
 *     │     populated with sku, packagingUnit, first price break, currency
 *     └── PartParameter[]   → deduped by raw name across sources, first non-empty value wins
 *
 * Side-effects:
 *  - New Manufacturers auto-register themselves as an alias (so future runs
 *    canonicalise the same name immediately).
 *  - When provider sources reported DIFFERENT manufacturer name strings for
 *    the same canonical manufacturer, those alternate spellings are also
 *    registered as aliases — that's how the alias table grows without manual
 *    seeding.
 *
 * Caller chooses PartCategory + StorageLocation (Part requires these and they
 * are not in the candidate).
 *
 * Datasheet + image URLs from the merged candidate are downloaded
 * server-side into PartAttachment rows (write-through CAS, so the same PDF
 * shared across many Parts only lives once on disk). The full provenance
 * fan is preserved: every contributing source's URL — not just the winning
 * one — is recorded as a BlobSource on the resulting Blob.
 */
final readonly class AggregatorImporter
{
	public function __construct(
		private EntityManagerInterface    $em,
		private ManufacturerCanonicalizer $manufacturerCanonicalizer,
		private UploadedFileService       $uploadedFileService,
		private DatasheetUrlResolver      $datasheetUrlResolver,
		private LoggerInterface           $logger
	)
	{
	}

	public function import(
		AggregatedPartCandidate $candidate,
		PartCategory            $category,
		StorageLocation         $storageLocation
	): Part
	{
		$mfrRawName = $candidate->manufacturerName->chosenValue ?? '';
		$mpn = $candidate->manufacturerPartNumber->chosenValue ?? '';
		if ($mfrRawName === '' || $mpn === '') {
			throw new \InvalidArgumentException('Candidate is missing manufacturer name or MPN — cannot import.');
		}

		$manufacturer = $this->resolveManufacturer($mfrRawName);

		$part = new Part;
		$part->setName($mpn);
		$part->setDescription($candidate->description->chosenValue ?? '');
		$part->setCategory($category);
		$part->setStorageLocation($storageLocation);

		$pm = (new PartManufacturer)
			->setManufacturer($manufacturer)
			->setPartNumber($mpn);
		$part->addManufacturer($pm);

		foreach ($candidate->providerSpecific as $sourceName => $ds) {
			$distributor = $this->resolveDistributor($sourceName);
			$pd = (new PartDistributor)
				->setDistributor($distributor)
				->setSku($ds->sourceSku)
				->setOrderNumber($ds->sourceSku);
			if ($ds->priceBreaks !== []) {
				$first = $ds->priceBreaks[0];
				$pd->setPrice((string)$first->price);
				$pd->setPackagingUnit(max($first->quantity, 1));
				if ($ds->currency !== null && $ds->currency !== '') {
					$pd->setCurrency(substr($ds->currency, 0, 3));
				}
			}
			$part->addDistributor($pd);
		}

		// Parameter dedup: first non-empty value per raw name wins
		$seen = [];
		foreach ($candidate->parameters as $params) {
			foreach ($params as $p) {
				if ($p->rawValue === '' || isset($seen[$p->rawName])) {
					continue;
				}
				$seen[$p->rawName] = true;
				$pp = (new PartParameter)
					->setName($p->rawName)
					->setStringValue($p->rawValue)
					->setValueType(PartParameter::VALUE_TYPE_STRING);
				$part->addParameter($pp);
			}
		}

		$this->em->persist($part);
		$this->em->flush();

		$this->maybeRegisterAliases($manufacturer, $candidate->manufacturerName);

		// Download attachments AFTER the Part is persisted — `addAttachment`
		// + cascade does the PartAttachment insert; CAS dedup means the
		// physical file lands on disk only once regardless of how many
		// future Parts pull the same datasheet.
		$mfrName = $manufacturer->getName() ?? '';
		$this->attachIfPresent($part, $candidate->datasheetUrl, 'Datasheet', $this->datasheetUrlResolver->candidates($mfrName, $mpn));
		$this->attachIfPresent($part, $candidate->imageUrl, 'Product image', []);

		return $part;
	}

	/**
	 * Materialise a candidate URL into a PartAttachment. Downloads the
	 * winning URL via UploadedFileService::replaceFromURL (which handles
	 * SSRF guard + Cloudflare-style retries + Blob dedup); when the
	 * download is transiently blocked we fall back to a URL-only row so
	 * the retry CLI can pick it up later. Unrecoverable failures (404,
	 * 401, …) trigger the manufacturer-direct URL list (`$fallbackUrls`),
	 * each tried in order with `adapter='mfr-direct'`. Only when EVERY
	 * candidate has been exhausted is the empty attachment rolled back.
	 *
	 * Per-source provenance: every contributing distributor's URL for
	 * this field is recorded as a BlobSource on the resulting Blob —
	 * even if Farnell+DigiKey shipped the same PDF, both URLs land in
	 * the provenance set.
	 *
	 * @param string[] $fallbackUrls Manufacturer-direct URLs tried in order
	 *                               when the chosen distributor URL fails
	 *                               (or is missing entirely).
	 */
	private function attachIfPresent(Part $part, FieldWithProvenance $field, string $descriptionPrefix, array $fallbackUrls = []): void
	{
		$chosen = $field->chosenValue;
		$primaryAvailable = $chosen !== null && $chosen !== '';
		if (!$primaryAvailable && $fallbackUrls === []) {
			return;
		}

		$attachment = new PartAttachment;
		$part->addAttachment($attachment);
		$attachment->setDescription($descriptionPrefix);

		// Tag the auto-created BlobSource with the adapter that contributed
		// the winning URL — otherwise replaceFromURL would seed an
		// adapter=null row alongside our per-source ones, leaving the
		// chosen URL unattributed in the provenance fan.
		$winningSource = null;
		if ($primaryAvailable) {
			foreach ($field->sourcesValues as $source => $url) {
				if ($url === $chosen) {
					$winningSource = $source;
					break;
				}
			}
		}

		$downloaded = false;
		$primaryRecoverable = false;
		if ($primaryAvailable) {
			try {
				$this->uploadedFileService->replaceFromURL($attachment, $chosen, null, $winningSource);
				$downloaded = true;
			} catch (\RuntimeException $e) {
				if ($this->uploadedFileService->isRecoverableDownloadError($e)) {
					$primaryRecoverable = true;
				} else {
					$this->logger->info(sprintf('AggregatorImporter: distributor %s URL %s failed unrecoverably: %s', $descriptionPrefix, $chosen, $e->getMessage()));
				}
			}
		}

		// Try manufacturer-direct fallback patterns when the distributor
		// URL didn't yield a Blob. These hit vendor CDNs (TI, ST, …)
		// directly, no bot-protection from intermediaries.
		if (!$downloaded && $fallbackUrls !== []) {
			foreach ($fallbackUrls as $altUrl) {
				try {
					$this->uploadedFileService->replaceFromURL($attachment, $altUrl, null, 'mfr-direct');
					$downloaded = true;
					$this->logger->info(sprintf('AggregatorImporter: %s recovered via mfr-direct %s', $descriptionPrefix, $altUrl));
					break;
				} catch (\RuntimeException $e) {
					// Soft-fail each pattern — 404 / 403 just means this
					// vendor's URL convention didn't match this MPN.
					continue;
				}
			}
		}

		// Last resort for the primary URL: if it was recoverable (transient
		// Cloudflare / 5xx) AND no fallback succeeded, persist it URL-only
		// so the retry CLI can finish later.
		if (!$downloaded && $primaryRecoverable && $primaryAvailable) {
			try {
				$this->uploadedFileService->saveUrlOnly($attachment, $chosen, $winningSource);
			} catch (\Throwable $e2) {
				$this->logger->warning(sprintf('AggregatorImporter: %s URL-only fallback failed for %s: %s', $descriptionPrefix, $chosen, $e2->getMessage()));
				$part->removeAttachment($attachment);
				return;
			}
		}

		if (!$downloaded && !$primaryRecoverable) {
			// Nothing landed; don't keep an empty attachment.
			$part->removeAttachment($attachment);
			return;
		}

		$this->em->flush();

		// Seed BlobSource for every contributing source's URL — preserves
		// the full provenance fan, not just the winning value. Only runs
		// when a Blob exists (the download succeeded); URL-only fallback
		// has no Blob and its single sourceUrl is already on the row.
		if ($downloaded) {
			$blob = $attachment->getBlob();
			if ($blob !== null) {
				foreach ($field->sourcesValues as $source => $url) {
					if (!is_string($url) || $url === '') {
						continue;
					}
					$this->uploadedFileService->ensureBlobSource($blob, $url, $source);
				}
				$this->em->flush();
			}
		}
	}

	/**
	 * Find canonical Manufacturer via the alias table; create + register first
	 * alias when nothing matches yet
	 */
	private function resolveManufacturer(string $rawName): Manufacturer
	{
		$canonical = $this->manufacturerCanonicalizer->canonicalize($rawName);
		if ($canonical !== null) {
			return $canonical;
		}
		$mfr = new Manufacturer;
		$mfr->setName($rawName);
		$this->em->persist($mfr);
		$this->em->flush(); // need ID for alias FK
		$this->manufacturerCanonicalizer->registerAlias($mfr, $rawName);
		return $mfr;
	}

	private function resolveDistributor(string $sourceName): Distributor
	{
		$d = $this->em->getRepository(Distributor::class)->findOneBy(['name' => $sourceName]);
		if ($d !== null) {
			return $d;
		}
		$d = new Distributor;
		$d->setName($sourceName);
		$this->em->persist($d);
		return $d;
	}

	/**
	 * Each distributor's raw-name spelling that does not yet normalise to the
	 * canonical manufacturer's name becomes a new alias. Silently skip aliases
	 * already pointing at a different manufacturer (very unlikely if grouping
	 * succeeded but possible after manual DB edits).
	 */
	private function maybeRegisterAliases(Manufacturer $mfr, FieldWithProvenance $field): void
	{
		$canonicalKey = ManufacturerCanonicalizer::normalize($mfr->getName() ?? '');
		foreach ($field->sourcesValues as $val) {
			if ($val === null || $val === '') {
				continue;
			}
			if (ManufacturerCanonicalizer::normalize($val) === $canonicalKey) {
				continue;
			}
			try {
				$this->manufacturerCanonicalizer->registerAlias($mfr, $val);
			} catch (\RuntimeException) {
				// Alias already maps to a different manufacturer — keep that one
			}
		}
	}
}
