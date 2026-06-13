<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


/**
 * Manufacturer name alias used to canonicalise raw names returned by info
 * providers. Different sources spell the same manufacturer differently
 * ("onsemi" vs "ONSEMI" vs "ON Semiconductor"); each alias maps to the
 * canonical Manufacturer.
 *
 * `aliasNormalized` is a deterministic lowercased/whitespace-collapsed form
 * (see ManufacturerCanonicalizer::normalize()) and carries a UNIQUE index so
 * lookups are O(1).
 */
#[ORM\Entity]
#[ORM\Table(name: 'ManufacturerAlias')]
#[ORM\UniqueConstraint(name: 'UNIQ_manufacturer_alias_norm', columns: ['aliasNormalized'])]
#[ORM\Index(name: 'IDX_manufacturer_alias_manufacturer', columns: ['manufacturer_id'])]
class ManufacturerAlias
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: Manufacturer::class)]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private Manufacturer $manufacturer;
	/** Raw alias as it came from a provider (preserves original casing) */
	#[ORM\Column(type: Types::STRING)]
	private string $alias;
	/** Deterministic normalised form for lookup */
	#[ORM\Column(type: Types::STRING)]
	private string $aliasNormalized;


	public function __construct(Manufacturer $manufacturer, string $alias, string $aliasNormalized)
	{
		$this->manufacturer = $manufacturer;
		$this->alias = $alias;
		$this->aliasNormalized = $aliasNormalized;
	}

	public function getManufacturer(): Manufacturer
	{
		return $this->manufacturer;
	}

	public function getAlias(): string
	{
		return $this->alias;
	}

	public function getAliasNormalized(): string
	{
		return $this->aliasNormalized;
	}
}
