<?php

namespace Limas\Exceptions;


class OnlySingleRootNodeAllowedException
	extends \Exception
{
	public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
	{
		parent::__construct('There may be only one root node', $code, $previous);
	}
}
