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
	private string $versionURI = 'http://www.partkeepr.org/versions.json';


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
	 * Checks against the versions at partkeepr.org
	 *
	 * If a newer version was found, create a system notice entry
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
	 * Returns the latest version information from partkeepr.org
	 */
	public function getLatestVersion(): array|false
	{
		$versions = json_decode(
			is_file($this->versionURI)
				? file_get_contents($this->versionURI)
				: (new Client)->request('GET', $this->versionURI)->getBody()
			, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($versions)) {
			return false;
		}

		$latestVersionEntry = $versions[0];

		if (!array_key_exists('version', $latestVersionEntry)) {
			return false;
		}

		if (!array_key_exists('changelog', $latestVersionEntry)) {
			return [
				'version' => $latestVersionEntry['version'],
				'changelog' => ''
			];
		}
		return [
			'version' => $latestVersionEntry['version'],
			'changelog' => $latestVersionEntry['changelog']
		];
	}
}
