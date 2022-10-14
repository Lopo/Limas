<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use Limas\Controller\Actions\ImageGetImage;
use Limas\Repository\ManufacturerICLogoRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ManufacturerICLogoRepository::class)]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),

		new Get(),
		new Put(),
		new Get(uriTemplate: '/manufacturer_i_c_logos/{id}/getImage', controller: ImageGetImage::class)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class ManufacturerICLogo
	extends Image
{
	#[ORM\ManyToOne(targetEntity: Manufacturer::class, inversedBy: 'icLogos')]
	private ?Manufacturer $manufacturer = null;


	public function __construct()
	{
		parent::__construct(Image::IMAGE_ICLOGO);
	}

	public function getManufacturer(): ?Manufacturer
	{
		return $this->manufacturer;
	}

	public function setManufacturer(?Manufacturer $manufacturer): self
	{
		$this->manufacturer = $manufacturer;
		return $this;
	}
}
