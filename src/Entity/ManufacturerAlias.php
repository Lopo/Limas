<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


/**
 * Manufacturer name alias used to canonicalise raw names returned by info
 * providers. Different sources spell the same manufacturer differently
 * ("onsemi" vs "ONSEMI" vs "ON Semiconductor"); each alias maps to the
 * canonical Manufacturer.
 *
 * Auto-grown over time as new vendor manufacturer strings appear:
 *  - on first sight, an alias is inserted with manufacturer=NULL + verified=false
 *  - admin grid lists `verified=false` rows sorted by usageCount so the
 *    most-frequently-seen pending aliases surface at the top
 *  - admin assigns a Manufacturer to the alias, flips verified=true → next
 *    aggregator import resolves immediately
 *
 * `manufacturer` is nullable specifically to support that pending-state
 * pattern. Canonicalizer treats alias-with-null-manufacturer as "still a
 * miss" — only returns a Manufacturer when a verified mapping exists.
 *
 * `aliasNormalized` carries a UNIQUE index so lookups are O(1).
 */
#[ORM\Entity]
#[ORM\Table(name: 'ManufacturerAlias')]
#[ORM\UniqueConstraint(name: 'UNIQ_manufacturer_alias_norm', columns: ['aliasNormalized'])]
#[ORM\Index(name: 'IDX_manufacturer_alias_manufacturer', columns: ['manufacturer_id'])]
#[ApiResource(
	operations: [
		new GetCollection,
		new Get,
		new Patch,
		new Delete
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class ManufacturerAlias
	extends BaseEntity
{
	public const string SOURCE_AUTO = 'auto';
	public const string SOURCE_USER = 'user';
	public const string SOURCE_SEED = 'seed';

	#[ORM\ManyToOne(targetEntity: Manufacturer::class)]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	#[Groups(['default'])]
	private ?Manufacturer $manufacturer = null;
	/** Raw alias as it came from a provider (preserves original casing) */
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $alias;
	/** Deterministic normalised form for lookup */
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $aliasNormalized;
	#[ORM\Column(type: Types::STRING, length: 16)]
	#[Groups(['default'])]
	private string $source = self::SOURCE_AUTO;
	#[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
	#[Groups(['default'])]
	private int $usageCount = 0;
	#[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
	#[Groups(['default'])]
	private bool $verified = false;
	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private \DateTimeImmutable $createdAt;


	public function __construct(string $alias, string $aliasNormalized, ?Manufacturer $manufacturer = null)
	{
		$this->alias = $alias;
		$this->aliasNormalized = $aliasNormalized;
		$this->manufacturer = $manufacturer;
		$this->createdAt = new \DateTimeImmutable;
	}

	public function getManufacturer(): ?Manufacturer
	{
		return $this->manufacturer;
	}

	public function setManufacturer(?Manufacturer $manufacturer): self
	{
		$this->manufacturer = $manufacturer;
		return $this;
	}

	public function getAlias(): string
	{
		return $this->alias;
	}

	public function getAliasNormalized(): string
	{
		return $this->aliasNormalized;
	}

	public function getSource(): string
	{
		return $this->source;
	}

	public function setSource(string $source): self
	{
		$this->source = $source;
		return $this;
	}

	public function getUsageCount(): int
	{
		return $this->usageCount;
	}

	public function incrementUsageCount(): self
	{
		$this->usageCount++;
		return $this;
	}

	public function isVerified(): bool
	{
		return $this->verified;
	}

	public function setVerified(bool $verified): self
	{
		$this->verified = $verified;
		return $this;
	}

	public function getCreatedAt(): \DateTimeImmutable
	{
		return $this->createdAt;
	}
}
