<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
// Meta-part matching (PartService::getMatchingMetaParts) and the parameter
// pickers filter on `name` + a value comparison. Without these the largest
// table in the DB was full-scanned per criterion. utf8mb4 needs a prefix on
// the VARCHAR columns (764 bytes, same tactic as the categoryPath indexes).
#[ORM\Index(name: 'idx_partparameter_name_normalized', columns: ['name', 'normalizedValue'], options: ['lengths' => [191, null]])]
#[ORM\Index(name: 'idx_partparameter_name_string', columns: ['name', 'stringValue'], options: ['lengths' => [191, 191]])]
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
class PartParameter
	extends BaseEntity
{
	final public const string VALUE_TYPE_STRING = 'string';
	final public const string VALUE_TYPE_NUMERIC = 'numeric';
	final public const array VALUE_TYPES = [self::VALUE_TYPE_STRING, self::VALUE_TYPE_NUMERIC];

	#[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'parameters')]
	private ?Part $part;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $description = '';
	#[ORM\ManyToOne(targetEntity: Unit::class)]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
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
	#[ApiProperty(readableLink: true, writableLink: true)]
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
		$this->setNormalizedValue($this->normalizeWithPrefix($this->getSiPrefix(), $this->getValue()));
		$this->setNormalizedMinValue($this->normalizeWithPrefix($this->getMinSiPrefix(), $this->getMinValue()));
		$this->setNormalizedMaxValue($this->normalizeWithPrefix($this->getMaxSiPrefix(), $this->getMaxValue()));
		return $this;
	}

	/**
	 * A parameter can carry an SI prefix on a slot whose value is null — e.g.
	 * a range-only spec ("180 mm" → maxValue set, value null) where the parser
	 * still attached a prefix to the empty `value` slot. calculateProduct()
	 * takes a non-null float, so guard: a null value normalizes to null
	 * regardless of the prefix, otherwise scale by the prefix when present.
	 */
	private function normalizeWithPrefix(?SiPrefix $prefix, ?float $value): ?float
	{
		if ($value === null) {
			return null;
		}
		return $prefix === null ? $value : $prefix->calculateProduct($value);
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
