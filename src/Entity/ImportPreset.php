<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'name_entity_unique', fields: ['baseEntity', 'name'])]
#[ApiResource(
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class ImportPreset
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $baseEntity;
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\Column(type: Types::TEXT)]
	#[Groups(['default'])]
	private string $configuration;


	public function getBaseEntity(): ?string
	{
		return $this->baseEntity;
	}

	public function setBaseEntity(string $baseEntity): self
	{
		$this->baseEntity = $baseEntity;
		return $this;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getConfiguration(): ?string
	{
		return $this->configuration;
	}

	public function setConfiguration(string $configuration): self
	{
		$this->configuration = $configuration;
		return $this;
	}
}
