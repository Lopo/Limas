<?php

namespace Limas\Command;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Limas\Entity\Blob;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * Maintenance CLI for the content-addressable attachment store
 *
 *   --prune-orphans   Delete Blob rows + their backing files that no
 *                     UploadedFile subclass still references. The
 *                     UploadedFileService::delete path already prunes
 *                     synchronously per attachment delete; this command
 *                     is the cron-able sweep that catches edge cases
 *                     (orphan from an aborted import, manual SQL row
 *                     delete that skipped the service layer, …).
 *
 *   --orphan-files    Detect files on disk under data/blob/ that no Blob
 *                     row points at. Reports them; with `--delete` removes
 *                     them too. Cheap insurance against half-written
 *                     uploads or files left from a rolled-back migration.
 *
 * Pre-CAS this command also offered --backfill (compute sha256 for legacy
 * rows) and --dedupe (collapse duplicates per Part). Both are obsolete:
 * the CAS migration both backfilled hashes and deduped in one shot, and
 * new writes go through the service which dedup automatically by sha256.
 */
#[AsCommand(
	name: 'limas:attachments:hash',
	description: 'Prune orphan Blob rows and orphan files from the CAS attachment store.'
)]
final class AttachmentHashCommand
	extends Command
{
	/** Tables that hold a blob_id FK. Used for the orphan-Blob query. */
	private const array SUBCLASS_TABLES = [
		'PartAttachment',
		'ProjectAttachment',
		'FootprintAttachment',
		'FootprintImage',
		'ManufacturerICLogo',
		'StorageLocationImage',
		'TempUploadedFile',
		'TempImage',
	];


	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly FilesystemOperator     $blobStorage
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('prune-orphans', null, InputOption::VALUE_NONE, 'Delete Blob rows + files no subclass references.')
			->addOption('orphan-files', null, InputOption::VALUE_NONE, 'Detect files on disk with no Blob row pointing at them.')
			->addOption('delete', null, InputOption::VALUE_NONE, 'Without this flag --orphan-files only lists; with it, also unlinks.')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'List actions without writing/deleting anything.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$pruneOrphans = $input->getOption('prune-orphans') === true;
		$orphanFiles = $input->getOption('orphan-files') === true;
		$dryRun = $input->getOption('dry-run') === true;
		$alsoDeleteFiles = $input->getOption('delete') === true;

		if (!$pruneOrphans && !$orphanFiles) {
			$io->warning('Specify --prune-orphans and/or --orphan-files.');
			return self::SUCCESS;
		}

		if ($pruneOrphans) {
			$this->pruneOrphans($io, $dryRun);
		}
		if ($orphanFiles) {
			$this->scanOrphanFiles($io, $dryRun, $alsoDeleteFiles);
		}
		return self::SUCCESS;
	}

	/**
	 * Find every Blob whose id appears in no UploadedFile subclass's
	 * `blob_id` column. The NOT IN list is the union across all subclass
	 * tables — single query with UNION is enough at our scale.
	 */
	private function pruneOrphans(SymfonyStyle $io, bool $dryRun): void
	{
		$io->section('Pruning orphan Blob rows');
		$conn = $this->em->getConnection();

		$referencedUnion = implode(
			' UNION ',
			array_map(
				static fn(string $t): string => "SELECT blob_id FROM `$t` WHERE blob_id IS NOT NULL",
				self::SUBCLASS_TABLES
			)
		);
		$orphans = $conn->fetchAllAssociative(
			"SELECT id, sha256, size, filename FROM `attachment_blob` WHERE id NOT IN ($referencedUnion)"
		);
		if ($orphans === []) {
			$io->writeln('No orphan Blob rows.');
			return;
		}
		$io->writeln(sprintf('Found %d orphan Blob row(s).', count($orphans)));

		$deleted = 0;
		foreach ($orphans as $b) {
			$io->writeln(sprintf('  Blob #%d sha=%s… size=%d → %s', (int)$b['id'], substr((string)$b['sha256'], 0, 12), (int)$b['size'], $dryRun ? 'would delete' : 'deleting'));
			if (!$dryRun) {
				try {
					if ($this->blobStorage->fileExists((string)$b['filename'])) {
						$this->blobStorage->delete((string)$b['filename']);
					}
				} catch (\Throwable $e) {
					$io->writeln(sprintf('    <error>file unlink fail</error>: %s', $e->getMessage()));
				}
				$this->em->getRepository(Blob::class)->createQueryBuilder('b')
					->delete()->where('b.id = :id')->setParameter('id', (int)$b['id'])
					->getQuery()->execute();
				$deleted++;
			}
		}
		$io->success(sprintf('%d orphan Blob(s) %s.', $deleted, $dryRun ? 'would be deleted' : 'deleted'));
	}

	/**
	 * Walk the on-disk blob/ tree, look for files that no Blob row points
	 * to. With --delete, also unlinks them; without, only lists. Cheap
	 * sanity sweep for orphans left by an aborted upload or restored
	 * snapshot that diverges from the DB.
	 */
	private function scanOrphanFiles(SymfonyStyle $io, bool $dryRun, bool $alsoDelete): void
	{
		$io->section('Scanning for orphan files on disk');
		$conn = $this->em->getConnection();
		$knownPaths = array_flip(
			array_map('strval', $conn->fetchFirstColumn('SELECT filename FROM `attachment_blob`'))
		);

		// Flysystem's listContents → file-only DirectoryListing of
		// StorageAttributes. Recursive walk returns paths relative to
		// the pool root — exactly what Blob.filename stores, so the
		// set-diff against the DB column is trivial.
		$allOnDisk = $this->blobStorage->listContents('', FilesystemOperator::LIST_DEEP)
			->filter(static fn(StorageAttributes $a): bool => $a->isFile())
			->map(static fn(StorageAttributes $a): string => $a->path())
			->toArray();
		$orphans = array_values(array_filter(
			$allOnDisk,
			static fn(string $p): bool => !isset($knownPaths[$p])
		));
		if ($orphans === []) {
			$io->writeln('No orphan files.');
			return;
		}
		$io->writeln(sprintf('Found %d orphan file(s).', count($orphans)));

		foreach ($orphans as $path) {
			$io->writeln('  ' . $path . ($alsoDelete && !$dryRun ? ' — deleted' : ($alsoDelete ? ' — would delete' : '')));
			if ($alsoDelete && !$dryRun) {
				try {
					$this->blobStorage->delete($path);
				} catch (\Throwable $e) {
					$io->writeln(sprintf('    <error>fail</error>: %s', $e->getMessage()));
				}
			}
		}
		if (!$alsoDelete) {
			$io->note('Add --delete to actually remove orphan files (defaults to list-only).');
		}
	}
}
