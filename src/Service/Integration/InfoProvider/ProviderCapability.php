<?php

namespace Limas\Service\Integration\InfoProvider;


/**
 * Capability flags an InfoProvider can declare for itself. The aggregator
 * surface (CLI / REST) uses them to tell consumers what kinds of data a given
 * provider is expected to deliver — so the UI can hide features that the
 * picked provider will never return.
 *
 * Capabilities are *informational*: a provider may still occasionally fail to
 * supply a declared kind for a specific part. Aggregator does not gate runtime
 * behaviour on them.
 *
 * Set mirrors Part-DB ProviderCapabilities (their pattern). PRICE and PARAMETERS
 * are practically mandatory for the aggregator use case; the rest are optional.
 */
enum ProviderCapability: string
{
	case BASIC = 'basic';              // manufacturer + MPN + description
	case PICTURE = 'picture';          // product photo URL
	case DATASHEET = 'datasheet';      // PDF datasheet URL
	case PRICE = 'price';              // pricing data with quantity breaks
	case FOOTPRINT = 'footprint';      // package/case info (e.g. "0805", "TO-220")
	case GTIN = 'gtin';                // global trade item number / EAN / UPC
	case PARAMETERS = 'parameters';    // technical spec parameters

	/**
	 * Stable order for UI display.
	 */
	public function orderIndex(): int
	{
		return match ($this) {
			self::BASIC => 1,
			self::PICTURE => 2,
			self::DATASHEET => 3,
			self::PRICE => 4,
			self::FOOTPRINT => 5,
			self::GTIN => 6,
			self::PARAMETERS => 7
		};
	}
}
