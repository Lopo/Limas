<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\MarkAsDefault;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'name_grid_unique', fields: ['grid', 'name'])]
#[ApiResource(
	itemOperations: [
		'get',
		'mark_as_default' => [
			'method' => 'put',
			'path' => 'grid_presets/{id}/markAsDefault',
			'controller' => MarkAsDefault::class,
			'deserialize' => false
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
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
