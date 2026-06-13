<?php

namespace Limas\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Service\Integration\InfoProvider\Enum\BulkImportDuplicatesBehavior;
use Limas\Service\Integration\InfoProvider\Enum\BulkImportJobStatus;
use Symfony\Component\Serializer\Attribute\Groups;


/**
 * One bulk-import run. Created by the operator via the FE dialog after
 * they upload a CSV, pick default Category + Storage, and confirm the
 * column mapping. The worker CLI (`limas:bulk-import:run <id>`) iterates
 * the pending BulkImportJobItem children, processes each via the
 * standard aggregator search → AggregatorImporter::import flow, and
 * updates per-item status as it goes.
 *
 * Status progression:
 *   pending → running → completed (all items succeeded/skipped)
 *                     → partial   (some items failed/ambiguous)
 *                     → failed    (worker itself blew up)
 */
#[ORM\Entity]
#[ORM\Table(name: 'BulkImportJob')]
class BulkImportJob
	extends BaseEntity
{
	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	#[Groups(['default'])]
	private \DateTimeInterface $createdAt;
	#[ORM\ManyToOne(targetEntity: User::class)]
	#[ORM\JoinColumn(name: 'createdBy_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
	#[Groups(['default'])]
	private ?User $createdBy = null;
	#[ORM\Column(type: Types::STRING, length: 20, enumType: BulkImportJobStatus::class)]
	#[Groups(['default'])]
	private BulkImportJobStatus $status = BulkImportJobStatus::Pending;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $totalRows = 0;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $processedRows = 0;
	/**
	 * Fallback category for rows whose CSV `category` column is empty or doesn't resolve to an existing PartCategory
	 */
	#[ORM\ManyToOne(targetEntity: PartCategory::class)]
	#[ORM\JoinColumn(name: 'defaultCategory_id', referencedColumnName: 'id', nullable: false)]
	#[Groups(['default'])]
	private PartCategory $defaultCategory;
	#[ORM\ManyToOne(targetEntity: StorageLocation::class)]
	#[ORM\JoinColumn(name: 'defaultStorage_id', referencedColumnName: 'id', nullable: false)]
	#[Groups(['default'])]
	private StorageLocation $defaultStorage;
	#[ORM\Column(type: Types::STRING, length: 20, enumType: BulkImportDuplicatesBehavior::class)]
	#[Groups(['default'])]
	private BulkImportDuplicatesBehavior $duplicatesBehavior = BulkImportDuplicatesBehavior::Skip;
	/** @var Collection<int, BulkImportJobItem> */
	#[ORM\OneToMany(targetEntity: BulkImportJobItem::class, mappedBy: 'job', cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $items;


	public function __construct()
	{
		$this->createdAt = new \DateTime;
		$this->items = new ArrayCollection;
	}

	public function getCreatedAt(): \DateTimeInterface
	{
		return $this->createdAt;
	}

	public function getCreatedBy(): ?User
	{
		return $this->createdBy;
	}

	public function setCreatedBy(?User $u): self
	{
		$this->createdBy = $u;
		return $this;
	}

	public function getStatus(): BulkImportJobStatus
	{
		return $this->status;
	}

	public function setStatus(BulkImportJobStatus $s): self
	{
		$this->status = $s;
		return $this;
	}

	public function getTotalRows(): int
	{
		return $this->totalRows;
	}

	public function setTotalRows(int $n): self
	{
		$this->totalRows = $n;
		return $this;
	}

	public function getProcessedRows(): int
	{
		return $this->processedRows;
	}

	public function setProcessedRows(int $n): self
	{
		$this->processedRows = $n;
		return $this;
	}

	public function incrementProcessedRows(): self
	{
		$this->processedRows++;
		return $this;
	}

	public function getDefaultCategory(): PartCategory
	{
		return $this->defaultCategory;
	}

	public function setDefaultCategory(PartCategory $c): self
	{
		$this->defaultCategory = $c;
		return $this;
	}

	public function getDefaultStorage(): StorageLocation
	{
		return $this->defaultStorage;
	}

	public function setDefaultStorage(StorageLocation $s): self
	{
		$this->defaultStorage = $s;
		return $this;
	}

	public function getDuplicatesBehavior(): BulkImportDuplicatesBehavior
	{
		return $this->duplicatesBehavior;
	}

	public function setDuplicatesBehavior(BulkImportDuplicatesBehavior $b): self
	{
		$this->duplicatesBehavior = $b;
		return $this;
	}

	/** @return Collection<int, BulkImportJobItem> */
	public function getItems(): Collection
	{
		return $this->items;
	}

	public function addItem(BulkImportJobItem $item): self
	{
		if (!$this->items->contains($item)) {
			$this->items->add($item);
			$item->setJob($this);
		}
		return $this;
	}
}
