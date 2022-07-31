<?php

namespace Limas\Exceptions;


class PackagingUnitOutOfRangeException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'Packaging Unit is out of range. It must be 1 or higher.';
	}
}
