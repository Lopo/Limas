<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\CategoryActions;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[ORM\Index(fields: ['lft']), ORM\Index(fields: ['rgt'])]
#[Gedmo\Tree(type: 'nested')]
#[ApiResource(
	collectionOperations: [
		'get',
		'post',
		'get_root_node' => [
			'method' => 'get',
			'path' => 'part_categories/getExtJSRootNode',
			'controller' => CategoryActions::class . '::GetRootNodeAction'
		]
	],
	itemOperations: [
		'get',
		'put',
		'delete',
		'move' => [
			'method' => 'put',
			'path' => 'part_categories/{id}/move',
			'controller' => CategoryActions::class . '::MoveAction'
		]
	],
	denormalizationContext: ['groups' => ['default', 'tree']],
	normalizationContext: ['groups' => ['default', 'tree']],
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
