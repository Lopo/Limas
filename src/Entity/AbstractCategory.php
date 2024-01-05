<?php

namespace Limas\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\MappedSuperclass]
abstract class AbstractCategory
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, length: 128)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $description;
	#[Groups(['default'])]
	private bool $expanded = true;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $categoryPath = null;


	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function isExpanded(): bool
	{
		return $this->expanded;
	}

	public function setCategoryPath(?string $categoryPath): self
	{
		$this->categoryPath = $categoryPath;
		return $this;
	}

	public function getCategoryPath(): ?string
	{
		return $this->categoryPath;
	}

	public function generateCategoryPath(string $pathSeparator): string
	{
		if ($this->getParent() !== null) {
			return $this->getParent()->generateCategoryPath($pathSeparator) . $pathSeparator . $this->getName();
		}
		return $this->getName();
	}

//	abstract public function setParent(?AbstractCategory $parent): AbstractCategory;

	abstract public function getParent(): ?AbstractCategory;

	abstract public function getChildren(): Collection;
}
