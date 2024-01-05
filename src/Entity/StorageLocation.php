<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Annotation\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Get,
		new Put,
		new Delete
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class StorageLocation
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, unique: true)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\OneToOne(mappedBy: 'storageLocation', targetEntity: StorageLocationImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	#[UploadedFile]
	private ?StorageLocationImage $image = null;
	#[ORM\ManyToOne(targetEntity: StorageLocationCategory::class, inversedBy: 'storageLocations')]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private ?StorageLocationCategory $category = null;


	#[Groups(['default'])]
	public function getCategoryPath(): string
	{
		return $this->category !== null
			? $this->category->getCategoryPath()
			: '';
	}

	public function setCategory(?StorageLocationCategory $category): self
	{
		$this->category = $category;
		return $this;
	}

	public function getCategory(): ?StorageLocationCategory
	{
		return $this->category;
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setImage(?StorageLocationImage $image): self
	{
		if ($image instanceof StorageLocationImage) {
			$image->setStorageLocation($this);
			$this->image = $image;
		} else {
			// Because this is a 1:1 relationship. only allow the temporary image to be set when no image exists.
			// If an image exists, the frontend needs to deliver the old file ID with the replacement property set.
			if ($this->getImage() === null) {
				$this->image = $image;
			}
		}
		return $this;
	}

	public function getImage(): ?StorageLocationImage
	{
		return $this->image;
	}
}
