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
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Limas\Controller\Actions\CategoryActions;
use Limas\Entity\Traits\Tree;


#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[ORM\Index(fields: ['lft']), ORM\Index(fields: ['rgt'])]
#[Gedmo\Tree(type: 'nested')]
#[ApiResource(
	operations: [
		new GetCollection,
		new GetCollection(
			uriTemplate: 'storage_location_categories/getExtJSRootNode',
			controller: CategoryActions::class . '::GetRootNodeAction',
			name: 'StorageLocationCategoryGetRoot'
		),
		new Post,
		new Get,
		new Put,
		new Delete,
		new Put(
			uriTemplate: 'storage_location_categories/{id}/move',
			controller: CategoryActions::class . '::MoveAction',
			name: 'StorageLocationCategoryMove'
		)
	],
	normalizationContext: ['groups' => ['default', 'tree']],
	denormalizationContext: ['groups' => ['default', 'tree']]
)]
class StorageLocationCategory
	extends AbstractCategory
{
	use Tree;

	/** @var Collection<StorageLocation> */
	#[ORM\OneToMany(mappedBy: 'category', targetEntity: StorageLocation::class)]
	private Collection $storageLocations;


	public function __construct()
	{
		$this->children = new ArrayCollection;
		$this->storageLocations = new ArrayCollection;
	}

	public function getStorageLocations(): Collection
	{
		return $this->storageLocations;
	}

	public function generateCategoryPath(string $pathSeparator): string
	{
		return $this->getParent() !== null
			? $this->getParent()->generateCategoryPath($pathSeparator) . $pathSeparator . $this->getName()
			: $this->getName();
	}
}
