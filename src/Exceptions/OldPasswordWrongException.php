<?php

namespace Limas\Exceptions;


class OldPasswordWrongException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'Old password is wrong';
	}
}
