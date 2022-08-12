<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Annotation\ByReference;
use Limas\Repository\ProjectPartRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ProjectPartRepository::class)]
#[ApiResource(
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class ProjectPart
	extends BaseEntity
{
	public const OVERAGE_TYPE_ABSOLUTE = 'absolute';
	public const OVERAGE_TYPE_PERCENT = 'percent';
	protected const OVERAGE_TYPES = [
		self::OVERAGE_TYPE_ABSOLUTE,
		self::OVERAGE_TYPE_PERCENT
	];

	#[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'projectParts')]
	#[Groups(['default'])]
	#[Assert\NotNull]
	#[ByReference]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private ?Part $part;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $quantity;
	#[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'parts')]
	#[Assert\NotNull]
	private ?Project $project;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $remarks;
	#[ORM\Column(type: Types::STRING, options: ['default' => ''])]
	#[Groups(['default'])]
	private string $overageType = self::OVERAGE_TYPE_ABSOLUTE;
	#[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
	#[Groups(['default'])]
	private int $overage = 0;
	#[ORM\Column(type: Types::TEXT, options: ['default' => ''])]
	#[Groups(['default'])]
	private string $lotNumber = '';
	#[Groups(['default'])]
	private int $totalQuantity;


	public function getTotalQuantity(): int
	{
		return match ($this->getOverageType()) {
			self::OVERAGE_TYPE_PERCENT => (int)$this->getQuantity() * (1 + $this->getOverage() / 100),
			self::OVERAGE_TYPE_ABSOLUTE => $this->getQuantity() + $this->getOverage(),
			default => $this->getQuantity(),
		};
	}

	public function getLotNumber(): ?string
	{
		return $this->lotNumber;
	}

	public function setLotNumber(string $lotNumber): self
	{
		$this->lotNumber = $lotNumber;
		return $this;
	}

	public function getOverageType(): ?string
	{
		if (!in_array($this->overageType, self::OVERAGE_TYPES, true)) {
			return self::OVERAGE_TYPE_ABSOLUTE;
		}
		return $this->overageType;
	}

	public function setOverageType(string $overageType): self
	{
		if (!in_array($overageType, self::OVERAGE_TYPES, true)) {
			$overageType = self::OVERAGE_TYPE_ABSOLUTE;
		}
		$this->overageType = $overageType;
		return $this;
	}

	public function getOverage(): ?int
	{
		return $this->overage;
	}

	public function setOverage(int $overage): self
	{
		$this->overage = $overage;
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

	public function getQuantity(): ?int
	{
		return $this->quantity;
	}

	public function setQuantity(int $quantity): self
	{
		$this->quantity = $quantity;
		return $this;
	}

	public function getProject(): ?Project
	{
		return $this->project;
	}

	public function setProject(?Project $project = null): self
	{
		$this->project = $project;
		return $this;
	}

	public function getRemarks(): ?string
	{
		return $this->remarks;
	}

	public function setRemarks(?string $remarks): self
	{
		$this->remarks = $remarks;
		return $this;
	}

	public function __toString(): string
	{
		return sprintf('Used in project %s', $this->getProject()->getName()) . ' / ' . parent::__toString();
	}
}
