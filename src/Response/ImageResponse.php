<?php

namespace Limas\Response;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Symfony\Component\HttpFoundation\Response;


class ImageResponse
	extends Response
{
	public function __construct(int $maxWidth, int $maxHeight, int $code, string $message)
	{
		if (0 === $maxWidth) {
			$maxWidth = 300;
		}
		if (0 === $maxHeight) {
			$maxHeight = 300;
		}

		$imagine = new Imagine;
		$image = $imagine->create(new Box(300, 300));
		$image->draw()->text($message, $imagine->font(realpath(__DIR__ . '/../../public/fonts/OpenSans-Regular.ttf'), 24, $image->palette()->color('000')), new Point(0, 0)); // @todo

		$box = $image->getSize();
		$box = $box->widen($maxWidth);
		if ($box->getHeight() > $maxHeight) {
			$box = $box->heighten($maxHeight);
		}
		$image->resize($box);

		parent::__construct($image->get('png'), $code, ['Content-Type' => 'image/png']);
	}
}
