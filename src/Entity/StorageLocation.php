<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Annotation\UploadedFile;
use Limas\Repository\StorageLocationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: StorageLocationRepository::class)]
#[ApiResource]
class StorageLocation
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, unique: true)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\OneToOne(mappedBy: 'storageLocation', targetEntity: StorageLocationImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	#[UploadedFile]
	private ?StorageLocationImage $image;
	#[ORM\ManyToOne(targetEntity: StorageLocationCategory::class, inversedBy: 'storageLocations')]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private ?StorageLocationCategory $category;


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
