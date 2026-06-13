<?php

namespace Limas\Filter;


class Filter
	implements AssociationPropertyInterface
{
	use AssociationPropertyTrait;

	public const string TYPE_AND = 'and';
	public const string TYPE_OR = 'or';
	public const string OPERATOR_LESS_THAN = '<';
	public const string OPERATOR_GREATER_THAN = '>';
	public const string OPERATOR_EQUALS = '=';
	public const string OPERATOR_GREATER_THAN_EQUALS = '>=';
	public const string OPERATOR_LESS_THAN_EQUALS = '<=';
	public const string OPERATOR_NOT_EQUALS = '!=';
	public const string OPERATOR_IN = 'in';
	public const string OPERATOR_LIKE = 'like';
	public const array OPERATORS = [
		self::OPERATOR_LESS_THAN,
		self::OPERATOR_GREATER_THAN,
		self::OPERATOR_EQUALS,
		self::OPERATOR_GREATER_THAN_EQUALS,
		self::OPERATOR_LESS_THAN_EQUALS,
		self::OPERATOR_NOT_EQUALS,
		self::OPERATOR_IN,
		self::OPERATOR_LIKE,
	];
	public const array TYPES = [
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
	 * @throws \RuntimeException
	 */
	public function setType(string $type): self
	{
		if (!in_array($type, self::TYPES, true)) {
			throw new \RuntimeException("Invalid type $type");
		}
		$this->type = $type;
		return $this;
	}

	public function getOperator(): string
	{
		return $this->operator;
	}

	/**
	 * @throws \RuntimeException Thrown if an invalid operator was passed
	 */
	public function setOperator(string $operator): self
	{
		if (!in_array(strtolower($operator), self::OPERATORS, true)) {
			throw new \RuntimeException("Invalid operator $operator");
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
