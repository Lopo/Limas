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
		new GetCollection(uriTemplate: '/storage_location_categories/getExtJSRootNode', controller: Actions\GetRootNode::class),

		new Get(),
		new Put(),
		new Delete(),
		new Put(uriTemplate: '/storage_location_categories/{id}/move', controller: Actions\Move::class)
	],
	normalizationContext: ['groups' => ['default', 'tree']],
	denormalizationContext: ['groups' => ['default', 'tree']]
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
