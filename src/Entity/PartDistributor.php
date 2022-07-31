<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Limas\Exceptions\PackagingUnitOutOfRangeException;
use Limas\Repository\PartDistributorRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: PartDistributorRepository::class)]
class PartDistributor
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'distributors')]
	private ?Part $part;
	#[ORM\ManyToOne(targetEntity: Distributor::class)]
	#[Groups(['default'])]
	private ?Distributor $distributor;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $orderNumber;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $packagingUnit;
	#[ORM\Column(type: Types::DECIMAL, precision: 13, scale: 4, nullable: true)]
	#[Groups(['default'])]
	private ?string $price;
	#[ORM\Column(type: Types::STRING, length: 3, nullable: true)]
	#[Groups(['default'])]
	private ?string $currency;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $sku;
	#[ORM\Column(type: Types::BOOLEAN, nullable: true)]
	#[Groups(['default'])]
	private ?bool $ignoreForReports = false;


	public function __construct()
	{
		$this->setPackagingUnit(1);
	}

	public function isIgnoreForReports(): ?bool
	{
		return $this->ignoreForReports;
	}

	public function setIgnoreForReports(?bool $ignoreForReports): self
	{
		$this->ignoreForReports = $ignoreForReports;
		return $this;
	}

	public function getCurrency(): mixed
	{
		return $this->currency;
	}

	public function setCurrency(mixed $currency): self
	{
		$this->currency = $currency;
		return $this;
	}

	public function getPackagingUnit(): ?int
	{
		return $this->packagingUnit;
	}

	public function setPackagingUnit(int $packagingUnit): self
	{
		if ($packagingUnit < 1) {
			throw new PackagingUnitOutOfRangeException;
		}
		$this->packagingUnit = $packagingUnit;
		return $this;
	}

	public function getPart(): Part
	{
		return $this->part;
	}

	public function setPart(?Part $part): self
	{
		$this->part = $part;
		return $this;
	}

	public function getDistributor(): Distributor
	{
		return $this->distributor;
	}

	public function setDistributor(Distributor $distributor): self
	{
		$this->distributor = $distributor;
		return $this;
	}

	public function getOrderNumber(): ?string
	{
		return $this->orderNumber;
	}

	public function setOrderNumber(?string $orderNumber): self
	{
		$this->orderNumber = $orderNumber;
		return $this;
	}

	public function getPrice(): ?string
	{
		return $this->price;
	}

	public function setPrice(?string $price): self
	{
		$this->price = $price;
		return $this;
	}

	public function getSku(): ?string
	{
		return $this->sku;
	}

	public function setSku(?string $sku): self
	{
		$this->sku = $sku;
		return $this;
	}


	public function getIgnoreForReports(): ?bool
	{
		return $this->ignoreForReports;
	}
}
