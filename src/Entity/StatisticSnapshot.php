<?php

namespace Limas\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Limas\Repository\StatisticSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: StatisticSnapshotRepository::class)]
class StatisticSnapshot
{
	#[ORM\Id]
	#[ORM\Column(type: Types::INTEGER)]
	#[ORM\GeneratedValue(strategy: 'AUTO')]
	private int $id;
	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	private \DateTimeInterface $dateTime;
	#[ORM\Column(type: Types::INTEGER)]
	private int $parts;
	#[ORM\Column(type: Types::INTEGER)]
	private int $categories;
	/** @var Collection<StatisticSnapshotUnit> */
	#[ORM\OneToMany(mappedBy: 'statisticSnapshot', targetEntity: StatisticSnapshotUnit::class, cascade: ['persist', 'remove'])]
	private Collection $units;


	public function __construct()
	{
		$this->units = new ArrayCollection;
		$this->setDateTime(new \DateTime);
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getDateTime(): ?\DateTimeInterface
	{
		return $this->dateTime;
	}

	public function setDateTime(\DateTimeInterface $dateTime): self
	{
		$this->dateTime = $dateTime;
		return $this;
	}

	public function getParts(): ?int
	{
		return $this->parts;
	}

	public function setParts(int $parts): self
	{
		$this->parts = $parts;
		return $this;
	}

	public function getCategories(): ?int
	{
		return $this->categories;
	}

	public function setCategories(int $categories): self
	{
		$this->categories = $categories;
		return $this;
	}

	public function getUnits(): Collection
	{
		return $this->units;
	}
}
