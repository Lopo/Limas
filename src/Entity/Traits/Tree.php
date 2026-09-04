<?php

namespace Limas\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


trait Tree
{
	#[ORM\Column(type: Types::INTEGER)]
	#[Gedmo\TreeLeft]
	private int $lft = 0;
	#[ORM\Column(type: Types::INTEGER)]
	#[Gedmo\TreeRight]
	private int $rgt = 0;
	#[ORM\Column(type: Types::INTEGER)]
	#[Gedmo\TreeLevel]
	private int $lvl = 0;


	public function getLevel(): int
	{
		return $this->lvl;
	}

	public function getLeftValue(): int
	{
		return $this->lft;
	}

	public function setLeftValue(int $lft): self
	{
		$this->lft = $lft;
		return $this;
	}

	public function getRightValue(): int
	{
		return $this->rgt;
	}

	public function setRightValue(int $rgt): self
	{
		$this->rgt = $rgt;
		return $this;
	}
}
