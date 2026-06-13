<?php

namespace Limas\Service;

use Gitonomy\Git\Repository;
use GuzzleHttp\Client;
use Limas\LimasVersion;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


class VersionService
{
	private string $version = LimasVersion::LIMAS_VERSION;
	// GitHub Releases API. Single-object response (the `latest` release),
	// rate-limited to 60 unauthenticated requests/hour per IP — plenty for
	// a per-startup check from one Limas instance. User-Agent header is
	// mandatory for github.com/api/v3.
	private string $versionURI = 'https://api.github.com/repos/Lopo/Limas/releases/latest';


	public function __construct(
		private readonly SystemNoticeService $systemNoticeService,
		private readonly TranslatorInterface $translator,
		private readonly KernelInterface     $kernel
	)
	{
	}

	/**
	 * Extracts the current commit from GIT
	 */
	public function extractGITCommit(): string
	{
		if (!is_dir(($path = $this->kernel->getProjectDir()) . '/.git')) {
			return '';
		}
		return (new Repository($path))->getHeadCommit()?->getHash();
	}

	/**
	 * Extracts the current short commit from GIT
	 */
	public function extractShortGITCommit(): string
	{
		if (!is_dir(($path = $this->kernel->getProjectDir()) . '/.git')) {
			return '';
		}
		return (new Repository($path))->getHeadCommit()?->getFixedShortHash();
	}

	/**
	 * Sets the version string
	 */
	public function setVersion(string $version): self
	{
		$this->version = $version;
		return $this;
	}

	/**
	 * Returns the current version string
	 */
	public function getVersion(): string
	{
		return $this->version;
	}

	public function setVersionURI(string $versionURI): self
	{
		$this->versionURI = $versionURI;
		return $this;
	}

	public function getCanonicalVersion(): string
	{
		if ($this->getVersion() === '{V_GIT}') {
			return 'GIT development version Commit ' . $this->extractGITCommit() . ' Short Commit ' . $this->extractShortGITCommit();
		}
		return $this->getVersion();
	}

	/**
	 * Checks against the latest GitHub release.
	 *
	 * If a newer version was found, create a system notice entry.
	 * Network errors / API hiccups never propagate — version check is a
	 * niceness, not a critical path.
	 */
	public function doVersionCheck(): void
	{
		if ($this->getVersion() === '{V_GIT}') {
			return;
		}

		if (str_starts_with($this->getVersion(), 'limas-nightly')) {
			return;
		}

		$latestVersion = $this->getLatestVersion();

		if ($latestVersion === false) {
			return;
		}

		if (version_compare($this->getVersion(), $latestVersion['version'], '<')) {
			$this->systemNoticeService->createUniqueSystemNotice(
				'LIMAS_VERSION_' . $latestVersion['version'],
				$this->translator->trans('New Limas Version %version% available',
					['%version%' => $latestVersion['version']]
				),
				$this->translator->trans('Limas Version %version% changelog:',
					['%version%' => $latestVersion['version'] . "\n\n" . $latestVersion['changelog']]
				)
			);
		}
	}

	/**
	 * Fetches the latest GitHub release and projects it onto the
	 * {version, changelog} shape the version-check loop expects.
	 *
	 * Accepts either:
	 *  - the canonical GitHub Releases API endpoint (single-object response)
	 *  - a local file path (test fixtures override `versionURI` to a
	 *    bundled versions.json — kept for back-compat with existing tests)
	 *
	 * Returns `false` on every kind of failure (network, JSON parse, missing
	 * `tag_name`, file missing) so the caller can no-op cleanly.
	 */
	public function getLatestVersion(): array|false
	{
		try {
			$body = is_file($this->versionURI)
				? file_get_contents($this->versionURI)
				: (new Client)->request('GET', $this->versionURI, [
					'headers' => [
						// GitHub API mandates User-Agent on every call.
						'User-Agent' => 'Limas-VersionCheck',
						'Accept' => 'application/vnd.github+json',
					],
					'connect_timeout' => 3,
					'timeout' => 5,
				])->getBody();
			$payload = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
		} catch (\Throwable) {
			// Network down, GitHub rate-limited, JSON malformed, file
			// missing — every failure short-circuits to "we just don't
			// know what's latest right now".
			return false;
		}

		// Legacy versions.json shape (test fixtures): list of release
		// objects, newest first. Keep the [0]-of-list compatibility so
		// existing tests don't need adjusting.
		if (is_array($payload) && array_is_list($payload)) {
			$payload = $payload[0] ?? null;
		}
		if (!is_array($payload)) {
			return false;
		}

		// GitHub shape: `tag_name`, `body`. Strip a leading `v` so
		// `version_compare('2.0.0', 'v2.0.0', '<')` doesn't false-positive.
		// Legacy shape: `version`, `changelog`.
		$tag = $payload['tag_name'] ?? $payload['version'] ?? null;
		if (!is_string($tag) || $tag === '') {
			return false;
		}
		$version = ltrim($tag, 'vV');
		$changelog = $payload['body'] ?? $payload['changelog'] ?? '';

		return [
			'version' => $version,
			'changelog' => is_string($changelog) ? $changelog : '',
		];
	}
}
