<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Limas\Repository\StatisticSnapshotUnitRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: StatisticSnapshotUnitRepository::class)]
class StatisticSnapshotUnit
{
	#[ORM\Id]
	#[ORM\Column(type: Types::INTEGER)]
	#[ORM\GeneratedValue(strategy: 'AUTO')]
	private int $id;
	#[ORM\ManyToOne(targetEntity: StatisticSnapshot::class)]
	private ?StatisticSnapshot $statisticSnapshot;
	#[ORM\ManyToOne(targetEntity: PartMeasurementUnit::class)]
	private ?PartMeasurementUnit $partUnit;
	#[ORM\Column(type: Types::INTEGER)]
	private int $stockLevel;


	public function getId(): ?int
	{
		return $this->id;
	}

	public function getStockLevel(): ?int
	{
		return $this->stockLevel;
	}

	public function setStockLevel(int $stockLevel): self
	{
		$this->stockLevel = $stockLevel;
		return $this;
	}

	public function getStatisticSnapshot(): ?StatisticSnapshot
	{
		return $this->statisticSnapshot;
	}

	public function setStatisticSnapshot(?StatisticSnapshot $statisticSnapshot): self
	{
		$this->statisticSnapshot = $statisticSnapshot;
		return $this;
	}

	public function getPartUnit(): ?PartMeasurementUnit
	{
		return $this->partUnit;
	}

	public function setPartUnit(?PartMeasurementUnit $partUnit): self
	{
		$this->partUnit = $partUnit;
		return $this;
	}
}
