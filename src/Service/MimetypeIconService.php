<?php

namespace Limas\Service;

use Symfony\Component\Config\FileLocator;


readonly class MimetypeIconService
{
	public function __construct(private array $limas)
	{
	}

	public function getMimetypeIcon(string $mimetype): array|string
	{
		$file = str_replace('/', '-', $mimetype) . '.svg';

		$iconDirectory = $this->limas['directories']['mimetype_icons'];

		$fileLocator = new FileLocator($iconDirectory);

		try {
			$iconFile = $fileLocator->locate($file);
		} catch (\InvalidArgumentException $e) {
			$file = 'empty.svg';
			$iconFile = $fileLocator->locate($file);
		}

		return $iconFile;
	}
}
