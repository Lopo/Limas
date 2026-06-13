<?php

namespace Limas\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Limas\DataFixtures\ParameterAliasFixtures;
use Limas\Entity\BatchJob;
use Limas\Entity\BatchJobQueryField;
use Limas\Entity\BatchJobUpdateField;
use Limas\Entity\CachedImage;
use Limas\Entity\Distributor;
use Limas\Entity\Footprint;
use Limas\Entity\FootprintAttachment;
use Limas\Entity\FootprintCategory;
use Limas\Entity\FootprintImage;
use Limas\Entity\GridPreset;
use Limas\Entity\ImportPreset;
use Limas\Entity\Manufacturer;
use Limas\Entity\ManufacturerICLogo;
use Limas\Entity\MetaPartParameterCriteria;
use Limas\Entity\Part;
use Limas\Entity\PartAttachment;
use Limas\Entity\PartCategory;
use Limas\Entity\PartDistributor;
use Limas\Entity\PartManufacturer;
use Limas\Entity\PartMeasurementUnit;
use Limas\Entity\PartParameter;
use Limas\Entity\Project;
use Limas\Entity\ProjectAttachment;
use Limas\Entity\ProjectPart;
use Limas\Entity\ProjectRun;
use Limas\Entity\ProjectRunPart;
use Limas\Entity\Report;
use Limas\Entity\ReportPart;
use Limas\Entity\ReportProject;
use Limas\Entity\SiPrefix;
use Limas\Entity\StatisticSnapshot;
use Limas\Entity\StatisticSnapshotUnit;
use Limas\Entity\StockEntry;
use Limas\Entity\StorageLocation;
use Limas\Entity\StorageLocationCategory;
use Limas\Entity\StorageLocationImage;
use Limas\Entity\SystemNotice;
use Limas\Entity\SystemPreference;
use Limas\Entity\TipOfTheDay;
use Limas\Entity\TipOfTheDayHistory;
use Limas\Entity\Unit;
use Limas\Entity\User;
use Limas\Entity\UserPreference;
use Limas\Entity\UserProvider;
use Limas\Service\Integration\InfoProvider\ParameterNormalizer;
use Limas\Service\Integration\InfoProvider\ParameterValueParser;
use Limas\Service\Integration\InfoProvider\Dto\Parameter as InfoProviderParameter;
use Limas\Service\ManufacturerCanonicalizer;
use Limas\Service\UserService;
use Nette\Utils\FileSystem as NFileSystem;
use Nette\Utils\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;


#[AsCommand(
	name: 'limas:import:partkeepr',
	description: 'Import PartKeepr data'
)]
class ImportPartKeeprCommand
	extends Command
{
	private Connection $connect;
	private bool $lowercase = false;


	public function __construct(
		private readonly EntityManagerInterface  $entityManager,
		private readonly FilesystemOperator      $blobStorage,
		private readonly UserService             $userService,
		private readonly ManufacturerCanonicalizer $manufacturerCanonicalizer,
		private readonly ParameterNormalizer     $parameterNormalizer,
		private readonly ParameterValueParser    $parameterValueParser
	)
	{
		parent::__construct();
		$this->connect = $this->entityManager->getConnection();
	}

	/**
	 * Read a PartKeepr attachment payload from disk and compute its
	 * SHA-256 content hash in the same pass. Used by the attachment
	 * import methods so each row gets its `sha256` column populated
	 * inline — avoids needing a separate `limas:attachments:hash
	 * --backfill` run after import. Returned bytes feed straight into
	 * the Gaufrette storage write below.
	 *
	 * @return array{0: string, 1: string}  [bytes, sha256 hex]
	 */
	private function readBytesAndHash(string $sourcePath): array
	{
		$bytes = NFileSystem::read($sourcePath);
		return [$bytes, hash('sha256', $bytes)];
	}

	/**
	 * Ensure a Blob row exists for these bytes; write the file into the
	 * blob CAS pool if needed. Returns the Blob's id so the attachment
	 * insert can set blob_id. Idempotent — re-running the import won't
	 * duplicate Blob rows or rewrite identical files.
	 *
	 * Used by every attachment-import method as the post-CAS replacement
	 * for the old per-type pool write.
	 */
	private function ensureBlobForImport(string $bytes, string $sha, string $mimetype, int $size): int
	{
		$existing = $this->connect->fetchOne('SELECT id FROM `attachment_blob` WHERE sha256 = ? AND size = ?', [$sha, $size]);
		if ($existing !== false) {
			return (int)$existing;
		}
		$blobFilename = substr($sha, 0, 2) . '/' . $sha;
		if (!$this->blobStorage->fileExists($blobFilename)) {
			$this->blobStorage->write($blobFilename, $bytes);
		}
		$this->connect->executeStatement(
			'INSERT INTO `attachment_blob` (sha256, size, filename, mimetype, createdAt) VALUES (?, ?, ?, ?, NOW())',
			[$sha, $size, $blobFilename, $mimetype]
		);
		return (int)$this->connect->lastInsertId();
	}

	protected function configure(): void
	{
		$this
			->addOption('pkdsn', null, InputOption::VALUE_REQUIRED, 'PK DB hostname', 'mysql://root:root@localhost:3306/partkeepr')
			->addOption('pkroot', null, InputOption::VALUE_REQUIRED, 'PK root dir')
			->addOption('lowercase', null, InputOption::VALUE_NONE, 'Lowercase source PK table names')
			->addOption('prepare-aggregator', null, InputOption::VALUE_NONE,
				'Also seed Manufacturer self-aliases, load the Octopart ParameterAlias taxonomy, '
				. 'and backfill canonical/numeric fields on imported PartParameters. '
				. 'Skip if you only want a plain PartKeepr → Limas data copy without aggregator preparation.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$depFac = DependencyFactory::fromEntityManager(new ExistingConfiguration(new Configuration), new ExistingEntityManager($this->entityManager));
		$aliasResolver = $depFac->getVersionAliasResolver();
		$verCurrent = $aliasResolver->resolveVersionAlias('current');
		if ((string)$verCurrent === '0') {
			$io->error('Limas DB not created yet, run `bin/console doctrine:migrations:migrate`');
			return Command::FAILURE;
		}
		if (!$verCurrent->equals($aliasResolver->resolveVersionAlias('latest'))) {
			$io->error('Limas DB is not up to date, run `bin/console doctrine:migrations:migrate`');
			return Command::FAILURE;
		}
		if ($this->entityManager->getRepository(User::class)->count([]) !== 0 || $this->entityManager->getRepository(SiPrefix::class)->count([]) !== 0) {
			$io->error('Limas DB already contains some data');
			return Command::FAILURE;
		}

		$pk = DriverManager::getConnection(['url' => $input->getOption('pkdsn')], new \Doctrine\DBAL\Configuration);
		if (!$pk->createSchemaManager()->tablesExist(['SchemaVersions'])) {
			$io->error('PartKeepr DB error - SchemaVersions table not found');
			return Command::FAILURE;
		}
		$verPK = $pk->executeQuery('SELECT MAX(version) FROM SchemaVersions')->fetchOne();
		if ($verPK !== '20170601175559') {
			$io->error("The version of PartKeepr DB ($verPK) is not supported");
			return Command::FAILURE;
		}

		if (null === ($pkRoot = $input->getOption('pkroot'))) {
			$io->error('missing pkroot option');
			return Command::FAILURE;
		}
		$fs = new Filesystem;
		if (!$fs->exists($pkRoot) || !$fs->isAbsolutePath($pkRoot) || !NFileSystem::isAbsolute($pkRoot)) {
			$io->error('pkroot must be existing absolute path');
			return Command::FAILURE;
		}
		$dataDir = $pkRoot . '/data';
		$this->lowercase = $input->getOption('lowercase');

		$this->importFootprintCategory($io, $pk);
		$this->importPartCategory($io, $pk);
		$this->importStorageLocationCategory($io, $pk);
		$this->importPartUnit($io, $pk);
		$this->importSiPrefix($io, $pk);
		$this->importUnit($io, $pk);
		$this->importUserProvider($io, $pk);
		// CronLogger entity dropped — recurring jobs migrated to
		// Symfony Scheduler. PartKeepr's `CronLogger` rows have no
		// landing place; the data was bookkeeping for the legacy
		// cron pipeline and is meaningless after migration.
		$this->importBatchJob($io, $pk);
		$this->importBatchJobUpdateField($io, $pk);
		$this->importBatchJobQueryField($io, $pk);
		$this->importDistributor($io, $pk);
		$this->importFootprint($io, $pk);
		$this->importStorageLocation($io, $pk);
		$this->importUser($io, $pk);
		$this->importTipOfTheDayHistory($io, $pk);
		$this->importUserPreference($io, $pk);
		$this->importStatisticSnapshot($io, $pk);
		$this->importStatisticSnapshotUnit($io, $pk);
		$this->importProject($io, $pk);
		$this->importProjectRun($io, $pk);
		$this->importManufacturer($io, $pk);
		$this->importReport($io, $pk);
		$this->importReportProject($io, $pk);
		$this->importFootprintAttachment($io, $pk, $dataDir . '/files/FootprintAttachment');
		$this->importFootprintImage($io, $pk, $dataDir . '/images/footprint');
		$this->importStorageLocationImage($io, $pk, $dataDir . '/images/storagelocation');
		$this->importProjectAttachment($io, $pk, $dataDir . '/files/ProjectAttachment');
		$this->importPart($io, $pk);
		$this->importProjectRunPart($io, $pk);
		$this->importProjectPart($io, $pk);
		$this->importReportPart($io, $pk);
		$this->importPartDistributor($io, $pk);
		$this->importStockEntry($io, $pk);
		$this->importPartAttachment($io, $pk, $dataDir . '/files/PartAttachment');
		$this->importPartManufacturer($io, $pk);
		$this->importUnitSiPrefixes($io, $pk);
		$this->importPartParameter($io, $pk);
		$this->importMetaPartParameterCriteria($io, $pk);
		$this->importManufacturerICLogo($io, $pk, $dataDir . '/images/iclogo');
		$this->importTipOfTheDay($io, $pk);
		$this->importCachedImage($io, $pk);
		$this->importGridPreset($io, $pk);
		$this->importImportPreset($io, $pk);
		$this->importSystemNotice($io, $pk);
		$this->importSystemPreference($io, $pk);

		$prepareAggregator = $input->getOption('prepare-aggregator') === true;
		if ($prepareAggregator) {
			// Aggregator-readiness backfill — pre-populates the
			// ManufacturerAlias + ParameterAlias tables so the aggregator's
			// canonicalizers don't have to learn from scratch on first use.
			// Order matters: param taxonomy seed BEFORE PartParameter alias
			// auto-creation so the normalizer's lookup finds Octopart
			// canonicals first; numeric backfill last because it depends
			// on the alias rows existing.
			$this->seedManufacturerSelfAliases($io);
			$this->seedParameterTaxonomy($io);
			$this->backfillParameterAliases($io);
			$this->backfillPartParameterNumerics($io);
		}

		$io->success('PartKeepr import finished.');
		$io->section('Next steps');
		$nextSteps = [];
		if (!$prepareAggregator) {
			$nextSteps[] = '(Optional) Prepare InfoProvider aggregator data — load Octopart taxonomy, seed Manufacturer aliases, backfill parameter canonicals + numerics over imported data. Re-run import with `--prepare-aggregator`, OR (if you don\'t want to re-import) load just the taxonomy by hand:' . PHP_EOL
				. '    php bin/console doctrine:fixtures:load --group=parameter-taxonomy --append';
		}
		$nextSteps[] = 'Set distributor API credentials in .env.local — DigiKey, Farnell/element14, TME, OEMSecrets, etc. (see .env.example.distributors).' . PHP_EOL
			. '    Adapters with missing keys stay invisible; the aggregator picks up the rest automatically.';
		$nextSteps[] = 'LCSC needs no key — set `LCSC_ENABLED=1` in .env.local and it lights up via the unauthenticated jlcsearch + wmsc endpoints.';
		$nextSteps[] = '(Optional) Generate JWT keypair if you haven\'t already:' . PHP_EOL
			. '    php bin/console lexik:jwt:generate-keypair';
		$io->listing($nextSteps);
		if ($prepareAggregator) {
			$io->note('Attachment sha256 hashes, Manufacturer self-aliases, Octopart parameter taxonomy, and PartParameter numeric backfill were all populated automatically during this import.');
		} else {
			$io->note('Attachment sha256 hashes were computed inline during this import — no need to run `limas:attachments:hash --backfill` afterwards.');
		}

		return Command::SUCCESS;
	}

	/**
	 * Register each imported Manufacturer as a self-alias in the
	 * ManufacturerAlias table. The aggregator's canonicalizer has a
	 * direct-name fallback when no alias matches, but seeding self-aliases
	 * here gives the lookup a uniform path and makes the alias table
	 * useful to the user as an editable mapping of "all vendor name spellings".
	 */
	private function seedManufacturerSelfAliases(OutputStyle $io): void
	{
		$io->note('Seeding ManufacturerAlias self-aliases');
		$manufacturers = $this->entityManager->getRepository(Manufacturer::class)->findAll();
		$bar = $io->createProgressBar(count($manufacturers));
		$bar->start();
		$skipped = 0;
		foreach ($manufacturers as $manufacturer) {
			try {
				$this->manufacturerCanonicalizer->registerAlias($manufacturer, $manufacturer->getName());
			} catch (\Throwable) {
				// Alias for that normalized form already exists pointing
				// elsewhere — rare on a fresh import (two manufacturers
				// only differing in casing), skip and let the user resolve.
				$skipped++;
			}
			$bar->advance();
		}
		$this->entityManager->flush();
		$bar->finish();
		$bar->clear();
		if ($skipped > 0) {
			$io->warning(sprintf('%d manufacturer(s) skipped due to alias conflicts.', $skipped));
		}
	}

	/**
	 * Load the Octopart-seeded ParameterAlias taxonomy + per-vendor
	 * mappings inline so the user doesn't have to remember the separate
	 * `doctrine:fixtures:load --group=parameter-taxonomy --append`
	 * command after import. Same data the fixture would produce, just
	 * invoked directly from PHP.
	 */
	private function seedParameterTaxonomy(OutputStyle $io): void
	{
		$io->note('Seeding ParameterAlias taxonomy (Octopart 757 attributes + per-vendor mappings)');
		$fixture = new ParameterAliasFixtures();
		$fixture->load($this->entityManager);
		// `load()` already flushes; nothing extra to do here.
	}

	/**
	 * Walk every imported PartParameter and run its `name` through the
	 * ParameterNormalizer. The normalizer auto-creates an unverified
	 * ParameterAlias row when none exists, so post-import the alias
	 * table covers BOTH the Octopart canonicals (from seedParameterTaxonomy)
	 * AND every parameter name the user actually had in their PartKeepr
	 * data — ready for the aggregator to match against on first query.
	 */
	private function backfillParameterAliases(OutputStyle $io): void
	{
		$io->note('Backfilling ParameterAlias rows for imported PartParameter names');
		$distinct = $this->connect
			->executeQuery('SELECT DISTINCT name FROM PartParameter WHERE name IS NOT NULL AND name <> \'\'')
			->fetchFirstColumn();
		$bar = $io->createProgressBar(count($distinct));
		$bar->start();
		foreach ($distinct as $name) {
			// `canonicalize()` is idempotent — returns the canonical name
			// for known rawNames (no DB write), creates an auto/unverified
			// alias row when not. We ignore the return value; what matters
			// is that the side-effect (alias row creation) happens.
			$this->parameterNormalizer->canonicalize((string)$name);
			$bar->advance();
		}
		$this->entityManager->flush();
		$bar->finish();
		$bar->clear();
	}

	/**
	 * Stage-2 backfill: for every PartParameter that has a non-empty
	 * `stringValue` but EMPTY numeric columns (value / minimumValue /
	 * maximumValue all null), run the value parser to extract numeric
	 * value + unit + SI prefix from the string. PartKeepr usually fills
	 * these from its own UI, but partial / pre-PartKeepr-2 imports may
	 * have stringValue-only rows; this lifts those into searchable
	 * numeric form.
	 *
	 * Unit + SI prefix lookup is by symbol — same approach as the
	 * frontend's `applyParameters` after aggregator Apply Data. Falls
	 * back to leaving the FK null when the symbol isn't in the seeded
	 * Unit/SiPrefix tables.
	 */
	private function backfillPartParameterNumerics(OutputStyle $io): void
	{
		$io->note('Backfilling PartParameter numeric values from stringValue');
		$repo = $this->entityManager->getRepository(PartParameter::class);
		// Eligible: any row where all three numeric columns are null but
		// the string carries something we can parse. We page through
		// repositories with iterateToFlush for large catalogs.
		$qb = $repo->createQueryBuilder('p')
			->where('p.value IS NULL')
			->andWhere('p.minValue IS NULL')
			->andWhere('p.maxValue IS NULL')
			->andWhere('p.stringValue IS NOT NULL')
			->andWhere('p.stringValue <> :empty')
			->setParameter('empty', '');
		$candidates = $qb->getQuery()->getResult();
		$bar = $io->createProgressBar(count($candidates));
		$bar->start();
		$unitRepo = $this->entityManager->getRepository(Unit::class);
		$siPrefixRepo = $this->entityManager->getRepository(SiPrefix::class);
		// In-process lookup cache so we don't hammer the DB per row when
		// the same unit/prefix symbol repeats across thousands of params.
		$unitBySymbol = [];
		$siPrefixBySymbol = [];
		$updated = 0;
		foreach ($candidates as $pp) {
			/** @var PartParameter $pp */
			$dto = new InfoProviderParameter(rawName: $pp->getName(), rawValue: $pp->getStringValue());
			$this->parameterValueParser->parse($dto);
			$hasNumeric = $dto->numericValue !== null
				|| $dto->numericMin !== null
				|| $dto->numericMax !== null;
			if (!$hasNumeric) {
				$bar->advance();
				continue;
			}
			if ($dto->numericValue !== null) {
				$pp->setValue($dto->numericValue);
			}
			if ($dto->numericMin !== null) {
				$pp->setMinValue($dto->numericMin);
			}
			if ($dto->numericMax !== null) {
				$pp->setMaxValue($dto->numericMax);
			}
			$pp->setValueType(PartParameter::VALUE_TYPE_NUMERIC);
			if ($dto->unit !== null && $dto->unit !== '') {
				if (!array_key_exists($dto->unit, $unitBySymbol)) {
					$unitBySymbol[$dto->unit] = $unitRepo->findOneBy(['symbol' => $dto->unit]);
				}
				if ($unitBySymbol[$dto->unit] !== null) {
					$pp->setUnit($unitBySymbol[$dto->unit]);
				}
			}
			if ($dto->siPrefix !== null && $dto->siPrefix !== '') {
				if (!array_key_exists($dto->siPrefix, $siPrefixBySymbol)) {
					$siPrefixBySymbol[$dto->siPrefix] = $siPrefixRepo->findOneBy(['symbol' => $dto->siPrefix]);
				}
				if ($siPrefixBySymbol[$dto->siPrefix] !== null) {
					$prefix = $siPrefixBySymbol[$dto->siPrefix];
					if ($dto->numericValue !== null) {
						$pp->setSiPrefix($prefix);
					}
					if ($dto->numericMin !== null) {
						$pp->setMinSiPrefix($prefix);
					}
					if ($dto->numericMax !== null) {
						$pp->setMaxSiPrefix($prefix);
					}
				}
			}
			$updated++;
			$bar->advance();
		}
		$this->entityManager->flush();
		$bar->finish();
		$bar->clear();
		$io->writeln(sprintf('  → %d PartParameter row(s) gained numeric data.', $updated));
	}

	private function importBatchJob(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'batchjob' : 'BatchJob';
		$io->note('Importing BatchJob');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(BatchJob::class)->getTableName())
				->values([
					'id' => ':id',
					'name' => ':name',
					'baseEntity' => ':baseEntity'
				])
				->setParameters([
					'id' => $row['id'],
					'name' => $row['name'],
					'baseEntity' => $row['baseEntity']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importBatchJobQueryField(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'batchjobqueryfield' : 'BatchJobQueryField';
		$io->note('Importing BatchJobQueryField');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(BatchJobQueryField::class)->getTableName())
				->values([
					'id' => ':id',
					'property' => ':property',
					'operator' => ':operator',
					'value' => ':value',
					'description' => ':description',
					'dynamic' => ':dynamic',
					'batchJob_id' => ':batchJob_id'
				])
				->setParameters([
					'id' => $row['id'],
					'property' => $row['property'],
					'operator' => $row['operator'],
					'value' => $row['value'],
					'description' => $row['description'],
					'dynamic' => $row['dynamic'],
					'batchJob_id' => $row['batchJob_id']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importBatchJobUpdateField(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'batchjobupdatefield' : 'BatchJobUpdateField';
		$io->note('Importing BatchJobUpdateField');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(BatchJobUpdateField::class)->getTableName())
				->values([
					'id' => ':id',
					'property' => ':property',
					'value' => ':value',
					'description' => ':description',
					'dynamic' => ':dynamic',
					'batchJob_id' => ':batchJob_id'
				])
				->setParameters([
					'id' => $row['id'],
					'property' => $row['property'],
					'value' => $row['value'],
					'description' => $row['description'],
					'dynamic' => $row['dynamic'],
					'batchJob_id' => $row['batchJob_id']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importCachedImage(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'cachedimage' : 'CachedImage';
		$io->note('Importing CachedImage');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(CachedImage::class)->getTableName())
				->values([
					'id' => ':id',
					'originalId' => ':originalId',
					'originalType' => ':originalType',
					'cacheFile' => ':cacheFile'
				])
				->setParameters([
					'id' => $row['id'],
					'originalId' => $row['originalId'],
					'originalType' => $row['originalType'],
					'cacheFile' => $row['cacheFile']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importDistributor(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'distributor' : 'Distributor';
		$io->note('Importing Distributor');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(Distributor::class)->getTableName())
				->values([
					'id' => ':id',
					'name' => ':name',
					'address' => ':address',
					'url' => ':url',
					'phone' => ':phone',
					'fax' => ':fax',
					'email' => ':email',
					'comment' => ':comment',
					'skuurl' => ':skuurl',
					'enabledForReports' => ':enabledForReports',
				])
				->setParameters([
					'id' => $row['id'],
					'name' => $row['name'],
					'address' => $row['address'],
					'url' => $row['url'],
					'phone' => $row['phone'],
					'fax' => $row['fax'],
					'email' => $row['email'],
					'comment' => $row['comment'],
					'skuurl' => $row['skuurl'],
					'enabledForReports' => $row['enabledForReports']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importFootprint(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'footprint' : 'Footprint';
		$io->note('Importing Footprint');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(Footprint::class)->getTableName())
				->values([
					'id' => ':id',
					'category_id' => ':category_id',
					'name' => ':name',
					'description' => ':description'
				])
				->setParameters([
					'id' => $row['id'],
					'category_id' => $row['category_id'],
					'name' => $row['name'],
					'description' => $row['description']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importFootprintAttachment(OutputStyle $io, Connection $pk, string $dataDir): void
	{
		$pkTable = $this->lowercase ? 'footprintattachment' : 'FootprintAttachment';
		$qb = new QueryBuilder($this->connect);
		$io->note('Importing FootprintAttachment');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			[$bytes, $sha] = $this->readBytesAndHash($dataDir . '/' . $row['filename'] . '.' . $row['extension']);
			$blobId = $this->ensureBlobForImport($bytes, $sha, $row['mimetype'], (int)$row['size']);
			$qb->insert($this->entityManager->getClassMetadata(FootprintAttachment::class)->getTableName())
				->values([
					'id' => ':id',
					'footprint_id' => ':footprint_id',
					'type' => ':type',
					'originalname' => ':originalname',
					'description' => ':description',
					'created' => ':created',
					'blob_id' => ':blob_id'
				])
				->setParameters([
					'id' => $row['id'],
					'footprint_id' => $row['footprint_id'],
					'type' => $row['type'],
					'originalname' => $row['originalname'],
					'description' => $row['description'],
					'created' => $row['created'],
					'blob_id' => $blobId
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importFootprintCategory(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'footprintcategory' : 'FootprintCategory';
		$io->note('Importing FootprintCategory');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable ORDER BY parent_id")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(FootprintCategory::class)->getTableName())
				->values([
					'id' => ':id',
					'parent_id' => ':parent',
					'lft' => ':lft',
					'rgt' => ':rgt',
					'lvl' => ':lvl',
					'root' => ':root',
					'name' => ':name',
					'description' => ':description',
					'categoryPath' => ':path'
				])
				->setParameters([
					'id' => (int)$row['id'],
					'parent' => $row['parent_id'],
					'lft' => $row['lft'],
					'rgt' => $row['rgt'],
					'lvl' => $row['lvl'],
					'root' => $row['root'],
					'name' => $row['name'],
					'description' => $row['description'],
					'path' => $row['categoryPath']
				])->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importFootprintImage(OutputStyle $io, Connection $pk, string $dataDir): void
	{
		$pkTable = $this->lowercase ? 'footprintimage' : 'FootprintImage';
		$qb = new QueryBuilder($this->connect);
		$io->note('Importing FootprintImage');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			[$bytes, $sha] = $this->readBytesAndHash($dataDir . '/' . $row['filename'] . '.' . $row['extension']);
			$blobId = $this->ensureBlobForImport($bytes, $sha, $row['mimetype'], (int)$row['size']);
			$qb->insert($this->entityManager->getClassMetadata(FootprintImage::class)->getTableName())
				->values([
					'id' => ':id',
					'footprint_id' => ':footprint_id',
					'type' => ':type',
					'originalname' => ':originalname',
					'description' => ':description',
					'created' => ':created',
					'blob_id' => ':blob_id'
				])
				->setParameters([
					'id' => $row['id'],
					'footprint_id' => $row['footprint_id'],
					'type' => $row['type'],
					'originalname' => $row['originalname'],
					'description' => $row['description'],
					'created' => $row['created'],
					'blob_id' => $blobId
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importGridPreset(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'gridpreset' : 'GridPreset';
		$io->note('Importing GridPreset');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(GridPreset::class)->getTableName())
				->values([
					'id' => ':id',
					'grid' => ':grid',
					'name' => ':name',
					'configuration' => ':configuration',
					'gridDefault' => ':gridDefault'
				])
				->setParameters([
					'id' => (int)$row['id'],
					'grid' => $row['grid'],
					'name' => $row['name'],
					'configuration' => $row['configuration'],
					'gridDefault' => $row['gridDefault']
				])->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importImportPreset(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'importpreset' : 'ImportPreset';
		$io->note('Importing ImportPreset');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(ImportPreset::class)->getTableName())
				->values([
					'id' => ':id',
					'baseEntity' => ':baseEntity',
					'name' => ':name',
					'configuration' => ':configuration'
				])
				->setParameters([
					'id' => (int)$row['id'],
					'baseEntity' => $row['baseEntity'],
					'name' => $row['name'],
					'configuration' => $row['configuration']
				])->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importManufacturer(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'manufacturer' : 'Manufacturer';
		$io->note('Importing Manufacturer');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(Manufacturer::class)->getTableName())
				->values([
					'id' => ':id',
					'name' => ':name',
					'address' => ':address',
					'url' => ':url',
					'email' => ':email',
					'comment' => ':comment',
					'phone' => ':phone',
					'fax' => ':fax'
				])
				->setParameters([
					'id' => $row['id'],
					'name' => $row['name'],
					'address' => $row['address'],
					'url' => $row['url'],
					'email' => $row['email'],
					'comment' => $row['comment'],
					'phone' => $row['phone'],
					'fax' => $row['fax']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importManufacturerICLogo(OutputStyle $io, Connection $pk, string $dataDir): void
	{
		$pkTable = $this->lowercase ? 'manufacturericlogo' : 'ManufacturerICLogo';
		$qb = new QueryBuilder($this->connect);
		$io->note('Importing ManufacturerICLogo');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			[$bytes, $sha] = $this->readBytesAndHash($dataDir . '/' . $row['filename'] . '.' . $row['extension']);
			$blobId = $this->ensureBlobForImport($bytes, $sha, $row['mimetype'], (int)$row['size']);
			$qb->insert($this->entityManager->getClassMetadata(ManufacturerICLogo::class)->getTableName())
				->values([
					'id' => ':id',
					'manufacturer_id' => ':manufacturer_id',
					'type' => ':type',
					'originalname' => ':originalname',
					'description' => ':description',
					'created' => ':created',
					'blob_id' => ':blob_id'
				])
				->setParameters([
					'id' => $row['id'],
					'manufacturer_id' => $row['manufacturer_id'],
					'type' => $row['type'],
					'originalname' => $row['originalname'],
					'description' => $row['description'],
					'created' => $row['created'],
					'blob_id' => $blobId
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importMetaPartParameterCriteria(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'metapartparametercriteria' : 'MetaPartParameterCriteria';
		$io->note('Importing MetaPartParameterCriteria');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(MetaPartParameterCriteria::class)->getTableName())
				->values([
					'id' => ':id',
					'part_id' => ':part_id',
					'unit_id' => ':unit_id',
					'partParameterName' => ':partParameterName',
					'operator' => ':operator',
					'value' => ':value',
					'normalizedValue' => ':normalizedValue',
					'stringValue' => ':stringValue',
					'valueType' => ':valueType',
					'siPrefix_id' => ':siPrefix_id'
				])
				->setParameters([
					'id' => $row['id'],
					'part_id' => $row['part_id'],
					'unit_id' => $row['unit_id'],
					'partParameterName' => $row['partParameterName'],
					'operator' => $row['operator'],
					'value' => $row['value'],
					'normalizedValue' => $row['normalizedValue'],
					'stringValue' => $row['stringValue'],
					'valueType' => $row['valueType'],
					'siPrefix_id' => $row['siPrefix_id']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importPart(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'part' : 'Part';
		$io->note('Importing Part');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(Part::class)->getTableName())
				->values([
					'id' => ':id',
					'category_id' => ':category_id',
					'footprint_id' => ':footprint_id',
					'name' => ':name',
					'description' => ':description',
					'comment' => ':comment',
					'stockLevel' => ':stockLevel',
					'minStockLevel' => ':minStockLevel',
					'averagePrice' => ':averagePrice',
					'status' => ':status',
					'needsReview' => ':needsReview',
					'partCondition' => ':partCondition',
					'productionRemarks' => ':productionRemarks',
					'createDate' => ':createDate',
					'internalPartNumber' => ':internalPartNumber',
					'removals' => ':removals',
					'lowStock' => ':lowStock',
					'metaPart' => ':metaPart',
					'partUnit_id' => ':partUnit_id',
					'storageLocation_id' => ':storageLocation_id'
				])
				->setParameters([
					'id' => $row['id'],
					'category_id' => $row['category_id'],
					'footprint_id' => $row['footprint_id'],
					'name' => $row['name'],
					'description' => $row['description'],
					'comment' => $row['comment'],
					'stockLevel' => $row['stockLevel'],
					'minStockLevel' => $row['minStockLevel'],
					'averagePrice' => $row['averagePrice'],
					'status' => $row['status'],
					'needsReview' => $row['needsReview'],
					'partCondition' => $row['partCondition'],
					'productionRemarks' => $row['productionRemarks'],
					'createDate' => $row['createDate'],
					'internalPartNumber' => $row['internalPartNumber'],
					'removals' => $row['removals'],
					'lowStock' => $row['lowStock'],
					'metaPart' => $row['metaPart'],
					'partUnit_id' => $row['partUnit_id'],
					'storageLocation_id' => $row['storageLocation_id']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importPartAttachment(OutputStyle $io, Connection $pk, string $dataDir): void
	{
		$pkTable = $this->lowercase ? 'partattachment' : 'PartAttachment';
		$qb = new QueryBuilder($this->connect);
		$io->note('Importing PartAttachment');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			[$bytes, $sha] = $this->readBytesAndHash($dataDir . '/' . $row['filename'] . '.' . $row['extension']);
			$blobId = $this->ensureBlobForImport($bytes, $sha, $row['mimetype'], (int)$row['size']);
			$qb->insert($this->entityManager->getClassMetadata(PartAttachment::class)->getTableName())
				->values([
					'id' => ':id',
					'part_id' => ':part_id',
					'type' => ':type',
					'originalname' => ':originalname',
					'description' => ':description',
					'created' => ':created',
					'isImage' => ':isImage',
					'blob_id' => ':blob_id'
				])
				->setParameters([
					'id' => $row['id'],
					'part_id' => $row['part_id'],
					'type' => $row['type'],
					'originalname' => $row['originalname'],
					'description' => $row['description'],
					'created' => $row['created'],
					'isImage' => $row['isImage'],
					'blob_id' => $blobId
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importPartCategory(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'partcategory' : 'PartCategory';
		$io->note('Importing PartCategory');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable ORDER BY parent_id")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(PartCategory::class)->getTableName())
				->values([
					'id' => ':id',
					'parent_id' => ':parent',
					'lft' => ':lft',
					'rgt' => ':rgt',
					'lvl' => ':lvl',
					'root' => ':root',
					'name' => ':name',
					'description' => ':description',
					'categoryPath' => ':path'
				])
				->setParameters([
					'id' => $row['id'],
					'parent' => $row['parent_id'],
					'lft' => $row['lft'],
					'rgt' => $row['rgt'],
					'lvl' => $row['lvl'],
					'root' => $row['root'],
					'name' => $row['name'],
					'description' => $row['description'],
					'path' => $row['categoryPath']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importPartDistributor(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'partdistributor' : 'PartDistributor';
		$io->note('Importing PartDistributor');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(PartDistributor::class)->getTableName())
				->values([
					'id' => ':id',
					'part_id' => ':part_id',
					'distributor_id' => ':distributor_id',
					'orderNumber' => ':orderNumber',
					'packagingUnit' => ':packagingUnit',
					'price' => ':price',
					'currency' => ':currency',
					'sku' => ':sku',
					'ignoreForReports' => ':ignoreForReports'
				])
				->setParameters([
					'id' => $row['id'],
					'part_id' => $row['part_id'],
					'distributor_id' => $row['distributor_id'],
					'orderNumber' => $row['orderNumber'],
					'packagingUnit' => $row['packagingUnit'],
					'price' => $row['price'],
					'currency' => $row['currency'],
					'sku' => $row['sku'],
					'ignoreForReports' => $row['ignoreForReports']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importUser(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'partkeepruser' : 'PartKeeprUser';
		$io->note('Importing User');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$fos = $pk->executeQuery('SELECT * FROM ' . ($this->lowercase ? 'fosuser' : 'FOSUser') . ' WHERE username = :username', ['username' => $row['username']])->fetchAssociative();
			$qb->insert($this->entityManager->getClassMetadata(User::class)->getTableName())
				->values([
					'id' => ':id',
					'provider_id' => ':provider_id',
					'username' => ':username',
					'password' => ':password',
					'email' => ':email',
					'lastSeen' => ':lastSeen',
					'active' => ':active',
					'protected' => ':protected',
					'roles' => ':roles'
				])
				->setParameters([
					'id' => $row['id'],
					'provider_id' => $row['provider_id'] ?? $this->userService->getBuiltinProvider(),
					'username' => $row['username'],
					'password' => $row['password'],
					'email' => $row['email'],
					'lastSeen' => $row['lastSeen'],
					'active' => $row['active'],
					'protected' => $row['protected'],
					'roles' => Json::encode(unserialize($fos['roles'], ['allowed_classes' => false, 'max_depth' => 0]))
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importPartManufacturer(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'partmanufacturer' : 'PartManufacturer';
		$io->note('Importing PartManufacturer');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(PartManufacturer::class)->getTableName())
				->values([
					'id' => ':id',
					'part_id' => ':part_id',
					'manufacturer_id' => ':manufacturer_id',
					'partNumber' => ':partNumber'
				])
				->setParameters([
					'id' => $row['id'],
					'part_id' => $row['part_id'],
					'manufacturer_id' => $row['manufacturer_id'],
					'partNumber' => $row['partNumber']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importPartParameter(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'partparameter' : 'PartParameter';
		$io->note('Importing PartParameter');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(PartParameter::class)->getTableName())
				->values([
					'id' => ':id',
					'part_id' => ':part_id',
					'unit_id' => ':unit_id',
					'name' => ':name',
					'description' => ':description',
					'value' => ':value',
					'normalizedValue' => ':normalizedValue',
					'maximumValue' => ':maximumValue',
					'normalizedMaxValue' => ':normalizedMaxValue',
					'minimumValue' => ':minimumValue',
					'normalizedMinValue' => ':normalizedMinValue',
					'stringValue' => ':stringValue',
					'valueType' => ':valueType',
					'siPrefix_id' => ':siPrefix_id',
					'minSiPrefix_id' => ':minSiPrefix_id',
					'maxSiPrefix_id' => ':maxSiPrefix_id'
				])
				->setParameters([
					'id' => $row['id'],
					'part_id' => $row['part_id'],
					'unit_id' => $row['unit_id'],
					'name' => $row['name'],
					'description' => $row['description'],
					'value' => $row['value'],
					'normalizedValue' => $row['normalizedValue'],
					'maximumValue' => $row['maximumValue'],
					'normalizedMaxValue' => $row['normalizedMaxValue'],
					'minimumValue' => $row['minimumValue'],
					'normalizedMinValue' => $row['normalizedMinValue'],
					'stringValue' => $row['stringValue'],
					'valueType' => $row['valueType'],
					'siPrefix_id' => $row['siPrefix_id'],
					'minSiPrefix_id' => $row['minSiPrefix_id'],
					'maxSiPrefix_id' => $row['maxSiPrefix_id']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importPartUnit(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'partunit' : 'PartUnit';
		$io->note('Importing PartMeasurementUnit');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(PartMeasurementUnit::class)->getTableName())
				->values([
					'id' => ':id',
					'name' => ':name',
					'shortName' => ':short',
					'is_default' => ':is_default'
				])
				->setParameters([
					'id' => $row['id'],
					'name' => $row['name'],
					'short' => $row['shortName'],
					'is_default' => $row['is_default']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importProject(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'project' : 'Project';
		$io->note('Importing Project');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(Project::class)->getTableName())
				->values([
					'id' => ':id',
					'user_id' => ':user_id',
					'name' => ':name',
					'description' => ':description'
				])
				->setParameters([
					'id' => $row['id'],
					'user_id' => $row['user_id'],
					'name' => $row['name'],
					'description' => $row['description']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importProjectAttachment(OutputStyle $io, Connection $pk, string $dataDir): void
	{
		$pkTable = $this->lowercase ? 'projectattachment' : 'ProjectAttachment';
		$qb = new QueryBuilder($this->connect);
		$io->note('Importing ProjectAttachment');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			[$bytes, $sha] = $this->readBytesAndHash($dataDir . '/' . $row['filename'] . '.' . $row['extension']);
			$blobId = $this->ensureBlobForImport($bytes, $sha, $row['mimetype'], (int)$row['size']);
			$qb->insert($this->entityManager->getClassMetadata(ProjectAttachment::class)->getTableName())
				->values([
					'id' => ':id',
					'project_id' => ':project_id',
					'type' => ':type',
					'originalname' => ':originalname',
					'description' => ':description',
					'created' => ':created',
					'blob_id' => ':blob_id'
				])
				->setParameters([
					'id' => $row['id'],
					'project_id' => $row['project_id'],
					'type' => $row['type'],
					'originalname' => $row['originalname'],
					'description' => $row['description'],
					'created' => $row['created'],
					'blob_id' => $blobId
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importProjectPart(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'projectpart' : 'ProjectPart';
		$io->note('Importing ProjectPart');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(ProjectPart::class)->getTableName())
				->values([
					'id' => ':id',
					'part_id' => ':part_id',
					'project_id' => ':project_id',
					'quantity' => ':quantity',
					'remarks' => ':remarks',
					'overageType' => ':overageType',
					'overage' => ':overage',
					'lotNumber' => ':lotNumber'
				])
				->setParameters([
					'id' => $row['id'],
					'part_id' => $row['part_id'],
					'project_id' => $row['project_id'],
					'quantity' => $row['quantity'],
					'remarks' => $row['remarks'],
					'overageType' => $row['overageType'],
					'overage' => $row['overage'],
					'lotNumber' => $row['lotNumber']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importProjectRun(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'projectrun' : 'ProjectRun';
		$io->note('Importing ProjectRun');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(ProjectRun::class)->getTableName())
				->values([
					'id' => ':id',
					'project_id' => ':project_id',
					'runDateTime' => ':runDateTime',
					'quantity' => ':quantity'
				])
				->setParameters([
					'id' => $row['id'],
					'project_id' => $row['project_id'],
					'runDateTime' => $row['runDateTime'],
					'quantity' => $row['quantity']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importProjectRunPart(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'projectrunpart' : 'ProjectRunPart';
		$io->note('Importing ProjectRunPart');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(ProjectRunPart::class)->getTableName())
				->values([
					'id' => ':id',
					'part_id' => ':part_id',
					'quantity' => ':quantity',
					'lotNumber' => ':lotNumber',
					'projectRun_id' => ':projectRun_id'
				])
				->setParameters([
					'id' => $row['id'],
					'part_id' => $row['part_id'],
					'quantity' => $row['quantity'],
					'lotNumber' => $row['lotNumber'],
					'projectRun_id' => $row['projectRun_id']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importReport(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'report' : 'Report';
		$io->note('Importing Report');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(Report::class)->getTableName())
				->values([
					'id' => ':id',
					'name' => ':name',
					'createDateTime' => ':createDateTime'
				])
				->setParameters([
					'id' => $row['id'],
					'name' => $row['name'],
					'createDateTime' => $row['createDateTime']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importReportPart(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'reportpart' : 'ReportPart';
		$io->note('Importing ReportPart');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(ReportPart::class)->getTableName())
				->values([
					'id' => ':id',
					'report_id' => ':report_id',
					'part_id' => ':part_id',
					'distributor_id' => ':distributor_id',
					'quantity' => ':quantity'
				])
				->setParameters([
					'id' => $row['id'],
					'report_id' => $row['report_id'],
					'part_id' => $row['part_id'],
					'distributor_id' => $row['distributor_id'],
					'quantity' => $row['quantity']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importReportProject(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'reportproject' : 'ReportProject';
		$io->note('Importing ReportProject');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(ReportProject::class)->getTableName())
				->values([
					'id' => ':id',
					'report_id' => ':report_id',
					'project_id' => ':project_id',
					'quantity' => ':quantity'
				])
				->setParameters([
					'id' => $row['id'],
					'report_id' => $row['report_id'],
					'project_id' => $row['project_id'],
					'quantity' => $row['quantity']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importSiPrefix(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'siprefix' : 'SiPrefix';
		$io->note('Importing SiPrefix');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(SiPrefix::class)->getTableName())
				->values([
					'id' => ':id',
					'prefix' => ':prefix',
					'symbol' => ':symbol',
					'exponent' => ':exponent',
					'base' => ':base'
				])
				->setParameters([
					'id' => $row['id'],
					'prefix' => $row['prefix'],
					'symbol' => $row['symbol'],
					'exponent' => $row['exponent'],
					'base' => $row['base']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importStatisticSnapshot(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'statisticsnapshot' : 'StatisticSnapshot';
		$io->note('Importing StatisticSnapshot');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(StatisticSnapshot::class)->getTableName())
				->values([
					'id' => ':id',
					'dateTime' => ':dateTime',
					'parts' => ':parts',
					'categories' => ':categories'
				])
				->setParameters([
					'id' => $row['id'],
					'dateTime' => $row['dateTime'],
					'parts' => $row['parts'],
					'categories' => $row['categories']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importStatisticSnapshotUnit(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'statisticsnapshotunit' : 'StatisticSnapshotUnit';
		$io->note('Importing StatisticSnapshotUnit');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(StatisticSnapshotUnit::class)->getTableName())
				->values([
					'id' => ':id',
					'stockLevel' => ':stockLevel',
					'statisticSnapshot_id' => ':statisticSnapshot_id',
					'partUnit_id' => ':partUnit_id'
				])
				->setParameters([
					'id' => $row['id'],
					'stockLevel' => $row['stockLevel'],
					'statisticSnapshot_id' => $row['statisticSnapshot_id'],
					'partUnit_id' => $row['partUnit_id']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importStockEntry(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'stockentry' : 'StockEntry';
		$io->note('Importing StockEntry');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(StockEntry::class)->getTableName())
				->values([
					'id' => ':id',
					'part_id' => ':part_id',
					'user_id' => ':user_id',
					'stockLevel' => ':stockLevel',
					'price' => ':price',
					'dateTime' => ':dateTime',
					'correction' => ':correction',
					'comment' => ':comment'
				])
				->setParameters([
					'id' => $row['id'],
					'part_id' => $row['part_id'],
					'user_id' => $row['user_id'],
					'stockLevel' => $row['stockLevel'],
					'price' => $row['price'],
					'dateTime' => $row['dateTime'],
					'correction' => $row['correction'],
					'comment' => $row['comment']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importStorageLocation(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'storagelocation' : 'StorageLocation';
		$io->note('Importing StorageLocation');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(StorageLocation::class)->getTableName())
				->values([
					'id' => ':id',
					'category_id' => ':category_id',
					'name' => ':name'
				])
				->setParameters([
					'id' => $row['id'],
					'category_id' => $row['category_id'],
					'name' => $row['name']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importStorageLocationCategory(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'storagelocationcategory' : 'StorageLocationCategory';
		$io->note('Importing StorageLocationCategory');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable ORDER BY parent_id")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(StorageLocationCategory::class)->getTableName())
				->values([
					'id' => ':id',
					'parent_id' => ':parent',
					'lft' => ':lft',
					'rgt' => ':rgt',
					'lvl' => ':lvl',
					'root' => ':root',
					'name' => ':name',
					'description' => ':description',
					'categoryPath' => ':path'
				])
				->setParameters([
					'id' => $row['id'],
					'parent' => $row['parent_id'],
					'lft' => $row['lft'],
					'rgt' => $row['rgt'],
					'lvl' => $row['lvl'],
					'root' => $row['root'],
					'name' => $row['name'],
					'description' => $row['description'],
					'path' => $row['categoryPath']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importStorageLocationImage(OutputStyle $io, Connection $pk, string $dataDir): void
	{
		$pkTable = $this->lowercase ? 'storagelocationimage' : 'StorageLocationImage';
		$qb = new QueryBuilder($this->connect);
		$io->note('Importing StorageLocationImage');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			[$bytes, $sha] = $this->readBytesAndHash($dataDir . '/' . $row['filename'] . '.' . $row['extension']);
			$blobId = $this->ensureBlobForImport($bytes, $sha, $row['mimetype'], (int)$row['size']);
			$qb->insert($this->entityManager->getClassMetadata(StorageLocationImage::class)->getTableName())
				->values([
					'id' => ':id',
					'storageLocation_id' => ':storageLocation_id',
					'type' => ':type',
					'originalname' => ':originalname',
					'description' => ':description',
					'created' => ':created',
					'blob_id' => ':blob_id'
				])
				->setParameters([
					'id' => $row['id'],
					'storageLocation_id' => $row['storageLocation_id'],
					'type' => $row['type'],
					'originalname' => $row['originalname'],
					'description' => $row['description'],
					'created' => $row['created'],
					'blob_id' => $blobId
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importSystemNotice(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'systemnotice' : 'SystemNotice';
		$io->note('Importing SystemNotice');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(SystemNotice::class)->getTableName())
				->values([
					'id' => ':id',
					'date' => ':date',
					'title' => ':title',
					'description' => ':description',
					'acknowledged' => ':acknowledged',
					'type' => ':type'
				])
				->setParameters([
					'id' => $row['id'],
					'date' => $row['date'],
					'title' => $row['title'],
					'description' => $row['description'],
					'acknowledged' => $row['acknowledged'],
					'type' => $row['type']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importSystemPreference(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'systempreference' : 'SystemPreference';
		$io->note('Importing SystemPreference');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(*) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(SystemPreference::class)->getTableName())
				->values([
					'preferenceKey' => ':preferenceKey',
					'preferenceValue' => ':preferenceValue'
				])
				->setParameters([
					'preferenceKey' => $row['preferenceKey'],
					'preferenceValue' => $row['preferenceValue']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importTipOfTheDay(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'tipoftheday' : 'TipOfTheDay';
		$io->note('Importing TipOfTheDay');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(TipOfTheDay::class)->getTableName())
				->values([
					'id' => ':id',
					'name' => ':name'
				])
				->setParameters([
					'id' => $row['id'],
					'name' => $row['name']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importTipOfTheDayHistory(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'tipofthedayhistory' : 'TipOfTheDayHistory';
		$io->note('Importing TipOfTheDayHistory');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(TipOfTheDayHistory::class)->getTableName())
				->values([
					'id' => ':id',
					'user_id' => ':user_id',
					'name' => ':name'
				])
				->setParameters([
					'id' => $row['id'],
					'user_id' => $row['user_id'],
					'name' => $row['name']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importUnit(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'unit' : 'Unit';
		$io->note('Importing Unit');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(Unit::class)->getTableName())
				->values([
					'id' => ':id',
					'name' => ':name',
					'symbol' => ':symbol'
				])
				->setParameters([
					'id' => $row['id'],
					'name' => $row['name'],
					'symbol' => $row['symbol']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importUnitSiPrefixes(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'unitsiprefixes' : 'UnitSiPrefixes';
		$io->note('Importing UnitSiPrefixes');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(*) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(Unit::class)->getAssociationMapping('prefixes')['joinTable']['name'])
				->values([
					'unit_id' => ':unit_id',
					'siprefix_id' => ':siprefix_id'
				])
				->setParameters([
					'unit_id' => $row['unit_id'],
					'siprefix_id' => $row['siprefix_id']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importUserPreference(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'userpreference' : 'UserPreference';
		$io->note('Importing UserPreference');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(*) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(UserPreference::class)->getTableName())
				->values([
					'user_id' => ':user_id',
					'preferenceKey' => ':preferenceKey',
					'preferenceValue' => ':preferenceValue'
				])
				->setParameters([
					'user_id' => $row['user_id'],
					'preferenceKey' => $row['preferenceKey'],
					'preferenceValue' => $row['preferenceValue']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}

	private function importUserProvider(OutputStyle $io, Connection $pk): void
	{
		$qb = new QueryBuilder($this->connect);
		$pkTable = $this->lowercase ? 'userprovider' : 'UserProvider';
		$io->note('Importing UserProvider');
		$bar = $io->createProgressBar($pk->executeQuery("SELECT COUNT(id) FROM $pkTable")->fetchOne());
		$bar->start();
		$this->connect->beginTransaction();
		foreach ($pk->executeQuery("SELECT * FROM $pkTable")->fetchAllAssociative() as $row) {
			$qb->insert($this->entityManager->getClassMetadata(UserProvider::class)->getTableName())
				->values([
					'id' => ':id',
					'type' => ':type',
					'editable' => ':editable'
				])
				->setParameters([
					'id' => $row['id'],
					'type' => $row['type'],
					'editable' => $row['editable']
				])
				->executeStatement();
			$bar->advance();
		}
		$this->connect->commit();
		$bar->finish();
		$bar->clear();
	}
}
