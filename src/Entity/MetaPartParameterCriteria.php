<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Repository\MetaPartParameterCriteriaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: MetaPartParameterCriteriaRepository::class)]
#[ApiResource(
	collectionOperations: [],
	itemOperations: []
)]
class MetaPartParameterCriteria
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'metaPartParameterCriterias')]
	private ?Part $part;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $partParameterName;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $operator;
	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	#[Groups(['default'])]
	private ?float $value;
	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private ?float $normalizedValue = 0;
	#[ORM\ManyToOne(targetEntity: SiPrefix::class)]
	#[Groups(['default'])]
	private ?SiPrefix $siPrefix = null;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $stringValue = '';
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $valueType;
	#[ORM\ManyToOne(targetEntity: Unit::class)]
	#[Groups(['default'])]
	private ?Unit $unit;


	public function __construct()
	{
		$this->setValueType(PartParameter::VALUE_TYPE_STRING);
	}

	public function getNormalizedValue(): ?float
	{
		return $this->normalizedValue;
	}

	public function setNormalizedValue(?float $normalizedValue): self
	{
		$this->normalizedValue = $normalizedValue;
		return $this;
	}

	protected function recalculateNormalizedValue(): void
	{
		if ($this->getSiPrefix() === null) {
			$this->setNormalizedValue($this->getValue());
		} else {
			$this->setNormalizedValue($this->getSiPrefix()->calculateProduct($this->getValue()));
		}
	}

	public function getUnit(): ?Unit
	{
		return $this->unit;
	}

	public function setUnit(?Unit $unit = null): self
	{
		$this->unit = $unit;
		return $this;
	}

	public function getValueType(): ?string
	{
		if (!in_array($this->valueType, PartParameter::VALUE_TYPES, true)) {
			return PartParameter::VALUE_TYPE_NUMERIC;
		}
		return $this->valueType;
	}

	public function setValueType(string $valueType): self
	{
		if (!in_array($valueType, PartParameter::VALUE_TYPES, true)) {
			throw new \Exception('Invalid value type given:' . $valueType);
		}
		$this->valueType = $valueType;
		return $this;
	}

	public function getSiPrefix(): ?SiPrefix
	{
		return $this->siPrefix;
	}

	public function setSiPrefix(?SiPrefix $siPrefix): self
	{
		$this->siPrefix = $siPrefix;
		$this->recalculateNormalizedValue();
		return $this;
	}

	public function getPart(): ?Part
	{
		return $this->part;
	}

	public function setPart(?Part $part = null): self
	{
		$this->part = $part;
		return $this;
	}

	public function getPartParameterName(): ?string
	{
		return $this->partParameterName;
	}

	public function setPartParameterName(string $partParameterName): self
	{
		$this->partParameterName = $partParameterName;
		return $this;
	}

	public function getOperator(): ?string
	{
		return $this->operator;
	}

	public function setOperator(string $operator): self
	{
		$this->operator = $operator;
		return $this;
	}

	public function getValue(): ?float
	{
		return $this->value;
	}

	public function setValue(?float $value): self
	{
		$this->value = $value;
		$this->recalculateNormalizedValue();
		return $this;
	}

	public function getStringValue(): ?string
	{
		return $this->stringValue;
	}

	public function setStringValue(string $stringValue): self
	{
		$this->stringValue = $stringValue;
		return $this;
	}
}
