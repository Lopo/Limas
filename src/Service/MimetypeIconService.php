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

		$fileLocator = new FileLocator($this->limas['directories']['mimetype_icons']);

		try {
			$iconFile = $fileLocator->locate($file);
		} catch (\InvalidArgumentException $e) {
			$iconFile = $fileLocator->locate('empty.svg');
		}

		return $iconFile;
	}
}
