<?php

namespace Limas\Exceptions;


class PartLimitExceededException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'The maximum number of parts is reached';
	}
}
