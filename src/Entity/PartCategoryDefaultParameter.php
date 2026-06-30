<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


/**
 * Per-category default parameter template. When a new Part is created in a
 * category, the resolver walks the category tree from leaf to root and
 * pre-populates the editor with empty PartParameter rows matching each
 * inherited template (child wins on same name).
 *
 * The template only carries the *shape* — name, description, unit hint,
 * valueType — never values. Values are per-Part.
 *
 * PartKeepr issues #777 / #366 / #54: "every resistor needs Resistance +
 * Tolerance + Power" templates, repeatedly requested since 2014.
 */
#[ORM\Entity]
#[ORM\Table(name: 'PartCategoryDefaultParameter')]
#[ORM\Index(name: 'IDX_pcdp_category', columns: ['category_id'])]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Get,
		new Patch,
		new Delete
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class PartCategoryDefaultParameter
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: PartCategory::class, inversedBy: 'defaultParameters')]
	#[ORM\JoinColumn(name: 'category_id', nullable: false, onDelete: 'CASCADE')]
	#[Groups(['default'])]
	private PartCategory $category;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $name = '';
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $description = '';
	#[ORM\ManyToOne(targetEntity: Unit::class)]
	#[Groups(['default'])]
	private ?Unit $unit = null;
	#[ORM\Column(type: Types::STRING, length: 16)]
	#[Groups(['default'])]
	private string $valueType = PartParameter::VALUE_TYPE_STRING;


	public function getCategory(): PartCategory
	{
		return $this->category;
	}

	public function setCategory(PartCategory $category): self
	{
		$this->category = $category;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getDescription(): string
	{
		return $this->description;
	}

	public function setDescription(string $description): self
	{
		$this->description = $description;
		return $this;
	}

	public function getUnit(): ?Unit
	{
		return $this->unit;
	}

	public function setUnit(?Unit $unit): self
	{
		$this->unit = $unit;
		return $this;
	}

	public function getValueType(): string
	{
		return $this->valueType;
	}

	public function setValueType(string $valueType): self
	{
		if (!in_array($valueType, PartParameter::VALUE_TYPES, true)) {
			throw new \InvalidArgumentException('Invalid value type: ' . $valueType);
		}
		$this->valueType = $valueType;
		return $this;
	}
}
