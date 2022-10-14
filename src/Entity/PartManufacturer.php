<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),

		new Get(),
		new Put(),
		new Delete()
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class PartManufacturer
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'manufacturers')]
	private ?Part $part;
	#[ORM\ManyToOne(targetEntity: Manufacturer::class)]
	#[Groups(['default'])]
	private ?Manufacturer $manufacturer;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $partNumber;


	public function getPart(): ?Part
	{
		return $this->part;
	}

	public function setPart(?Part $part): self
	{
		$this->part = $part;
		return $this;
	}

	public function getManufacturer(): ?Manufacturer
	{
		return $this->manufacturer;
	}

	public function setManufacturer(Manufacturer $manufacturer): self
	{
		$this->manufacturer = $manufacturer;
		return $this;
	}

	public function getPartNumber(): ?string
	{
		return $this->partNumber;
	}

	public function setPartNumber(?string $partNumber): self
	{
		$this->partNumber = $partNumber;
		return $this;
	}
}
