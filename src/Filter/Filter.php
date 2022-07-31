<?php

namespace Limas\Filter;


class Filter
	implements AssociationPropertyInterface
{
	use AssociationPropertyTrait;

	public const TYPE_AND = 'and';
	public const TYPE_OR = 'or';
	public const OPERATOR_LESS_THAN = '<';
	public const OPERATOR_GREATER_THAN = '>';
	public const OPERATOR_EQUALS = '=';
	public const OPERATOR_GREATER_THAN_EQUALS = '>=';
	public const OPERATOR_LESS_THAN_EQUALS = '<=';
	public const OPERATOR_NOT_EQUALS = '!=';
	public const OPERATOR_IN = 'in';
	public const OPERATOR_LIKE = 'like';
	public const OPERATORS = [
		self::OPERATOR_LESS_THAN,
		self::OPERATOR_GREATER_THAN,
		self::OPERATOR_EQUALS,
		self::OPERATOR_GREATER_THAN_EQUALS,
		self::OPERATOR_LESS_THAN_EQUALS,
		self::OPERATOR_NOT_EQUALS,
		self::OPERATOR_IN,
		self::OPERATOR_LIKE,
	];
	public const TYPES = [
		self::TYPE_AND,
		self::TYPE_OR,
	];

	private string $type;
	private string $operator;
	private mixed $value;
	private array $subFilters;


	public function __construct(string $type = self::TYPE_AND)
	{
		$this->setType($type);
		$this->setSubFilters([]);
	}

	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @throws \Exception
	 */
	public function setType(string $type): self
	{
		if (!in_array($type, self::TYPES, true)) {
			throw new \Exception("Invalid type $type");
		}
		$this->type = $type;
		return $this;
	}

	public function getOperator(): string
	{
		return $this->operator;
	}

	/**
	 * @throws \Exception Thrown if an invalid operator was passed
	 */
	public function setOperator(string $operator): self
	{
		if (!in_array(strtolower($operator), self::OPERATORS, true)) {
			throw new \Exception("Invalid operator $operator");
		}
		$this->operator = strtolower($operator);
		return $this;
	}

	public function getValue(): mixed
	{
		return $this->value;
	}

	public function setValue(mixed $value): self
	{
		$this->value = $value;
		return $this;
	}

	public function getSubFilters(): array
	{
		return $this->subFilters;
	}

	public function setSubFilters(array $subFilters): self
	{
		$this->subFilters = $subFilters;
		return $this;
	}

	public function hasSubFilters(): bool
	{
		return count($this->subFilters) > 0;
	}
}
