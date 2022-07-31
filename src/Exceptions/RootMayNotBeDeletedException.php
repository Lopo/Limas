<?php

namespace Limas\Exceptions;


class RootMayNotBeDeletedException
	extends \Exception
{
	public function __construct(string $message = 'The root node may not be deleted', int $code = 0, ?\Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
