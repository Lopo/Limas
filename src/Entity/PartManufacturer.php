<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
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
