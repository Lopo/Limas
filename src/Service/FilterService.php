<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Comparison;
use Limas\Filter\Filter;


readonly class FilterService
{
	public function __construct(private EntityManagerInterface $em)
	{
	}

	/**
	 * Returns a DQL expression for the given filter and alias
	 *
	 * @param Filter $filter The filter to build the expression for
	 * @param string $alias The field alias to search in
	 * @param string $paramName The parameter name you use to bind the value to
	 *
	 * @throws \RuntimeException
	 */
	public function getExpressionForFilter(Filter $filter, string $alias, string $paramName): Comparison
	{
		return match (strtolower($filter->getOperator())) {
			Filter::OPERATOR_EQUALS => $this->em->getExpressionBuilder()->eq($alias, $paramName),
			Filter::OPERATOR_GREATER_THAN => $this->em->getExpressionBuilder()->gt($alias, $paramName),
			Filter::OPERATOR_GREATER_THAN_EQUALS => $this->em->getExpressionBuilder()->gte($alias, $paramName),
			Filter::OPERATOR_LESS_THAN => $this->em->getExpressionBuilder()->lt($alias, $paramName),
			Filter::OPERATOR_LESS_THAN_EQUALS => $this->em->getExpressionBuilder()->lte($alias, $paramName),
			Filter::OPERATOR_NOT_EQUALS => $this->em->getExpressionBuilder()->neq($alias, $paramName),
			Filter::OPERATOR_LIKE => $this->em->getExpressionBuilder()->like($alias, $paramName),
			default => throw new \RuntimeException('Unknown operator ' . $filter->getOperator()),
		};
	}
}
