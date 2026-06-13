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
 * Vendor parameter rawName → canonical name mapping for the InfoProvider
 * aggregator. Seeded from Octopart's 757-entry attribute taxonomy
 * (`data/parameter-taxonomy/octopart-attributes.json`); auto-grown over time
 * as new vendor rawNames appear.
 *
 * Lookup key is `rawNameNormalized`: lowercased + whitespace-collapsed +
 * trimmed (NOT punctuation-stripped — we want "Tolerance ± %" to stay
 * distinct from "Tolerance"). The corresponding `canonicalName` is what
 * we ultimately write into PartParameter.name.
 *
 * `shortname` is Octopart's stable programmatic ID (e.g. "resistance",
 * "case_package") — kept around so future code can group + i18n by shortname
 * without depending on the human-readable canonicalName text.
 *
 * `verified` flips true once a human (or seed) confirmed the mapping is
 * semantically correct. Aliases created by the auto-discovery path on first
 * sight of a new rawName start with verified=false; admin UI later can
 * promote / merge / split.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ParameterAlias')]
#[ORM\UniqueConstraint(name: 'UNIQ_param_alias_norm', columns: ['rawNameNormalized', 'vendor'])]
#[ORM\Index(name: 'IDX_param_alias_canonical', columns: ['canonicalName'])]
#[ApiResource(
	operations: [
		new GetCollection,
		new Get,
		new Patch,
		new Delete,
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class ParameterAlias
	extends BaseEntity
{
	public const string SOURCE_OCTOPART = 'octopart';
	public const string SOURCE_AUTO = 'auto';
	public const string SOURCE_USER = 'user';
	public const string SOURCE_VENDOR = 'vendor';

	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $rawName;
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $rawNameNormalized;
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $canonicalName;
	#[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
	#[Groups(['default'])]
	private ?string $shortname = null;
	#[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
	#[Groups(['default'])]
	private ?string $vendor = null;
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


	public function __construct(string $rawName, string $canonicalName, ?string $vendor = null)
	{
		$this->rawName = $rawName;
		$this->rawNameNormalized = self::normalize($rawName);
		$this->canonicalName = $canonicalName;
		$this->vendor = $vendor;
		$this->createdAt = new \DateTimeImmutable;
	}

	public static function normalize(string $name): string
	{
		$collapsed = preg_replace('/\s+/u', ' ', trim($name));
		return mb_strtolower($collapsed ?? $name);
	}

	public function getRawName(): string
	{
		return $this->rawName;
	}

	public function getRawNameNormalized(): string
	{
		return $this->rawNameNormalized;
	}

	public function getCanonicalName(): string
	{
		return $this->canonicalName;
	}

	public function setCanonicalName(string $canonicalName): self
	{
		$this->canonicalName = $canonicalName;
		return $this;
	}

	public function getShortname(): ?string
	{
		return $this->shortname;
	}

	public function setShortname(?string $shortname): self
	{
		$this->shortname = $shortname;
		return $this;
	}

	public function getVendor(): ?string
	{
		return $this->vendor;
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
