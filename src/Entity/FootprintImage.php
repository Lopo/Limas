<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Limas\Controller\Actions\ImageActions;
use Limas\Repository\FootprintImageRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: FootprintImageRepository::class)]
#[ApiResource(
	itemOperations: [
		'get',
		'FootprintImageGetImage' => [
			'method' => 'get',
			'path' => 'footprint_images/{id}/getImage',
			'controller' => ImageActions::class . '::getImageAction'
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class FootprintImage
	extends Image
{
	#[ORM\OneToOne(inversedBy: 'image', targetEntity: Footprint::class)]
	private ?Footprint $footprint;


	public function __construct()
	{
		parent::__construct(self::IMAGE_FOOTPRINT);
	}

	public function getFootprint(): ?Footprint
	{
		return $this->footprint;
	}

	public function setFootprint(?Footprint $footprint): self
	{
		$this->footprint = $footprint;
		return $this;
	}
}
