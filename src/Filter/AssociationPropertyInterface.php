<?php

namespace Limas\Filter;


interface AssociationPropertyInterface
{
	public function getProperty(): string;

	public function setProperty(string $property): self;

	public function getAssociation(): ?string;

	public function setAssociation($association): self;
}
