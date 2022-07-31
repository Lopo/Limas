<?php

namespace Limas\Exceptions;


class RootMayNotBeDeletedException
	extends \Exception
{
	public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
	{
		parent::__construct('The root node may not be deleted', $code, $previous);
	}
}
