<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Annotation\VirtualOneToMany;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Get,
		new Put,
		new Delete
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class ReportPart
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: Report::class, inversedBy: 'reportParts')]
	#[Groups(['default'])]
	private ?Report $report;
	#[ORM\ManyToOne(targetEntity: Part::class)]
	#[Groups(['default'])]
	private ?Part $part;
	#[ORM\Column(type: Types::INTEGER, nullable: false)]
	#[Groups(['default'])]
	private int $quantity;
	#[ORM\ManyToOne(targetEntity: Distributor::class)]
	#[Groups(['default'])]
	private ?Distributor $distributor;
	#[Groups(['default'])]
	private string $distributorOrderNumber;
	#[Groups(['default'])]
	private string $itemPrice;
	#[Groups(['default'])]
	private string $orderSum;
	#[Groups(['default'])]
	private bool $metaPart;
	#[VirtualOneToMany(target: Part::class)]
	#[Groups(['default'])]
	private Collection $subParts;
	#[VirtualOneToMany(target: ProjectPart::class)]
	#[Groups(['default'])]
	private Collection $projectParts;
	#[Groups(['default'])]
	private string $itemSum;
	#[Groups(['default'])]
	private int $missing;


	public function __construct()
	{
		$this->projectParts = new ArrayCollection;
	}

	public function getProjectParts(): Collection
	{
		return $this->projectParts;
	}

	public function getSubParts(): Collection
	{
		return $this->subParts;
	}

	public function setSubParts(Collection $subParts): self
	{
		$this->subParts = $subParts;
		return $this;
	}

	public function isMetaPart(): bool
	{
		return $this->metaPart;
	}

	public function setMetaPart(bool $metaPart): self
	{
		$this->metaPart = $metaPart;
		return $this;
	}

	public function getDistributorOrderNumber(): string
	{
		return $this->distributorOrderNumber;
	}

	public function setDistributorOrderNumber(string $distributorOrderNumber): self
	{
		$this->distributorOrderNumber = $distributorOrderNumber;
		return $this;
	}

	public function getItemPrice(): string
	{
		return $this->itemPrice;
	}

	public function setItemPrice(string $itemPrice): self
	{
		$this->itemPrice = $itemPrice;
		return $this;
	}

	public function getOrderSum(): string
	{
		return $this->orderSum;
	}

	public function setOrderSum(string $orderSum): void
	{
		$this->orderSum = $orderSum;
	}

	public function getItemSum(): string
	{
		return $this->itemSum;
	}

	public function setItemSum(string $itemSum): void
	{
		$this->itemSum = $itemSum;
	}

	public function getDistributor(): ?Distributor
	{
		return $this->distributor;
	}

	public function setDistributor(?Distributor $distributor): self
	{
		$this->distributor = $distributor;
		return $this;
	}

	public function getMissing(): int
	{
		return $this->missing;
	}

	public function setMissing(int $missing): void
	{
		$this->missing = $missing;
	}

	public function getQuantity(): ?int
	{
		return $this->quantity;
	}

	public function setQuantity(int $quantity): self
	{
		$this->quantity = $quantity;
		return $this;
	}

	public function getReport(): ?Report
	{
		return $this->report;
	}

	public function setReport(?Report $report): self
	{
		$this->report = $report;
		return $this;
	}

	public function getPart(): ?Part
	{
		return $this->part;
	}

	public function setPart(?Part $part): self
	{
		$this->part = $part;
		return $this;
	}

	public function __toString(): string
	{
		return sprintf('Used in project report %s %s',
				$this->getReport()->getName(),
				$this->getReport()->getCreateDateTime()->format('Y-m-d H:i:s')
			) . ' / ' . parent::__toString();
	}
}
