<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\CategoryActions;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[Gedmo\Tree(type: 'nested')]
#[ORM\Index(fields: ['lft']), ORM\Index(fields: ['rgt'])]
#[ApiResource(
	collectionOperations: [
		'get',
		'post',
		'get_root_node' => [
			'method' => 'get',
			'path' => 'footprint_categories/getExtJSRootNode',
			'controller' => CategoryActions::class . '::GetRootNodeAction'
		],
	],
	itemOperations: [
		'get',
		'put',
		'delete',
		'move' => [
			'method' => 'put',
			'path' => 'footprint_categories/{id}/move',
			'controller' => CategoryActions::class . '::MoveAction'
		]
	],
	denormalizationContext: ['groups' => ['default', 'tree']],
	normalizationContext: ['groups' => ['default', 'tree']]
)]
class FootprintCategory
	extends AbstractCategory
	implements CategoryPathInterface
{
	#[Gedmo\TreeParent]
	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
	#[ORM\JoinColumn(onDelete: 'CASCADE')]
	protected mixed $parent = null;
	#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
	#[ORM\OrderBy(['lft' => 'ASC'])]
	#[Groups(['tree'])]
	protected Collection $children;
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
