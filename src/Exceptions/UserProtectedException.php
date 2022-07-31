<?php

namespace Limas\Exceptions;


class UserProtectedException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'User is protected against changes';
	}
}
