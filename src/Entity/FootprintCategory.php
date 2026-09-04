<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiProperty;
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
use Limas\Entity\Traits\Tree;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[ORM\Index(fields: ['lft']), ORM\Index(fields: ['rgt'])]
#[ORM\Index(name: 'idx_footprintcategory_categorypath', columns: ['categoryPath'], options: ['lengths' => [191]])]
#[Gedmo\Tree(type: Strategy::NESTED)]
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
//		new Put,
		new Patch,
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

	#[ORM\ManyToOne(targetEntity: self::class)]
	#[Gedmo\TreeRoot]
	private ?self $root = null;
	#[Gedmo\TreeParent]
	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
	#[ORM\JoinColumn(referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
	#[ApiProperty(writableLink: true)]
	#[Groups(['default', 'tree'])]
	protected ?self $parent = null;
	/** @var Collection<self> */
	#[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
	#[ORM\OrderBy(['lft' => 'ASC'])]
	#[Groups(['tree'])]
	protected Collection $children;
	/** @var Collection<Footprint> */
	#[ORM\OneToMany(targetEntity: Footprint::class, mappedBy: 'category')]
	private Collection $footprints;


	public function __construct()
	{
		$this->children = new ArrayCollection;
		$this->footprints = new ArrayCollection;
	}

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

	public function getFootprints(): Collection
	{
		return $this->footprints;
	}
}
