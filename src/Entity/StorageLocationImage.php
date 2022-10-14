<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Limas\Controller\Actions\ImageGetImage;
use Limas\Repository\StorageLocationImageRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: StorageLocationImageRepository::class)]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),

		new Get(),
		new Get(uriTemplate: '/storage_location_images/{id}/getImage', controller: ImageGetImage::class)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class StorageLocationImage
	extends Image
{
	#[ORM\OneToOne(inversedBy: 'image', targetEntity: StorageLocation::class)]
	private ?StorageLocation $storageLocation = null;


	public function __construct()
	{
		parent::__construct(self::IMAGE_STORAGELOCATION);
	}

	public function getStorageLocation(): ?StorageLocation
	{
		return $this->storageLocation;
	}

	public function setStorageLocation(?StorageLocation $storageLocation): self
	{
		$this->storageLocation = $storageLocation;
		return $this;
	}
}
