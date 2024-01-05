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
			uriTemplate: 'footprint_categories/getExtJSRootNode',
			controller: CategoryActions::class . '::GetRootNodeAction',
			name: 'FootprintCategoryGetRootNode'
		),
		new Post,
		new Get,
		new Put,
		new Delete,
		new Put(
			uriTemplate: 'footprint_categories/{id}/move',
			controller: CategoryActions::class . '::MoveAction',
			name: 'FootprintCategoryMove'
		)
	],
	normalizationContext: ['groups' => ['default', 'tree']],
	denormalizationContext: ['groups' => ['default', 'tree']]
)]
class FootprintCategory
	extends AbstractCategory
	implements CategoryPathInterface
{
	use Tree;

	/** @var Collection<Footprint> */
	#[ORM\OneToMany(mappedBy: 'category', targetEntity: Footprint::class)]
	private Collection $footprints;


	public function __construct()
	{
		$this->children = new ArrayCollection;
		$this->footprints = new ArrayCollection;
	}

	public function getFootprints(): Collection
	{
		return $this->footprints;
	}
}
