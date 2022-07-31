<?php

namespace Limas\Exceptions;


class PasswordChangeNotAllowedException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'Password change not allowed by the administrator';
	}
}
