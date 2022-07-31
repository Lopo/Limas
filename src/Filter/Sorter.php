<?php

namespace Limas\Filter;


class Sorter
	implements AssociationPropertyInterface
{
	use AssociationPropertyTrait;

	private string $direction;


	public function getDirection(): string
	{
		return $this->direction;
	}

	public function setDirection(string $direction): self
	{
		$this->direction = $direction;
		return $this;
	}
}
