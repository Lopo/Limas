<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;


final class Version20260601160925
	extends AbstractMigration
{
	/**
	 * All concrete UploadedFile subclass tables. Order matters only for readability of progress output — Phase 2 walks them sequentially.
	 *
	 * @var array<string, string> table name → Gaufrette pool subdirectory
	 */
	private const array TABLE_TO_POOL_DIR = [
		'PartAttachment' => 'files/PartAttachment',
		'ProjectAttachment' => 'files/ProjectAttachment',
		'FootprintAttachment' => 'files/FootprintAttachment',
		'TempUploadedFile' => 'files/Temporary',
		'FootprintImage' => 'images/footprint',
		'ManufacturerICLogo' => 'images/iclogo',
		'StorageLocationImage' => 'images/storagelocation',
		'TempImage' => 'temp'
	];


	public function up(Schema $schema): void
	{
		/* Attachment CAS refactor: split UploadedFile per-row file fields into Blob + BlobSource entities; move storage to sha256-keyed layout under data/blob/<prefix>/<sha> */
		// PHASE 1 — DDL: create Blob + BlobSource, add nullable blob_id to
		// every UploadedFile subclass table. Legacy columns stay for now;
		// Phase 3 drops them after the data walk completes.
		$this->connection->executeStatement(
			'CREATE TABLE `attachment_blob` ('
			. 'id INT AUTO_INCREMENT NOT NULL, '
			. 'sha256 VARCHAR(64) NOT NULL, '
			. 'size INT NOT NULL, '
			. 'filename VARCHAR(80) NOT NULL, '
			. 'mimetype VARCHAR(255) NOT NULL, '
			. 'createdAt DATETIME NOT NULL, '
			. 'UNIQUE INDEX UNIQ_blob_sha256_size (sha256, size), '
			. 'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4'
		);
		// UNIQUE on (blob_id, sourceUrl) with utf8mb4 hits InnoDB's
		// 3072-byte key limit if sourceUrl is the full 2048 chars
		// (2048*4 = 8192 bytes). Prefix the URL portion to 512 chars —
		// that covers 99%+ of real distributor URLs and keeps the index
		// inside the limit (4 + 2048 = 2052 bytes).
		$this->connection->executeStatement(
			'CREATE TABLE BlobSource ('
			. 'id INT AUTO_INCREMENT NOT NULL, '
			. 'blob_id INT DEFAULT NULL, '
			. 'sourceUrl VARCHAR(2048) NOT NULL, '
			. 'adapter VARCHAR(64) DEFAULT NULL, '
			. 'createdAt DATETIME NOT NULL, '
			. 'INDEX IDX_3F94F966ED3E8EA5 (blob_id), '
			. 'UNIQUE INDEX UNIQ_blob_source_url (blob_id, sourceUrl(512)), '
			. 'PRIMARY KEY (id), '
			. 'CONSTRAINT FK_3F94F966ED3E8EA5 FOREIGN KEY (blob_id) REFERENCES `attachment_blob` (id) ON DELETE CASCADE'
			. ') DEFAULT CHARACTER SET utf8mb4'
		);
		foreach (array_keys(self::TABLE_TO_POOL_DIR) as $table) {
			$this->connection->executeStatement("ALTER TABLE `$table` ADD blob_id INT DEFAULT NULL");
			$idx = substr(md5($table . '_blob'), 0, 10);
			$this->connection->executeStatement("ALTER TABLE `$table` ADD CONSTRAINT FK_blob_$idx FOREIGN KEY (blob_id) REFERENCES `attachment_blob` (id) ON DELETE SET NULL");
		}

		// PHASE 2 — data walk: for every blob-carrying row, find or create
		// the Blob, move the physical file into the CAS layout, and link
		// the row to the Blob. Rows with sourceUrl additionally seed a
		// BlobSource. URL-only rows (downloaded=false) skip the file
		// handling; sourceUrl still seeds an orphan-Blob BlobSource so
		// provenance survives even before the retry CLI downloads the file.
		$dataDir = $this->dataDir();
		$blobDir = $dataDir . 'blob/';
		if (!is_dir($blobDir) && !mkdir($blobDir, 0775, true) && !is_dir($blobDir)) {
			throw new \RuntimeException("Could not create blob storage directory: $blobDir");
		}

		$blobIdByShaSize = []; // 'sha:size' → blob_id (in-memory cache to avoid lookup roundtrips)
		$movedShas = []; // sha → true (so we only mv the first file per sha; subsequent dups get unlinked)

		foreach (self::TABLE_TO_POOL_DIR as $table => $poolSubdir) {
			$poolDir = $dataDir . $poolSubdir . '/';
			$rows = $this->connection->fetchAllAssociative("SELECT id, sha256, filename, mimetype, size, sourceUrl, downloaded FROM `$table`");
			foreach ($rows as $row) {
				$rowId = (int)$row['id'];
				$sha = $row['sha256'];
				$filename = $row['filename'];
				$mimetype = ($row['mimetype'] !== null && $row['mimetype'] !== '') ? $row['mimetype'] : 'application/octet-stream';
				$size = (int)$row['size'];
				$sourceUrl = $row['sourceUrl'];
				$downloaded = (int)$row['downloaded'] === 1;
				$blobId = null;

				if ($downloaded && $filename !== null && $filename !== '') {
					$srcPath = $poolDir . $filename;

					// Lazy-hash any row that predates the sha256 backfill.
					// File-missing isn't a hard error — it can mean the fixture set was deleted from disk while DB rows linger.
					// Skip Blob creation but still let any sourceUrl seed a BlobSource (orphan blob_id).
					if (($sha === null || $sha === '') && is_file($srcPath)) {
						$sha = hash_file('sha256', $srcPath);
						if ($size === 0) {
							$fsz = filesize($srcPath);
							$size = $fsz !== false ? $fsz : 0;
						}
					}

					if ($sha !== null && $sha !== '') {
						$key = $sha . ':' . $size;
						if (isset($blobIdByShaSize[$key])) {
							$blobId = $blobIdByShaSize[$key];
							// Duplicate of an already-CAS-ed blob; just drop the old per-attachment file.
							// If the move from a prior row already removed it, this is a noop.
							if (is_file($srcPath) && isset($movedShas[$sha])) {
								@unlink($srcPath);
							}
						} else {
							$blobFilename = substr($sha, 0, 2) . '/' . $sha;
							$blobAbs = $blobDir . $blobFilename;
							if (!is_dir(dirname($blobAbs))) {
								mkdir(dirname($blobAbs), 0775, true);
							}
							// Move the first occurrence of this sha into CAS.
							// Subsequent rows with the same sha hit the $movedShas branch above and just unlink.
							if (is_file($srcPath) && !is_file($blobAbs)) {
								if (!rename($srcPath, $blobAbs)) {
									copy($srcPath, $blobAbs);
									@unlink($srcPath);
								}
								$movedShas[$sha] = true;
							}
							$this->connection->executeStatement(
								'INSERT INTO `attachment_blob` (sha256, size, filename, mimetype, createdAt) VALUES (?, ?, ?, ?, NOW())',
								[$sha, $size, $blobFilename, $mimetype]
							);
							$blobId = (int)$this->connection->lastInsertId();
							$blobIdByShaSize[$key] = $blobId;
						}
					}

					if ($blobId !== null) {
						$this->connection->executeStatement(
							"UPDATE `$table` SET blob_id = ? WHERE id = ?",
							[$blobId, $rowId]
						);
					}
				}

				if ($sourceUrl !== null && $sourceUrl !== '') {
					// blob_id may legitimately be null here (URL-only
					// attachment whose download never completed). Such
					// BlobSources stay orphaned until the retry CLI fills
					// in the Blob and re-links them via (blob_id, sourceUrl)
					// uniqueness; for now we just record the URL.
					try {
						$this->connection->executeStatement(
							'INSERT INTO BlobSource (blob_id, sourceUrl, adapter, createdAt) VALUES (?, ?, NULL, NOW())',
							[$blobId, $sourceUrl]
						);
					} catch (\Throwable) {
						// Unique-key conflict (same Blob + same URL seen twice from different attachment rows) is fine — we already have that provenance edge
					}
				}
			}
		}

		// PHASE 3 — DDL: drop the legacy per-row file columns. Done last so the data walk above could read them
		//
		// NOTE: sourceUrl is intentionally KEPT — post-CAS it holds the
		// "pending download URL" state for URL-only attachments that have
		// no Blob yet (the retry CLI uses it to know what to fetch). Once
		// the download succeeds the URL moves into a BlobSource row and
		// this column is nulled. The data walk above already copied every
		// existing sourceUrl into BlobSource where blob_id was set; rows
		// with blob_id IS NULL kept their sourceUrl so retry still works.
		foreach (array_keys(self::TABLE_TO_POOL_DIR) as $table) {
			$this->connection->executeStatement(
				"ALTER TABLE `$table` "
				. 'DROP filename, '
				. 'DROP mimetype, '
				. 'DROP size, '
				. 'DROP downloaded, '
				. 'DROP sha256'
			);
			// Null the redundant sourceUrl on rows that successfully migrated to a Blob — those now own their provenance via BlobSource and shouldn't keep a stale per-row copy
			$this->connection->executeStatement("UPDATE `$table` SET sourceUrl = NULL WHERE blob_id IS NOT NULL");
		}
	}

	public function down(Schema $schema): void
	{
		// PHASE 1 — DDL: bring legacy columns back as nullable so the
		// data walk can populate them per row. NOT NULL constraints get
		// re-applied in Phase 3 after data is in. sourceUrl already
		// survives in the post-CAS schema (used for URL-only pending
		// state) — we don't re-add it, just repopulate it from the
		// most-recent BlobSource for blob-attached rows.
		foreach (array_keys(self::TABLE_TO_POOL_DIR) as $table) {
			$this->connection->executeStatement(
				"ALTER TABLE `$table` "
				. 'ADD filename VARCHAR(255) DEFAULT NULL, '
				. 'ADD mimetype VARCHAR(255) DEFAULT NULL, '
				. 'ADD size INT DEFAULT NULL, '
				. 'ADD downloaded TINYINT DEFAULT 1 NOT NULL, '
				. 'ADD sha256 VARCHAR(64) DEFAULT NULL'
			);
		}

		// PHASE 2 — data walk in reverse: copy Blob's fields back into each
		// row, copy the physical file from blob/<sha> back to the per-type
		// UUID slot, pick the most-recent BlobSource as the sole sourceUrl,
		// and emit a warning for every Blob that had >1 BlobSource so the
		// information loss is at least visible in the migration log.
		$dataDir = $this->dataDir();
		$blobDir = $dataDir . 'blob/';

		foreach (self::TABLE_TO_POOL_DIR as $table => $poolSubdir) {
			$poolDir = $dataDir . $poolSubdir . '/';
			if (!is_dir($poolDir)) {
				mkdir($poolDir, 0775, true);
			}
			$rows = $this->connection->fetchAllAssociative(
				"SELECT a.id AS rowId, b.id AS bid, b.sha256, b.size, b.filename AS blobFilename, b.mimetype "
				. "FROM `$table` a "
				. 'LEFT JOIN `attachment_blob` b ON b.id = a.blob_id'
			);
			foreach ($rows as $row) {
				$rowId = (int)$row['rowId'];
				$blobId = $row['bid'] !== null ? (int)$row['bid'] : null;
				if ($blobId === null) {
					// URL-only row — restore downloaded=false, leave file fields null. sourceUrl will be filled in below.
					$this->connection->executeStatement(
						"UPDATE `$table` SET downloaded = 0 WHERE id = ?",
						[$rowId]
					);
					$sourceUrl = $this->pickLatestSourceUrl(null, $rowId);
					if ($sourceUrl !== null) {
						$this->connection->executeStatement(
							"UPDATE `$table` SET sourceUrl = ? WHERE id = ?",
							[$sourceUrl, $rowId]
						);
					}
					continue;
				}

				// Generate a fresh UUID for this row's storage slot
				$uuid = $this->generateUuid();
				$srcPath = $blobDir . $row['blobFilename'];
				$dstPath = $poolDir . $uuid;

				if (is_file($srcPath)) {
					copy($srcPath, $dstPath);
				}

				$this->connection->executeStatement(
					"UPDATE `$table` SET filename = ?, sha256 = ?, size = ?, mimetype = ?, downloaded = 1 WHERE id = ?",
					[$uuid, $row['sha256'], (int)$row['size'], $row['mimetype'], $rowId]
				);

				$sourceUrl = $this->pickLatestSourceUrl($blobId, $rowId);
				if ($sourceUrl !== null) {
					$this->connection->executeStatement(
						"UPDATE `$table` SET sourceUrl = ? WHERE id = ?",
						[$sourceUrl, $rowId]
					);
				}
			}
		}

		// Drop the now-empty CAS file tree once every row has its own copy
		// in the per-type pool. Leaves an empty blob/ dir behind — cheap.
		if (is_dir($blobDir)) {
			$this->rmrf($blobDir);
		}

		// PHASE 3 — DDL: re-apply NOT NULL on filename/mimetype/size, drop
		// blob_id FKs, drop CAS tables.
		foreach (array_keys(self::TABLE_TO_POOL_DIR) as $table) {
			$this->connection->executeStatement(
				"ALTER TABLE `$table` "
				. 'MODIFY filename VARCHAR(255) NOT NULL, '
				. 'MODIFY mimetype VARCHAR(255) NOT NULL, '
				. 'MODIFY size INT NOT NULL'
			);
			$idx = substr(md5($table . '_blob'), 0, 10);
			$this->connection->executeStatement("ALTER TABLE `$table` DROP FOREIGN KEY FK_blob_$idx");
			$this->connection->executeStatement("ALTER TABLE `$table` DROP COLUMN blob_id");
		}
		$this->connection->executeStatement('DROP TABLE BlobSource');
		$this->connection->executeStatement('DROP TABLE `attachment_blob`');
	}

	public function isTransactional(): bool
	{
		return false;
	}

	/**
	 * Pick the most-recent BlobSource for a Blob (or for a row whose blob
	 * is null — we have no way to find its BlobSource because the
	 * provenance lives entirely on Blob). Emits a warn-via-write-warning
	 * when collapsing >1 URL so the operator sees what information was
	 * narrowed in the migration log.
	 */
	private function pickLatestSourceUrl(?int $blobId, int $rowId): ?string
	{
		if ($blobId === null) {
			return null;
		}
		$urls = $this->connection->fetchFirstColumn(
			'SELECT sourceUrl FROM BlobSource WHERE blob_id = ? ORDER BY createdAt DESC',
			[$blobId]
		);
		if ($urls === []) {
			return null;
		}
		if (count($urls) > 1) {
			$this->warnIf(true, sprintf(
				'down(): Blob #%d had %d source URLs; keeping the most recent (row #%d) and dropping the rest: %s',
				$blobId, count($urls), $rowId, implode(', ', array_slice($urls, 1))
			));
		}
		return (string)$urls[0];
	}

	/**
	 * Resolve the configured data directory. Mirrors limas.yaml:
	 *   default:  <project>/data/
	 *   when@test: <project>/data_test/
	 */
	private function dataDir(): string
	{
		$projectDir = realpath(__DIR__ . '/..');
		if ($projectDir === false) {
			throw new \RuntimeException('Could not resolve project dir');
		}
		$env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev';
		$leaf = $env === 'test' ? 'data_test/' : 'data/';
		return $projectDir . '/' . $leaf;
	}

	private function rmrf(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $f) {
			$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
		}
		@rmdir($dir);
	}

	/**
	 * Match the pre-CAS `UploadedFile` constructor which used time-based
	 * uuid1 for the per-row filename. Keeps post-down filenames shape-
	 * compatible with any legacy tooling that parses the timestamp out.
	 */
	private function generateUuid(): string
	{
		return Uuid::uuid1()->toString();
	}
}
