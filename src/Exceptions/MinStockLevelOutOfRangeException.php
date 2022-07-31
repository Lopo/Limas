<?php

namespace Limas\Exceptions;


class MinStockLevelOutOfRangeException
	extends TranslatableException
{
	public function getMessageKey(): string
	{
		return 'Minimum Stock Level is out of range. The minimum stock level must be 0 or higher';
	}
}
