<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Limas\Controller\Actions\ImageGetImage;
use Limas\Repository\FootprintImageRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: FootprintImageRepository::class)]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),

		new Get(),
		new Get(uriTemplate: '/footprint_images/{id}/getImage', controller: ImageGetImage::class)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
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
