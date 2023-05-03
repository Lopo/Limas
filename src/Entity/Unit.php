<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
#[ApiResource(
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class Unit
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	#[Assert\Type(type: 'string')]
	#[Assert\NotBlank(message: 'unit.name.not_blank')]
	private string $name;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	#[Assert\Type(type: 'string')]
	#[Assert\NotBlank(message: 'unit.symbol.not_blank')]
	private string $symbol;
	/** @var Collection<SiPrefix> */
	#[ORM\ManyToMany(targetEntity: SiPrefix::class)]
	#[ORM\JoinTable(
		joinColumns: [new ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id')],
		inverseJoinColumns: [new ORM\JoinColumn(name: 'siprefix_id', referencedColumnName: 'id')]
	)]
	#[Groups(['default'])]
	#[Assert\All([
		new Assert\Type(type: SiPrefix::class)
	])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private Collection $prefixes;


	public function __construct(?string $name = null, ?string $symbol = null)
	{
		$this->prefixes = new ArrayCollection;
		$this->name = $name;
		$this->symbol = $symbol;
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

	public function setSymbol(string $symbol): self
	{
		$this->symbol = $symbol;
		return $this;
	}

	public function getSymbol(): ?string
	{
		return $this->symbol;
	}

	public function getPrefixes(): Collection
	{
		return $this->prefixes;
	}

	public function addPrefix(SiPrefix $prefix): self
	{
		$this->prefixes->add($prefix);
		return $this;
	}

	public function removePrefix(SiPrefix $prefix): self
	{
		$this->prefixes->removeElement($prefix);
		return $this;
	}
}
