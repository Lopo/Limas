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
		'get', 'post',
		'get_root_node' => [
			'method' => 'get',
			'path' => 'storage_location_categories/getExtJSRootNode',
			'controller' => CategoryActions::class . '::GetRootNodeAction'
		],
	],
	itemOperations: ['get', 'put', 'delete',
		'move' => [
			'method' => 'put',
			'path' => 'storage_location_categories/{id}/move',
			'controller' => CategoryActions::class . '::MoveAction'
		]
	],
	denormalizationContext: ['groups' => ['default', 'tree']],
	normalizationContext: ['groups' => ['default', 'tree']]
)]
class StorageLocationCategory
	extends AbstractCategory
{
	#[Gedmo\TreeParent]
	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
	#[ORM\JoinColumn(referencedColumnName: 'id', onDelete: 'CASCADE')]
	#[Groups(['default'])]
	protected mixed $parent = null;
	/** @var Collection<StorageLocationCategory> */
	#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
	#[ORM\OrderBy(['lft' => 'ASC'])]
	#[Groups(['tree'])]
	protected Collection $children;
	/** @var Collection<StorageLocation> */
	#[ORM\OneToMany(mappedBy: 'category', targetEntity: StorageLocation::class)]
	private Collection $storageLocations;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $categoryPath;


	public function __construct()
	{
		parent::__construct();
		$this->storageLocations = new ArrayCollection;
	}

	public function getParent(): ?self
	{
		return $this->parent;
	}

	public function setParent(?self $parent): self
	{
		$this->parent = $parent;
		return $this;
	}

	public function getStorageLocations(): Collection
	{
		return $this->storageLocations;
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
		return $this->getParent() !== null
			? $this->getParent()->generateCategoryPath($pathSeparator) . $pathSeparator . $this->getName()
			: $this->getName();
	}
}
