<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Limas\Controller\Actions\Category as Actions;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[ORM\Index(fields: ['lft']), ORM\Index(fields: ['rgt'])]
#[Gedmo\Tree(type: 'nested')]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),
		new GetCollection(uriTemplate: '/part_categories/getExtJSRootNode', controller: Actions\GetRootNode::class),

		new Get(),
		new Put(),
		new Delete(),
		new Put(uriTemplate: '/part_categories/{id}/move', controller: Actions\Move::class)
	],
	normalizationContext: ['groups' => ['default', 'tree']],
	denormalizationContext: ['groups' => ['default', 'tree']],
	paginationEnabled: false
)]
class PartCategory
	extends AbstractCategory
	implements CategoryPathInterface
{
	#[Gedmo\TreeParent]
	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
	#[ORM\JoinColumn(onDelete: 'CASCADE')]
	#[Groups(['tree'])]
	protected mixed $parent = null;
	/** @var Collection<PartCategory> */
	#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
	#[ORM\OrderBy(['lft' => 'ASC'])]
	#[Groups(['tree'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	protected Collection $children;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $categoryPath;


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

	public function getCategoryPath(): ?string
	{
		return $this->categoryPath;
	}

	public function setCategoryPath(?string $categoryPath): self
	{
		$this->categoryPath = $categoryPath;
		return $this;
	}

	public function generateCategoryPath($pathSeparator): string
	{
		return $this->getParent() !== null
			? $this->getParent()->generateCategoryPath($pathSeparator) . $pathSeparator . $this->getName()
			: $this->getName();
	}
}
