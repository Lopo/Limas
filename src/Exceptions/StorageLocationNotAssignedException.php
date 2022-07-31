<?php

namespace Limas\Exceptions;


class StorageLocationNotAssignedException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'The part has no storage location assigned';
	}
}
