<?php

namespace Limas\Entity\Traits;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;


trait Tree
{
	#[ORM\ManyToOne(targetEntity: self::class)]
	#[Gedmo\TreeRoot]
	private ?self $root;
	#[Gedmo\TreeParent]
	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
	#[ORM\JoinColumn(referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
	#[ApiProperty(writableLink: true)]
	#[Groups(['default', 'tree'])]
	protected ?self $parent = null;
	/** @var Collection<self> */
	#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
	#[ORM\OrderBy(['lft' => 'ASC'])]
	#[Groups(['tree'])]
	protected Collection $children;

	#[ORM\Column(type: Types::INTEGER)]
	#[Gedmo\TreeLeft]
	private int $lft;
	#[ORM\Column(type: Types::INTEGER)]
	#[Gedmo\TreeRight]
	private int $rgt;
	#[ORM\Column(type: Types::INTEGER)]
	#[Gedmo\TreeLevel]
	private int $lvl;


	public function setRoot(?self $root): self
	{
		$this->root = $root;
		return $this;
	}

	public function getRoot(): ?self
	{
		return $this->root;
	}

	#[Groups(['default'])]
	public function setParent(?self $parent): self
	{
		$this->parent = $parent;
		return $this;
	}

	public function getParent(): ?self
	{
		return $this->parent;
	}

	public function getChildren(): Collection
	{
		return $this->children;
	}

	public function getLevel(): int
	{
		return $this->lvl;
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
}
