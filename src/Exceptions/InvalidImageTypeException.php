<?php

namespace Limas\Exceptions;


class InvalidImageTypeException
	extends TranslatableException
{
	public function __construct(
		private readonly string $type,
		int                     $code = 0,
		?\Throwable             $previous = null
	)
	{
		parent::__construct($code, $previous);
	}

	public function getMessageKey(): string
	{
		return sprintf('Invalid image type "%s"', $this->type);
	}
}
