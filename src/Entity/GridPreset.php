<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\MarkAsDefault;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'name_grid_unique', fields: ['grid', 'name'])]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Get,
		new Put,
		new Delete,
		new Put(
			uriTemplate: '/grid_presets/{id}/markAsDefault',
			controller: MarkAsDefault::class,
			deserialize: false,
			name: 'MarkGridPresetAsDefault'
		)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class GridPreset
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $grid;
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\Column(type: Types::TEXT)]
	#[Groups(['default'])]
	private string $configuration;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $gridDefault = false;


	public function isGridDefault(): bool
	{
		return $this->gridDefault;
	}

	public function setGridDefault(bool $gridDefault = true): self
	{
		$this->gridDefault = $gridDefault;
		return $this;
	}

	public function getGrid(): ?string
	{
		return $this->grid;
	}

	public function setGrid(string $grid): self
	{
		$this->grid = $grid;
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

	public function getGridDefault(): ?bool
	{
		return $this->gridDefault;
	}
}
