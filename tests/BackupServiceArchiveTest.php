<?php

namespace Limas\Tests;

use Doctrine\DBAL\Connection;
use Limas\Service\BackupService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Filesystem\Filesystem;


/**
 * Regression guard: PharData::compress() loaded the whole tar into memory, so
 * once a backup outgrew memory_limit the nightly worker died with a fatal
 * error (empty .tar.gz, raw .tar and plaintext staging dir left behind).
 * The archive must now be built in roughly constant memory.
 */
class BackupServiceArchiveTest
	extends TestCase
{
	private string $dir;


	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir() . '/limas-backup-test-' . bin2hex(random_bytes(4));
		mkdir($this->dir . '/work', 0700, true);
	}

	protected function tearDown(): void
	{
		(new Filesystem)->remove($this->dir);
	}

	public function testArchiveLargerThanMemoryLimitIsBuiltAndValid(): void
	{
		// 64 MB of incompressible data — far above the headroom we allow below
		$size = 64 * 1024 * 1024;
		$h = fopen($this->dir . '/work/blob.bin', 'wb');
		for ($i = 0; $i < 64; $i++) {
			fwrite($h, random_bytes(1024 * 1024));
		}
		fclose($h);
		file_put_contents($this->dir . '/work/db.sql', 'SELECT 1;');

		$service = (new \ReflectionClass(BackupService::class))->newInstanceWithoutConstructor();
		$build = new \ReflectionMethod($service, 'buildArchive');
		$archive = $this->dir . '/limas-test.tar';

		$previous = ini_get('memory_limit');
		ini_set('memory_limit', (string)(memory_get_usage(true) + 32 * 1024 * 1024));
		try {
			$build->invoke($service, $archive, $this->dir . '/work');
		} finally {
			ini_set('memory_limit', $previous);
		}

		self::assertFileDoesNotExist($archive, 'uncompressed .tar must be removed');
		self::assertFileDoesNotExist($archive . '.gz.part', 'temporary .part must be renamed away');
		self::assertFileExists($archive . '.gz');
		$tar = new \PharData($archive . '.gz');
		self::assertSame($size, $tar['blob.bin']->getSize());
		self::assertSame('SELECT 1;', $tar['db.sql']->getContent());
	}

	/**
	 * A .part left by a run that was killed outright must be cleaned up and
	 * never counted as one of the retained backups
	 */
	public function testPruneDropsStalePartialAndKeepsNewest(): void
	{
		foreach (['20260101', '20260102', '20260103'] as $day) {
			touch(sprintf('%s/limas-%s-020000.tar.gz', $this->dir, $day));
		}
		touch($this->dir . '/limas-20260104-020000.tar.gz.part');

		$service = new BackupService(
			$this->createStub(Connection::class),
			$this->createStub(KernelInterface::class),
			$this->dir,
			2,
			'mysqldump',
			$this->dir . '/work'
		);
		(new \ReflectionMethod($service, 'prune'))->invoke($service);

		$left = array_map('basename', glob($this->dir . '/limas-*') ?: []);
		sort($left);
		self::assertSame(['limas-20260102-020000.tar.gz', 'limas-20260103-020000.tar.gz'], $left);
	}
}
