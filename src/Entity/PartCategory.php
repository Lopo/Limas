<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
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
			uriTemplate: 'part_categories/getExtJSRootNode',
			controller: CategoryActions::class . '::GetRootNodeAction',
			name: 'PartCategoryGetRootNode'
		),
		new Post,
		new Get,
		new Put,
		new Delete,
		new Put(
			uriTemplate: 'part_categories/{id}/move',
			controller: CategoryActions::class . '::MoveAction',
			name: 'PartCategoryMove'
		)
	],
	normalizationContext: ['groups' => ['default', 'tree']],
	denormalizationContext: ['groups' => ['default', 'tree']],
	paginationEnabled: false
)]
class PartCategory
	extends AbstractCategory
	implements CategoryPathInterface
{
	use Tree;


	public function __construct()
	{
		$this->children = new ArrayCollection;
	}
}
