<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\SetDefaultUnit;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Put(
			uriTemplate: 'part_measurement_units/{id}/setDefault',
			controller: SetDefaultUnit::class,
			name: 'PartMeasurementUnitSetDefault'
		),
		new Get,
		new Put
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class PartMeasurementUnit
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	#[Assert\Type(type: Types::STRING)]
	#[Assert\NotBlank(message: 'partMeasurementUnit.name.not_blank')]
	private string $name;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	#[Assert\Type(type: Types::STRING)]
	#[Assert\NotBlank(message: 'partMeasurementUnit.shortName.not_blank')]
	private string $shortName;
	#[ORM\Column(name: 'is_default', type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $default = false;
	/** @var Collection<Part> */
	#[ORM\OneToMany(mappedBy: 'partUnit', targetEntity: Part::class)]
	private Collection $parts;


	public function __construct()
	{
		$this->parts = new ArrayCollection;
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

	public function setShortName(string $shortName): self
	{
		$this->shortName = $shortName;
		return $this;
	}

	public function getShortName(): ?string
	{
		return $this->shortName;
	}

	public function setDefault(bool $default): self
	{
		$this->default = $default;
		return $this;
	}

	public function isDefault(): bool
	{
		return $this->default;
	}

	public function getParts(): Collection
	{
		return $this->parts;
	}
}
