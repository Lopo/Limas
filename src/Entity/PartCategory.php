<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Gedmo\Tree\Strategy;
use Limas\Controller\Actions\CategoryActions;
use Limas\Controller\Actions\PartCategoryActions;
use Limas\Entity\Traits\Tree;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[ORM\Index(fields: ['lft']), ORM\Index(fields: ['rgt'])]
#[Gedmo\Tree(type: Strategy::NESTED)]
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
//		new Put,
		new Patch,
		new Delete,
		new Put(
			uriTemplate: 'part_categories/{id}/move',
			controller: CategoryActions::class . '::MoveAction',
			name: 'PartCategoryMove'
		),
		new Get(
			uriTemplate: 'part_categories/{id}/resolved_defaults',
			controller: PartCategoryActions::class . '::resolvedDefaults',
			name: 'PartCategoryResolvedDefaults'
		),
		new Get(
			uriTemplate: 'part_categories/{id}/inherited_defaults',
			controller: PartCategoryActions::class . '::inheritedDefaults',
			name: 'PartCategoryInheritedDefaults'
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

	/** @var Collection<PartCategoryDefaultParameter> */
	#[ORM\OneToMany(targetEntity: PartCategoryDefaultParameter::class, mappedBy: 'category', cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $defaultParameters;


	public function __construct()
	{
		$this->children = new ArrayCollection;
		$this->defaultParameters = new ArrayCollection;
	}

	/**
	 * @return Collection<PartCategoryDefaultParameter>
	 */
	public function getDefaultParameters(): Collection
	{
		return $this->defaultParameters;
	}

	public function addDefaultParameter(PartCategoryDefaultParameter $defaultParameter): self
	{
		$defaultParameter->setCategory($this);
		if (!$this->defaultParameters->contains($defaultParameter)) {
			$this->defaultParameters->add($defaultParameter);
		}
		return $this;
	}

	public function removeDefaultParameter(PartCategoryDefaultParameter $defaultParameter): self
	{
		$this->defaultParameters->removeElement($defaultParameter);
		return $this;
	}
}
