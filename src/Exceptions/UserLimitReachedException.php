<?php

namespace Limas\Exceptions;


class UserLimitReachedException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'The maximum number of users is reached';
	}
}
