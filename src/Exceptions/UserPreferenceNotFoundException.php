<?php

namespace Limas\Exceptions;

use Limas\Entity\User;


class UserPreferenceNotFoundException
	extends TranslatableException
{
	public function __construct(
		private readonly User   $user,
		private readonly string $key,
		int                     $code = 0,
		?\Throwable             $previous = null
	)
	{
		parent::__construct($code, $previous);
	}

	public function getMessageKey(): string
	{
		return sprintf('The requested user preference %s was not found for user %s',
			$this->key,
			$this->user->getUsername()
		);
	}
}
