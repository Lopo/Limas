<?php

namespace Limas\Exceptions;


abstract class TranslatableException
	extends \Exception
{
	public function __construct(int $code = 0, ?\Throwable $previous = null)
	{
		parent::__construct($this->getMessageKey(), $code, $previous);
	}

	abstract public function getMessageKey(): mixed;
}
