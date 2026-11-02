<?php

namespace Limas\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;


/**
 * One-shot full-install backup — DB dump + user-uploaded files + secrets,
 * packaged into a single timestamped `.tar.gz` written under the configured
 * directory
 *
 * Restore path is a manual untar + `mysql < backup/db.sql` + put the files
 * back where they were; we don't ship an automated restore command because
 * production restores are usually mid-incident operations that benefit from
 * a human inspecting the tarball first.
 *
 * Remote upload (S3 / SFTP) is intentionally out of scope here — the
 * operator is expected to point LIMAS_BACKUP_DIR at a network-mounted
 * share (SMB/NFS) if they want off-host storage.
 */
final readonly class BackupService
{
	public function __construct(
		private Connection      $connection,
		private KernelInterface $kernel,
		private string          $backupDirectory,
		private int             $retentionCount,
		private string          $mysqldumpPath,
		private string          $dataDirectory
	)
	{
	}

	/**
	 * Create one backup archive, run retention pruning, return the path of
	 * the created file. Throws on dump / write failures so the caller
	 * (BackupMessageHandler / CLI command) can surface a SystemNotice.
	 */
	public function create(): string
	{
		$fs = new Filesystem;
		$fs->mkdir($this->backupDirectory);

		$stamp = (new \DateTimeImmutable)->format('Ymd-His');
		$archivePath = sprintf('%s/limas-%s.tar', $this->backupDirectory, $stamp);
		$workDir = sprintf('%s/limas-%s.staging', sys_get_temp_dir(), $stamp);
		// Staging holds a plaintext DB dump + .env.local secrets — keep it
		// private to the backup user while it exists
		$fs->mkdir($workDir, 0700);

		try {
			// Archive shape mirrors the project root: db.sql, data/...,
			// config/..., .env.local. Restore = untar into the project dir
			// and load db.sql into MySQL — no path remapping required.
			$this->dumpDatabase($workDir . '/db.sql');
			$this->copyConfig($workDir);
			$this->copyAttachments($workDir . '/data');
			$this->buildArchive($archivePath, $workDir);
		} catch (\Throwable $e) {
			// Never leave a half-written archive behind: a truncated .tar.gz
			// would be counted as a valid backup by prune(), and a stray .tar
			// is never pruned at all
			$fs->remove([$archivePath, $archivePath . '.gz', $archivePath . '.gz.part']);
			throw $e;
		} finally {
			// Always clear the staging dir, even if any of the above
			// threw — we don't want orphan plaintext SQL + secrets lying around in /tmp
			$fs->remove($workDir);
		}

		$this->prune();
		return $archivePath . '.gz';
	}

	private function dumpDatabase(string $target): void
	{
		$params = $this->connection->getParams();
		$args = [
			$this->mysqldumpPath,
			'--single-transaction',
			'--quick',
			'--no-tablespaces',
			'--skip-lock-tables',
			'--default-character-set=utf8mb4',
			sprintf('--host=%s', $params['host'] ?? 'localhost'),
			sprintf('--port=%d', $params['port'] ?? 3306),
			sprintf('--user=%s', $params['user'] ?? 'root')
		];
		if (isset($params['password']) && $params['password'] !== '') {
			$args[] = sprintf('--password=%s', $params['password']);
		}
		$args[] = $params['dbname'] ?? 'limas';

		// Redirect stdout into the target file. `Process` lacks a built-in
		// "send stdout to file" toggle so we stream the iterator output
		// into the file ourselves.
		$process = new Process($args);
		$process->setTimeout(3600);
		$handle = fopen($target, 'wb');
		if ($handle === false) {
			throw new \RuntimeException(sprintf('Cannot open %s for writing', $target));
		}
		try {
			$process->run(function (string $type, string $buffer) use ($handle): void {
				if ($type === Process::OUT) {
					fwrite($handle, $buffer);
				}
			});
		} finally {
			fclose($handle);
		}
		if (!$process->isSuccessful()) {
			throw new \RuntimeException('mysqldump failed: ' . $process->getErrorOutput());
		}
	}

	private function copyConfig(string $workDir): void
	{
		$fs = new Filesystem;
		$projectDir = $this->kernel->getProjectDir();

		// Whole config/ tree — most of it is in git, but per-deployment
		// edits (limas.yaml schedule, services.yaml binds, packages/*
		// tweaks) live alongside the secrets so we ship the lot. Cost is
		// trivial; a curated allowlist is just one missing-file away from
		// a "I lost my LDAP config" restore surprise.
		if (is_dir($projectDir . '/config')) {
			$fs->mirror($projectDir . '/config', $workDir . '/config');
		}
		// .env.local lives at project root, not under config/.
		if (is_file($projectDir . '/.env.local')) {
			$fs->copy($projectDir . '/.env.local', $workDir . '/.env.local');
		}
	}

	private function copyAttachments(string $targetDir): void
	{
		// `data/files/` after the CAS refactor — sha256-keyed blob tree.
		// Tarball includes the whole `data/` so future-added subdirs
		// (image cache, fixtures imported into data/) come along too.
		if (!is_dir($this->dataDirectory)) {
			return;
		}
		(new Filesystem)->mirror($this->dataDirectory, $targetDir);
	}

	private function buildArchive(string $archivePath, string $workDir): void
	{
		// PharData = PHP-native tar; no `tar` binary required.
		// Two-step "build .tar, then .compress to .tar.gz" mirrors PharData's own API.
		$tar = new \PharData($archivePath);
		$tar->buildFromDirectory($workDir);
		unset($tar);

		// Stream-gzip the tar instead of PharData::compress(), which loads the
		// whole archive into memory: once a backup outgrew memory_limit the
		// worker died with a fatal error every night, leaving an empty .tar.gz,
		// the raw .tar and the plaintext staging dir behind
		$this->gzipFile($archivePath, $archivePath . '.gz');
		(new Filesystem)->remove($archivePath);
	}

	/**
	 * Writes under a temporary `.part` name and renames only once complete, so
	 * a process killed mid-write never leaves a truncated file named like a
	 * valid backup (prune() would count it)
	 */
	private function gzipFile(string $source, string $target): void
	{
		$partial = $target . '.part';
		$in = fopen($source, 'rb');
		if ($in === false) {
			throw new \RuntimeException(sprintf('Cannot open %s for reading', $source));
		}
		$out = fopen('compress.zlib://' . $partial, 'wb');
		if ($out === false) {
			fclose($in);
			throw new \RuntimeException(sprintf('Cannot open %s for writing', $partial));
		}
		try {
			$copied = stream_copy_to_stream($in, $out);
		} finally {
			fclose($in);
			fclose($out);
		}
		clearstatcache(true, $source);
		if ($copied === false || $copied !== filesize($source)) {
			throw new \RuntimeException(sprintf('Compressing %s failed', $source));
		}
		if (!rename($partial, $target)) {
			throw new \RuntimeException(sprintf('Cannot move %s to %s', $partial, $target));
		}
	}

	/**
	 * Keep the N newest archives, drop the rest. Sorted by filename which
	 * doubles as timestamp (Ymd-His) — newest lexicographically last
	 */
	private function prune(): void
	{
		// A leftover .part can only come from a run that was killed outright
		// (no catch/finally); this run's own file was already renamed
		$stale = glob($this->backupDirectory . '/limas-*.tar.gz.part');
		foreach ($stale === false ? [] : $stale as $file) {
			@unlink($file);
		}
		if ($this->retentionCount <= 0) {
			return;
		}
		$pattern = $this->backupDirectory . '/limas-*.tar.gz';
		$matches = glob($pattern);
		if ($matches === false || $matches === []) {
			return;
		}
		sort($matches);  // oldest first
		$drop = max(0, count($matches) - $this->retentionCount);
		for ($i = 0; $i < $drop; $i++) {
			@unlink($matches[$i]);
		}
	}
}
