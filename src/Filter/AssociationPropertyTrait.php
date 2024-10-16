<?php

namespace Limas\Filter;


trait AssociationPropertyTrait
{
	private string $property;
	private ?string $association = null;


	public function getProperty(): string
	{
		return $this->property;
	}

	public function setProperty(string $property): self
	{
		$this->property = $property;
		return $this;
	}

	public function getAssociation(): ?string
	{
		return $this->association;
	}

	public function setAssociation($association): self
	{
		$this->association = $association;
		return $this;
	}
}
