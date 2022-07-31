<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Limas\Repository\PartParameterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: PartParameterRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PartParameter
	extends BaseEntity
{
	final public const VALUE_TYPE_STRING = 'string';
	final public const VALUE_TYPE_NUMERIC = 'numeric';
	final public const VALUE_TYPES = [self::VALUE_TYPE_STRING, self::VALUE_TYPE_NUMERIC];

	#[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'parameters')]
	private Part $part;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $description = '';
	#[ORM\ManyToOne(targetEntity: Unit::class)]
	#[Groups(['default'])]
	private ?Unit $unit;
	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	#[Groups(['default'])]
	private ?float $value = null;
	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private ?float $normalizedValue = null;
	#[ORM\Column(name: 'maximumValue', type: Types::FLOAT, nullable: true)]
	#[Groups(['default'])]
	private ?float $maxValue = null;
	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private ?float $normalizedMaxValue = null;
	#[ORM\Column(name: 'minimumValue', type: Types::FLOAT, nullable: true)]
	#[Groups(['default'])]
	private ?float $minValue = null;
	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private ?float $normalizedMinValue = null;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $stringValue = '';
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $valueType = self::VALUE_TYPE_STRING;
	#[ORM\ManyToOne(targetEntity: SiPrefix::class)]
	#[Groups(['default'])]
	private ?SiPrefix $siPrefix = null;
	#[ORM\ManyToOne(targetEntity: SiPrefix::class)]
	#[Groups(['default'])]
	private ?SiPrefix $minSiPrefix = null;
	#[ORM\ManyToOne(targetEntity: SiPrefix::class)]
	#[Groups(['default'])]
	private ?SiPrefix $maxSiPrefix = null;


	public function getNormalizedValue(): ?float
	{
		return $this->normalizedValue;
	}

	public function setNormalizedValue(?float $normalizedValue): self
	{
		$this->normalizedValue = $normalizedValue;
		return $this;
	}

	public function getNormalizedMaxValue(): ?float
	{
		return $this->normalizedMaxValue;
	}

	public function setNormalizedMaxValue(?float $normalizedMaxValue): self
	{
		$this->normalizedMaxValue = $normalizedMaxValue;
		return $this;
	}

	public function getNormalizedMinValue(): ?float
	{
		return $this->normalizedMinValue;
	}

	public function setNormalizedMinValue(?float $normalizedMinValue): self
	{
		$this->normalizedMinValue = $normalizedMinValue;
		return $this;
	}

	public function getStringValue(): string
	{
		return $this->stringValue;
	}

	public function setStringValue(string $stringValue): self
	{
		$this->stringValue = $stringValue;
		return $this;
	}

	public function getValueType(): ?string
	{
		if (!in_array($this->valueType, self::VALUE_TYPES, true)) {
			return self::VALUE_TYPE_NUMERIC;
		}

		return $this->valueType;
	}

	public function setValueType(string $valueType): self
	{
		if (!in_array($valueType, self::VALUE_TYPES, true)) {
			throw new \Exception('Invalid value type given:' . $valueType);
		}
		$this->valueType = $valueType;
		return $this;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(string $description): self
	{
		$this->description = $description;
		return $this;
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

	public function getPart(): ?Part
	{
		return $this->part;
	}

	public function setPart(?Part $part): self
	{
		$this->part = $part;
		return $this;
	}

	protected function recalculateNormalizedValues(): self
	{
		if ($this->getSiPrefix() === null) {
			$this->setNormalizedValue($this->getValue());
		} else {
			$this->setNormalizedValue($this->getSiPrefix()->calculateProduct($this->getValue()));
		}

		if ($this->getMinSiPrefix() === null) {
			$this->setNormalizedMinValue($this->getMinValue());
		} else {
			$this->setNormalizedMinValue($this->getMinSiPrefix()->calculateProduct($this->getMinValue()));
		}

		if ($this->getMaxSiPrefix() === null) {
			$this->setNormalizedMaxValue($this->getMaxValue());
		} else {
			$this->setNormalizedMaxValue($this->getMaxSiPrefix()->calculateProduct($this->getMaxValue()));
		}
		return $this;
	}

	public function getSiPrefix(): ?SiPrefix
	{
		return $this->siPrefix;
	}

	public function setSiPrefix(?SiPrefix $siPrefix): self
	{
		$this->siPrefix = $siPrefix;
		$this->recalculateNormalizedValues();
		return $this;
	}

	public function getValue(): ?float
	{
		return $this->value;
	}

	public function setValue(?float $value): self
	{
		$this->value = $value;
		$this->recalculateNormalizedValues();
		return $this;
	}

	public function getMinSiPrefix(): ?SiPrefix
	{
		return $this->minSiPrefix;
	}

	public function setMinSiPrefix(?SiPrefix $minSiPrefix): self
	{
		$this->minSiPrefix = $minSiPrefix;
		$this->recalculateNormalizedValues();
		return $this;
	}

	public function getMinValue(): ?float
	{
		return $this->minValue;
	}

	public function setMinValue(?float $minValue): self
	{
		$this->minValue = $minValue;
		$this->recalculateNormalizedValues();
		return $this;
	}

	public function getMaxSiPrefix(): ?SiPrefix
	{
		return $this->maxSiPrefix;
	}

	public function setMaxSiPrefix(?SiPrefix $maxSiPrefix): self
	{
		$this->maxSiPrefix = $maxSiPrefix;
		$this->recalculateNormalizedValues();
		return $this;
	}

	public function getMaxValue(): ?float
	{
		return $this->maxValue;
	}

	public function setMaxValue(?float $maxValue): self
	{
		$this->maxValue = $maxValue;
		$this->recalculateNormalizedValues();
		return $this;
	}
}
