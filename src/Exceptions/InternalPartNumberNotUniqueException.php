<?php

namespace Limas\Exceptions;


class InternalPartNumberNotUniqueException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'The internal part number is already used';
	}
}
