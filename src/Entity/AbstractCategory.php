<?php

namespace Limas\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\MappedSuperclass]
#[ORM\Index(fields: ['lft']), ORM\Index(fields: ['rgt'])]
abstract class AbstractCategory
	extends BaseEntity
{
	protected mixed $parent;
	#[ORM\Column(type: Types::INTEGER)]
	#[Gedmo\TreeLeft]
	private int $lft;
	#[ORM\Column(type: Types::INTEGER)]
	#[Gedmo\TreeRight]
	private int $rgt;
	#[ORM\Column(type: Types::INTEGER)]
	#[Gedmo\TreeLevel]
	private int $lvl;
	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	#[Gedmo\TreeRoot]
	private ?int $root;
	#[ORM\Column(type: Types::STRING, length: 128)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $description;
	#[Groups(['default'])]
	private bool $expanded = true;
	#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
	#[ORM\OrderBy(['lft' => 'ASC'])]
	#[Groups(['tree'])]
	protected Collection $children;


	public function __construct()
	{
		$this->children = new ArrayCollection;
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getLevel(): int
	{
		return $this->lvl;
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

	public function getLeftValue(): int
	{
		return $this->lft;
	}

	public function setLeftValue(int $lft): self
	{
		$this->lft = $lft;
		return $this;
	}

	public function getRightValue(): int
	{
		return $this->rgt;
	}

	public function setRightValue(int $rgt): self
	{
		$this->rgt = $rgt;
		return $this;
	}

	public function setRoot(?int $root): self
	{
		$this->root = $root;
		return $this;
	}

	public function getRoot(): ?int
	{
		return $this->root;
	}

	public function isExpanded(): bool
	{
		return $this->expanded;
	}
}
