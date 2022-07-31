<?php

namespace Limas\Exceptions;


class RootNodeNotFoundException
	extends \Exception
{
	public function __construct(string $message = 'Root Category not found', int $code = 0, ?\Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
