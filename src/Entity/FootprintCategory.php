<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Limas\Controller\Actions\Category as Actions;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[Gedmo\Tree(type: 'nested')]
#[ORM\Index(fields: ['lft']), ORM\Index(fields: ['rgt'])]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),
		new GetCollection(uriTemplate: '/footprint_categories/getExtJSRootNode', controller: Actions\GetRootNode::class),

		new Get(),
		new Put(),
		new Delete(),
		new Put(uriTemplate: '/footprint_categories/{id}/move', controller: Actions\Move::class)
	],
	normalizationContext: ['groups' => ['default', 'tree']],
	denormalizationContext: ['groups' => ['default', 'tree']]
)]
class FootprintCategory
	extends AbstractCategory
	implements CategoryPathInterface
{
	#[Gedmo\TreeParent]
	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
	#[ORM\JoinColumn(onDelete: 'CASCADE')]
	protected mixed $parent = null;
	/** @var Collection<FootprintCategory> */
	#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
	#[ORM\OrderBy(['lft' => 'ASC'])]
	#[Groups(['tree'])]
	protected Collection $children;
	/** @var Collection<Footprint> */
	#[ORM\OneToMany(mappedBy: 'category', targetEntity: Footprint::class)]
	private Collection $footprints;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $categoryPath;


	public function __construct()
	{
		parent::__construct();
		$this->children = new ArrayCollection;
		$this->footprints = new ArrayCollection;
	}

	#[Groups(['default'])]
	public function setParent(?self $parent = null): self
	{
		$this->parent = $parent;
		return $this;
	}

	public function getParent(): ?self
	{
		return $this->parent;
	}

	public function getFootprints(): Collection
	{
		return $this->footprints;
	}

	public function getChildren(): Collection
	{
		return $this->children;
	}

	public function getCategoryPath(): ?string
	{
		return $this->categoryPath;
	}

	public function setCategoryPath(?string $categoryPath): self
	{
		$this->categoryPath = $categoryPath;
		return $this;
	}

	public function generateCategoryPath(string $pathSeparator): string
	{
		if ($this->getParent() !== null) {
			return $this->getParent()->generateCategoryPath($pathSeparator) . $pathSeparator . $this->getName();
		}
		return $this->getName();
	}
}
