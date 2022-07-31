<?php

namespace Limas\Exceptions;


class OnlySingleRootNodeAllowedException
	extends \Exception
{
	public function __construct(string $message = 'There may be only one root node', int $code = 0, ?\Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
