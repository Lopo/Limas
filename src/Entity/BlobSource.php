<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;


/**
 * Provenance row for a Blob. One row per distinct (Blob, sourceUrl) pair —
 * the same physical PDF can have come from Farnell and DigiKey and Mouser
 * over time; each fetch leaves its own BlobSource so the UI can show
 * "this datasheet came from N distributors".
 *
 * `adapter` is the aggregator source key ('farnell', 'digikey', 'lcsc', …)
 * or null for user-uploaded blobs that never went through an adapter.
 */
#[ORM\Entity]
#[ORM\Table(name: 'BlobSource')]
#[ORM\UniqueConstraint(name: 'UNIQ_blob_source_url', columns: ['blob_id', 'sourceUrl'], options: ['lengths' => [null, 512]])]
class BlobSource
	extends BaseEntity
{
	/**
	 * Hard-ignored in serialisation: would recurse Blob ↔ BlobSource and
	 * trip Symfony's circular-reference guard. The reverse direction (Blob
	 * → sources) is also kept off the default group for the same reason.
	 */
	#[ORM\ManyToOne(targetEntity: Blob::class, inversedBy: 'sources')]
	#[ORM\JoinColumn(name: 'blob_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
	#[Ignore]
	private ?Blob $blob = null;
	#[ORM\Column(type: Types::STRING, length: 2048)]
	#[Groups(['default'])]
	private string $sourceUrl;
	#[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
	#[Groups(['default'])]
	private ?string $adapter = null;
	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
	#[Groups(['default'])]
	private \DateTimeInterface $createdAt;


	public function __construct()
	{
		$this->createdAt = new \DateTime;
	}

	public function getBlob(): ?Blob
	{
		return $this->blob;
	}

	public function setBlob(?Blob $blob): self
	{
		$this->blob = $blob;
		return $this;
	}

	public function getSourceUrl(): string
	{
		return $this->sourceUrl;
	}

	public function setSourceUrl(string $sourceUrl): self
	{
		$this->sourceUrl = $sourceUrl;
		return $this;
	}

	public function getAdapter(): ?string
	{
		return $this->adapter;
	}

	public function setAdapter(?string $adapter): self
	{
		$this->adapter = $adapter;
		return $this;
	}

	public function getCreatedAt(): \DateTimeInterface
	{
		return $this->createdAt;
	}

	public function setCreatedAt(\DateTimeInterface $createdAt): self
	{
		$this->createdAt = $createdAt;
		return $this;
	}
}
