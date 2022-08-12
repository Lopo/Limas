<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Limas\Controller\Actions\ImageActions;
use Limas\Repository\ManufacturerICLogoRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ManufacturerICLogoRepository::class)]
#[ApiResource(
	itemOperations: [
		'get',
		'put',
		'getImage' => [
			'method' => 'get',
			'path' => 'manufacturer_i_c_logos/{id}/getImage',
			'controller' => ImageActions::class . '::getImageAction'
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
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
