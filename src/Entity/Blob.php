<?php

namespace Limas\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


/**
 * Content-addressable file blob. One row per unique (sha256, size) pair —
 * physical file on disk lives at `<sha-prefix>/<sha256>` inside the `blob`
 * Gaufrette pool. Owning attachments (PartAttachment, ProjectAttachment, …)
 * hold a FK to Blob; many attachments across many parts can share one Blob.
 *
 * Blob is deleted only when its last referring attachment is removed (see
 * UploadedFileService::delete + AttachmentHashCommand --prune-orphans).
 */
#[ORM\Entity]
// `Blob` is a MySQL reserved word — Doctrine doesn't backtick-quote table
// references in DQL→SQL, so we use a distinct, safe name. PHP class stays
// `Blob` (no friction in application code).
#[ORM\Table(name: 'attachment_blob')]
#[ORM\UniqueConstraint(name: 'UNIQ_blob_sha256_size', columns: ['sha256', 'size'])]
class Blob
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, length: 64)]
	#[Groups(['default'])]
	private string $sha256;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $size;
	/**
	 * Storage-relative path under the `blob` pool: `<sha-prefix-2>/<sha256>`,
	 * e.g. `ab/abc123…`. Two-char prefix subdir avoids directory bloat past
	 * a few thousand blobs.
	 */
	#[ORM\Column(type: Types::STRING, length: 80)]
	private string $filename;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $mimetype;
	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
	#[Groups(['default'])]
	private \DateTimeInterface $createdAt;
	/**
	 * @var Collection<int, BlobSource>
	 *
	 * Intentionally NOT in the `default` serialisation group — the
	 * Symfony serializer would recurse Blob → sources → BlobSource →
	 * blob → … and trip the circular-reference guard. The flat list of
	 * source URLs is exposed via `UploadedFile::getSourceUrls()` which
	 * is all the frontend needs.
	 */
	#[ORM\OneToMany(targetEntity: BlobSource::class, mappedBy: 'blob', cascade: ['persist', 'remove'], orphanRemoval: true)]
	private Collection $sources;


	public function __construct()
	{
		$this->createdAt = new \DateTime;
		$this->sources = new ArrayCollection;
	}

	public function getSha256(): string
	{
		return $this->sha256;
	}

	public function setSha256(string $sha256): self
	{
		$this->sha256 = $sha256;
		return $this;
	}

	public function getSize(): int
	{
		return $this->size;
	}

	public function setSize(int $size): self
	{
		$this->size = $size;
		return $this;
	}

	public function getFilename(): string
	{
		return $this->filename;
	}

	public function setFilename(string $filename): self
	{
		$this->filename = $filename;
		return $this;
	}

	public function getMimetype(): string
	{
		return $this->mimetype;
	}

	public function setMimetype(string $mimetype): self
	{
		$this->mimetype = $mimetype;
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

	/** @return Collection<int, BlobSource> */
	public function getSources(): Collection
	{
		return $this->sources;
	}

	public function addSource(BlobSource $source): self
	{
		if (!$this->sources->contains($source)) {
			$this->sources->add($source);
			$source->setBlob($this);
		}
		return $this;
	}

	public function removeSource(BlobSource $source): self
	{
		if ($this->sources->removeElement($source)) {
			if ($source->getBlob() === $this) {
				$source->setBlob(null);
			}
		}
		return $this;
	}
}
