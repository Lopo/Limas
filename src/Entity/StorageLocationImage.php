<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Limas\Controller\Actions\ImageActions;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ApiResource(
	itemOperations: [
		'get',
		'getImage' => [
			'path' => 'storage_location_images/{id}/getImage',
			'method' => 'get',
			'controller' => ImageActions::class . '::getImageAction'
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
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
