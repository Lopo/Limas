<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Service\Integration\InfoProvider\Enum\BulkImportItemStatus;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;


/**
 * One CSV row inside a BulkImportJob
 *
 * Status semantics (set by the worker after attempting to import):
 *   - success    — Part created cleanly
 *   - warning    — Part created, but a CSV override (category/storage)
 *                  didn't resolve and the job default kicked in
 *   - skipped    — ExistingPartFinder matched an existing Part; `existingPart`
 *                  FK links to it. With `duplicatesBehavior=Skip` (default)
 *                  no further action.
 *   - ambiguous  — multiple aggregator candidates match the (mfr, mpn)
 *                  given; operator must disambiguate by re-running with
 *                  a more specific Manufacturer column
 *   - failed     — aggregator found nothing, or import itself blew up
 *                  (the `errorMessage` captures details)
 */
#[ORM\Entity]
#[ORM\Table(name: 'BulkImportJobItem')]
class BulkImportJobItem
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: BulkImportJob::class, inversedBy: 'items')]
	#[ORM\JoinColumn(name: 'job_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
	#[Ignore]
	private BulkImportJob $job;
	/** 1-based row number in the source CSV (after header). */
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $line;
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $rawMpn = '';
	#[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
	#[Groups(['default'])]
	private ?string $rawManufacturer = null;
	#[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
	#[Groups(['default'])]
	private ?string $rawCategory = null;
	#[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
	#[Groups(['default'])]
	private ?string $rawStorage = null;
	/**
	 * Raw quantity cell from the CSV (only used in UpdateStock duplicates
	 * mode). Stored verbatim — the worker parses to int at processing time
	 * so we can surface "couldn't parse '12 pcs' as a number" warnings
	 * instead of silently failing.
	 */
	#[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
	#[Groups(['default'])]
	private ?string $rawQuantity = null;
	/**
	 * Stock the worker actually applied — for UpdateStock rows this is the
	 * quantity that landed in the StockEntry (either incremented to an
	 * existing Part or initial-stock on a freshly-created one). Null when
	 * not applicable (Skip / CreateAnyway modes, or UpdateStock rows that
	 * fell back to Skip because quantity wouldn't parse).
	 */
	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	#[Groups(['default'])]
	private ?int $quantityApplied = null;
	#[ORM\Column(type: Types::STRING, length: 20, enumType: BulkImportItemStatus::class)]
	#[Groups(['default'])]
	private BulkImportItemStatus $status = BulkImportItemStatus::Pending;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $errorMessage = null;
	/** Part this row created (success / warning). */
	#[ORM\ManyToOne(targetEntity: Part::class)]
	#[ORM\JoinColumn(name: 'part_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
	#[Groups(['default'])]
	private ?Part $part = null;
	/** Part this row found already in inventory (skipped). */
	#[ORM\ManyToOne(targetEntity: Part::class)]
	#[ORM\JoinColumn(name: 'existingPart_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
	#[Groups(['default'])]
	private ?Part $existingPart = null;


	public function getJob(): BulkImportJob
	{
		return $this->job;
	}

	public function setJob(BulkImportJob $job): self
	{
		$this->job = $job;
		return $this;
	}

	public function getLine(): int
	{
		return $this->line;
	}

	public function setLine(int $line): self
	{
		$this->line = $line;
		return $this;
	}

	public function getRawMpn(): string
	{
		return $this->rawMpn;
	}

	public function setRawMpn(string $mpn): self
	{
		$this->rawMpn = $mpn;
		return $this;
	}

	public function getRawManufacturer(): ?string
	{
		return $this->rawManufacturer;
	}

	public function setRawManufacturer(?string $m): self
	{
		$this->rawManufacturer = $m;
		return $this;
	}

	public function getRawCategory(): ?string
	{
		return $this->rawCategory;
	}

	public function setRawCategory(?string $c): self
	{
		$this->rawCategory = $c;
		return $this;
	}

	public function getRawStorage(): ?string
	{
		return $this->rawStorage;
	}

	public function setRawStorage(?string $s): self
	{
		$this->rawStorage = $s;
		return $this;
	}

	public function getRawQuantity(): ?string
	{
		return $this->rawQuantity;
	}

	public function setRawQuantity(?string $q): self
	{
		$this->rawQuantity = $q;
		return $this;
	}

	public function getQuantityApplied(): ?int
	{
		return $this->quantityApplied;
	}

	public function setQuantityApplied(?int $q): self
	{
		$this->quantityApplied = $q;
		return $this;
	}

	public function getStatus(): BulkImportItemStatus
	{
		return $this->status;
	}

	public function setStatus(BulkImportItemStatus $s): self
	{
		$this->status = $s;
		return $this;
	}

	public function getErrorMessage(): ?string
	{
		return $this->errorMessage;
	}

	public function setErrorMessage(?string $msg): self
	{
		$this->errorMessage = $msg;
		return $this;
	}

	public function getPart(): ?Part
	{
		return $this->part;
	}

	public function setPart(?Part $p): self
	{
		$this->part = $p;
		return $this;
	}

	public function getExistingPart(): ?Part
	{
		return $this->existingPart;
	}

	public function setExistingPart(?Part $p): self
	{
		$this->existingPart = $p;
		return $this;
	}
}
