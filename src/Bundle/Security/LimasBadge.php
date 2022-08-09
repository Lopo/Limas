<?php

namespace Limas\Bundle\Security;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;


class LimasBadge
	implements BadgeInterface
{
	private bool $resolved = false;


	public function markResolved(): void
	{
		$this->resolved = true;
	}

	public function isResolved(): bool
	{
		return $this->resolved;
	}
}
