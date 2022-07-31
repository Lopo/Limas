<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Repository\SiPrefixRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: SiPrefixRepository::class)]
#[ApiResource(
	collectionOperations: ['get'],
	itemOperations: ['get']
)]
class SiPrefix
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING)]
	#[Assert\Type(type: 'string')]
	#[Assert\NotBlank(message: 'siprefix.prefix.not_blank')]
	#[Groups(['default'])]
	private string $prefix;
	#[ORM\Column(type: Types::STRING, length: 2)]
	#[Assert\Type(type: 'string')]
	#[Groups(['default'])]
	private string $symbol;
	#[ORM\Column(type: Types::INTEGER)]
	#[Assert\Type(type: 'integer')]
	#[Groups(['default'])]
	private int $exponent;
	#[ORM\Column(type: Types::INTEGER)]
	#[Assert\Type(type: 'integer')]
	#[Groups(['default'])]
	private int $base;


	public function __construct(?string $prefix, ?string $symbol, ?int $exponent, ?int $base)
	{
		$this->prefix = $prefix;
		$this->symbol = $symbol;
		$this->exponent = $exponent;
		$this->base = $base;
	}

	public function setPrefix(string $prefix): self
	{
		$this->prefix = $prefix;
		return $this;
	}

	public function getPrefix(): ?string
	{
		return $this->prefix;
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

	public function setExponent(int $exponent): self
	{
		$this->exponent = $exponent;
		return $this;
	}

	public function getExponent(): ?int
	{
		return $this->exponent;
	}

	public function setBase(int $base): self
	{
		$this->base = $base;
		return $this;
	}

	public function getBase(): ?int
	{
		return $this->base;
	}

	public function calculateProduct(float $value): float
	{
		return $value * ($this->base ** $this->exponent);
	}
}
