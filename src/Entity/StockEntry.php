<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Repository\StockEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: StockEntryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
class StockEntry
	extends BaseEntity
{
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $stockLevel;
	#[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'stockLevels')]
	#[Groups(['default'])]
	private ?Part $part;
	#[ORM\ManyToOne(targetEntity: User::class)]
	#[Groups(['default'])]
	private ?User $user;
	#[ORM\Column(type: Types::DECIMAL, precision: 13, scale: 4, nullable: true)]
	#[Groups(['default'])]
	private ?string $price = '0';
	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	#[Groups(['default'])]
	private \DateTimeInterface $dateTime;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $correction = false;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $comment;


	public function __construct()
	{
		$this->setDateTime(new \DateTime);
	}

	public function setDateTime(\DateTimeInterface $dateTime): self
	{
		$this->dateTime = $dateTime;
		return $this;
	}

	public function getDateTime(): ?\DateTimeInterface
	{
		return $this->dateTime;
	}

	public function setCorrection(bool $correction): self
	{
		$this->correction = $correction;
		return $this;
	}

	public function getCorrection(): ?bool
	{
		return $this->correction;
	}

	public function setPrice(?string $price): self
	{
		$this->price = $price;
		return $this;
	}

	public function getPrice(): ?string
	{
		return $this->price;
	}

	public function setStockLevel(int $stockLevel): self
	{
		$this->stockLevel = $stockLevel;
		return $this;
	}

	public function getStockLevel(): ?int
	{
		return $this->stockLevel;
	}

	public function setPart(?Part $part): self
	{
		$this->part = $part;
		return $this;
	}

	public function getPart(): ?Part
	{
		return $this->part;
	}

	public function setUser(?User $user): self
	{
		$this->user = $user;
		return $this;
	}

	public function getUser(): ?User
	{
		return $this->user;
	}

	public function checkPrice(): void
	{
		if ($this->getStockLevel() < 0 && $this->getPrice() !== null) {
			$this->setPrice(null);
		}
	}

	public function isRemoval(): bool
	{
		return $this->getStockLevel() < 0;
	}

	public function setComment(?string $comment): self
	{
		$this->comment = $comment;
		return $this;
	}

	public function getComment(): ?string
	{
		return $this->comment;
	}
}
