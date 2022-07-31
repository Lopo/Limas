<?php

namespace Limas\Exceptions;


class CategoryNotAssignedException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'The part has no category assigned';
	}
}
