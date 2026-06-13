<?php

namespace Limas\Service\Integration\InfoProvider\Dto;


/**
 * One step of a quantity-based price tier ("price break")
 * @example new PriceBreak(quantity: 100, price: 0.05) — 0.05 EUR per piece for orders of 100+
 */
final class PriceBreak
{
	public function __construct(
		public readonly int   $quantity, // minimum quantity at this price tier
		public readonly float $price // per-unit price in the parent result's currency
	)
	{
	}
}
