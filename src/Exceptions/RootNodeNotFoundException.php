<?php

namespace Limas\Exceptions;


class RootNodeNotFoundException
	extends \Exception
{
	public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
	{
		parent::__construct('Root Category not found', $code, $previous);
	}
}
