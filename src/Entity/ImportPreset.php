<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Repository\ImportPresetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: ImportPresetRepository::class)]
#[ORM\UniqueConstraint(name: 'name_entity_unique', fields: ['baseEntity', 'name'])]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),

		new Get(),
		new Put(),
		new Delete()
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
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
