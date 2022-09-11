<?php

namespace Limas\Service;

use Composer\InstalledVersions;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use GuzzleHttp\Client;
use Limas\Entity;
use Limas\Object\OperatingSystem;
use Limas\Object\SystemInformationRecord;


class SystemService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly VersionService         $versionService,
		private readonly CronLoggerService      $cronLoggerService,
		private readonly array                  $limas
	)
	{
	}

	/**
	 * Returns a list of system information records
	 *
	 * Please note that it is not defined which information is returned; the result
	 * should be seen as "informational" to the system operator, not for automated purposes
	 *
	 * @return SystemInformationRecord[] An array of SystemInformationRecords
	 */
	public function getSystemInformation(): array
	{
		$aData = [
			new SystemInformationRecord('Doctrine ORM', InstalledVersions::getVersion('doctrine/orm'), 'Libraries'),
			new SystemInformationRecord('Doctrine DBAL', InstalledVersions::getVersion('doctrine/dbal'), 'Libraries'),

			new SystemInformationRecord('PHP Version', PHP_VERSION, 'System')
		];

		$os = new OperatingSystem;

		$aData[] = new SystemInformationRecord('Operating System Type', $os->getPlatform(), 'System');
		$aData[] = new SystemInformationRecord('Operating System Release', $os->getRelease(), 'System');
		$aData[] = new SystemInformationRecord('Load Average (1, 5, 15 minutes)', $this->getServerLoadAvg(), 'System');

		$aData[] = new SystemInformationRecord('memory_limit', ini_get('memory_limit'), 'PHP');
		$aData[] = new SystemInformationRecord('post_max_size', ini_get('post_max_size'), 'PHP');
		$aData[] = new SystemInformationRecord('upload_max_filesize', ini_get('upload_max_filesize'), 'PHP');
		$aData[] = new SystemInformationRecord('allow_url_fopen', ini_get('allow_url_fopen'), 'PHP');
		$aData[] = new SystemInformationRecord('max_execution_time', ini_get('max_execution_time'), 'PHP');

		$aData[] = new SystemInformationRecord('Query Cache Implementation', get_class($this->entityManager->getConfiguration()->getQueryCache()), 'PHP');
		$aData[] = new SystemInformationRecord('Metadata Cache Implementation', get_class($this->entityManager->getConfiguration()->getMetadataCache()), 'PHP');

		$aData[] = new SystemInformationRecord(
			'Disk Space (Total)',
			$this->format_bytes($this->getTotalDiskSpace()),
			'Limas'
		);

		$aData[] = new SystemInformationRecord(
			'Disk Space (Free)',
			$this->format_bytes($this->getFreeDiskSpace()),
			'Limas'
		);

		$aData[] = new SystemInformationRecord(
			'Disk Space (Used)',
			$this->format_bytes($this->getUsedDiskSpace()),
			'Limas'
		);

		$aData[] = new SystemInformationRecord(
			'Data Directory',
			realpath($this->limas['filesystem']['data_directory']),
			'Limas'
		);

		$aData[] = new SystemInformationRecord(
			'Limas Version',
			$this->versionService->getCanonicalVersion(),
			'Limas'
		);

		return $aData;
	}

	/**
	 * Returns the database schema status
	 *
	 * This method is usuall called once the user logs in, and alerts him if the schema is not up-to-date
	 *
	 * Returns either status incomplete if the schema is not up-to-date, or complete if everything is OK
	 */
	public function getSystemStatus(): array
	{
		$inactiveCronjobs = $this->limas['cronjob']['check']
			? $this->cronLoggerService->getInactiveCronjobs($this->limas['required_cronjobs'])
			: []; // Skip cronjob tests

		return [
			'inactiveCronjobCount' => count($inactiveCronjobs),
			'inactiveCronjobs' => $inactiveCronjobs,
			'schemaStatus' => $this->getSchemaStatus(),
			'schemaQueries' => $this->getSchemaQueries(),
		];
	}

	/**
	 * Checks if the schema is up-to-date. If yes, it returns "complete", if not, it returns "incomplete".
	 */
	protected function getSchemaStatus(): string
	{
		return count($this->getSchemaQueries()) > 0
			? 'incomplete'
			: 'complete';
	}

	/**
	 * Returns all queries to be executed for a proper database update
	 */
	protected function getSchemaQueries(): array
	{
		$metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();
		return (new SchemaTool($this->entityManager))->getUpdateSchemaSql($metadatas, true);
	}

	/**
	 * Returns the available disk space for the configured data_dir
	 */
	public function getFreeDiskSpace(): float
	{
		if ($this->limas['filesystem']['quota'] === false) {
			return disk_free_space($this->limas['filesystem']['data_directory']);
		}
		return $this->getTotalDiskSpace() - $this->getUsedDiskSpace();
	}

	public function getTotalDiskSpace(): mixed
	{
		if ($this->limas['filesystem']['quota'] === false) {
			return disk_total_space($this->limas['filesystem']['data_directory']);
		}
		return $this->limas['filesystem']['quota'];
	}

	/**
	 * Returns the used disk space occupied by attachments etc.
	 *
	 * Does not count temporary files
	 */
	public function getUsedDiskSpace(): int
	{
		if ($this->limas['filesystem']['quota'] === false) {
			return $this->getTotalDiskSpace() - $this->getFreeDiskSpace();
		}

		$fileEntities = [
			Entity\FootprintAttachment::class,
			Entity\FootprintImage::class,
			Entity\ManufacturerICLogo::class,
			Entity\PartAttachment::class,
			Entity\ProjectAttachment::class,
			Entity\StorageLocationImage::class
		];
		$size = 0;
		foreach ($fileEntities as $fileEntity) {
			$qb = $this->entityManager->createQueryBuilder();
			$qb->select('SUM(a.size)')->from($fileEntity, 'a');
			$size += $qb->getQuery()->getSingleScalarResult();
		}

		return $size;
	}

	protected function is_valid_value($number): bool
	{
		return is_numeric($number);
	}

	/**
	 * Filter for converting bytes to a human-readable format, as Unix command "ls -h" does
	 *
	 * @param string|int $number A string or integer number value to format
	 * @param bool $base2conversion Defines if the conversion has to be strictly performed as binary values or
	 *                                    by using a decimal conversion such as 1 KByte = 1000 Bytes
	 * @return string The number converted to human readable representation
	 */
	public function format_bytes(string|int $number, bool $base2conversion = true): string
	{
		if (!$this->is_valid_value($number)) {
			return '';
		}
		$unit = $base2conversion ? 1024 : 1000;
		if ($number < $unit) {
			return $number . ' B';
		}
		$exp = (int)(log($number) / log($unit));
		$pre = $base2conversion ? 'kMGTPE' : 'KMGTPE';
		$pre = $pre[$exp - 1] . ($base2conversion ? '' : 'i');

		return sprintf('%.1f %sB', $number / pow($unit, $exp), $pre);
	}

	/**
	 * Returns the effective size from a human-readable byte format
	 *
	 * @example getBytesFromHumanReadable("1M") will return 1048576
	 */
	public function getBytesFromHumanReadable(string $size_str): int
	{
		return match (substr($size_str, -1)) {
			'K', 'k' => ((int)$size_str) << 10,
			'M', 'm' => ((int)$size_str) << 20,
			'G', 'g' => ((int)$size_str) << 30,
			default => $size_str,
		};
	}

	public function getServerLoadAvg(): string
	{
		if (false === ($load = sys_getloadavg())) {
			return '???';
		}

		return sprintf('%.1f %.1f %.1f', $load[0], $load[1], $load[2]);
	}
}
