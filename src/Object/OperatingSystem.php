<?php

namespace Limas\Object;


class OperatingSystem
{
	/**
	 * Returns the platform name the system is running on
	 *
	 * Typical return values are: "Linux", "FreeBSD", "Darwin" (Mac OSX), "Windows"
	 */
	public function getPlatform(): string
	{
		if (function_exists('posix_uname')) {
			$data = posix_uname();
			if (array_key_exists('sysname', $data)) {
				return $data['sysname'];
			}
		}

		if (\PHP_OS === 'WINNT') {
			return 'Windows';
		}

		return 'unknown';
	}

	/**
	 * Returns the distribution
	 */
	public function getRelease(): string
	{
		switch (strtolower($this->getPlatform())) {
			case 'freebsd':
				/*
				 * Unfortunately, there's no text file on FreeBSD which tells us the release
				 * number. Thus, we hope that "release" within posix_uname() is defined.
				 */
				if (function_exists('posix_uname')) {
					$data = posix_uname();
					if (array_key_exists('release', $data)) {
						return $data['release'];
					}
				}
				break;
			case 'darwin':
				// Mac stores its version number in a public readable plist file, which is in XML format
				$document = new \DOMDocument;
				$document->load('/System/Library/CoreServices/SystemVersion.plist');
				$xpath = new \DOMXPath($document);
				$entries = $xpath->query('/plist/dict/*');

				$previous = '';
				foreach ($entries as $entry) {
					if (str_contains($previous, 'ProductVersion')) {
						return $entry->textContent;
					}
					$previous = $entry->textContent;
				}
				break;
			case 'linux':
				return $this->getLinuxDistribution();
			default:
				break;
		}

		return 'unknown';
	}

	/**
	 * Tries to detect the distribution
	 *
	 * Currently, we only execute lsb_release to find out the version number.
	 * As I don't have any other distributions at hand to test with, I rely
	 * on user feedback which distributions don't have lsb_release.
	 */
	public function getLinuxDistribution(): string
	{
		// Try executing lsb_release
		/* @phpstan-ignore-next-line */
		$release = @exec('lsb_release -d -s', $void, $retval);

		if ($retval === 0 && $release !== '') {
			return $release;
		}

		//@todo we need better handling here
		return 'unknown';
	}
}
