<?php

namespace Limas\Exceptions;


class SystemPreferenceNotFoundException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'The requested system preference was not found';
	}
}
