<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\MappedSuperclass]
abstract class BaseEntity
{
	#[ORM\Id]
	#[ORM\Column(type: Types::INTEGER)]
	#[ORM\GeneratedValue(strategy: 'AUTO')]
	#[Groups(['default'])]
	private ?int $id = null;


	public function getId(): ?int
	{
		return $this->id;
	}

	public function __toString(): string
	{
		return get_class($this) . ' #' . $this->getId();
	}
}
