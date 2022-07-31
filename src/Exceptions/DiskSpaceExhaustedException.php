<?php

namespace Limas\Exceptions;


class DiskSpaceExhaustedException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'Not enough disk space or quota exhausted';
	}
}
