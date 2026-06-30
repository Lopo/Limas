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
 * Footprint name alias used to canonicalise raw package strings returned by
 * info providers. Distributors spell the same package differently —
 * "SOIC-8" vs "SOIC8" vs "SOIC 8", "LQFP-48(7x7)" vs "LQFP48", etc.
 *
 * Auto-grown over time as new vendor package strings appear:
 *  - on first sight, an alias is inserted with footprint=NULL + verified=false
 *  - admin grid lists `verified=false` rows sorted by usageCount so the
 *    most-frequently-seen "TSSOP-20" / "QFN-48" / etc. are top of mind
 *  - admin assigns a Footprint to the alias, flips verified=true → next
 *    aggregator import resolves immediately
 *
 * `footprint` is nullable specifically to support that pending-state pattern.
 * Canonicalizer treats alias-with-null-footprint as "still a miss" — only
 * fires setFootprint when a verified mapping exists.
 *
 * `aliasNormalized` carries a UNIQUE index so lookups are O(1).
 */
#[ORM\Entity]
#[ORM\Table(name: 'FootprintAlias')]
#[ORM\UniqueConstraint(name: 'UNIQ_footprint_alias_norm', columns: ['aliasNormalized'])]
#[ORM\Index(name: 'IDX_footprint_alias_footprint', columns: ['footprint_id'])]
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
class FootprintAlias
	extends BaseEntity
{
	public const string SOURCE_AUTO = 'auto';
	public const string SOURCE_USER = 'user';
	public const string SOURCE_SEED = 'seed';

	#[ORM\ManyToOne(targetEntity: Footprint::class)]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	#[Groups(['default'])]
	private ?Footprint $footprint = null;
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


	public function __construct(string $alias, string $aliasNormalized, ?Footprint $footprint = null)
	{
		$this->alias = $alias;
		$this->aliasNormalized = $aliasNormalized;
		$this->footprint = $footprint;
		$this->createdAt = new \DateTimeImmutable;
	}

	public function getFootprint(): ?Footprint
	{
		return $this->footprint;
	}

	public function setFootprint(?Footprint $footprint): self
	{
		$this->footprint = $footprint;
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
