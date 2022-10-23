<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\PartActions;
use Limas\Annotation\UploadedFileCollection;
use Limas\Exceptions\CategoryNotAssignedException;
use Limas\Exceptions\MinStockLevelOutOfRangeException;
use Limas\Exceptions\StorageLocationNotAssignedException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
	collectionOperations: [
		'get' => [
			'controller' => PartActions::class . '::GetPartsAction'
		],
		'post' => [
			'controller' => PartActions::class . '::PartPostAction'
		],
		'parameterNames' => [
			'method' => 'get',
			'path' => 'parts/getPartParameterNames',
			'controller' => PartActions::class . '::getParameterNamesAction',
			'deserialize' => false
		],
		'parameterValues' => [
			'method' => 'get',
			'path' => 'parts/getPartParameterValues',
			'controller' => PartActions::class . '::getParameterValuesAction',
			'deserialize' => false
		]
	],
	itemOperations: [
		'get',
		'put' => [
			'controller' => PartActions::class . '::PartPutAction',
			'deserialize' => false
		],
		'delete',
		'add_stock' => [
			'method' => 'put',
			'path' => 'parts/{id}/addStock',
			'controller' => PartActions::class . '::AddStockAction'
		],
		'remove_stock' => [
			'method' => 'put',
			'path' => 'parts/{id}/removeStock',
			'controller' => PartActions::class . '::RemoveStockAction'
		],
		'set_stock' => [
			'method' => 'put',
			'path' => 'parts/{id}/setStock',
			'controller' => PartActions::class . '::SetStockAction'
		]
	],
	denormalizationContext: ['groups' => ['default', 'stock']],
	normalizationContext: ['groups' => ['default', 'readonly']]
)]
class Part
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: PartCategory::class)]
	#[Assert\NotNull]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private ?PartCategory $category = null;
	#[ORM\Column(type: Types::STRING)]
	#[Assert\NotBlank]
	#[Groups(['default'])]
	private string $name;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $description = null;
	#[ORM\ManyToOne(targetEntity: Footprint::class)]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private ?Footprint $footprint = null;
	#[ORM\ManyToOne(targetEntity: PartMeasurementUnit::class, inversedBy: 'parts')]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private ?PartMeasurementUnit $partUnit = null;
	#[ORM\ManyToOne(targetEntity: StorageLocation::class)]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private ?StorageLocation $storageLocation = null;
	/** @var Collection<PartManufacturer> */
	#[ORM\OneToMany(mappedBy: 'part', targetEntity: PartManufacturer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $manufacturers;
	/** @var Collection<PartDistributor> */
	#[ORM\OneToMany(mappedBy: 'part', targetEntity: PartDistributor::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $distributors;
	/** @var Collection<PartAttachment> */
	#[ORM\OneToMany(mappedBy: 'part', targetEntity: PartAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	#[UploadedFileCollection]
	private Collection $attachments;
	#[ORM\Column(type: Types::TEXT)]
	#[Groups(['default'])]
	private string $comment = '';
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['readonly'])]
	private int $stockLevel = 0;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $minStockLevel = 0;
	#[ORM\Column(type: Types::DECIMAL, precision: 13, scale: 4)]
	#[Groups(['readonly'])]
	private string $averagePrice = '0';
	/** @var Collection<StockEntry> */
	#[ORM\OneToMany(mappedBy: 'part', targetEntity: StockEntry::class, cascade: ['persist', 'remove'])]
	#[Groups(['stock'])]
	private Collection $stockLevels;
	/** @var Collection<PartParameter> */
	#[ORM\OneToMany(mappedBy: 'part', targetEntity: PartParameter::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private Collection $parameters;
	/** @var Collection<MetaPartParameterCriteria> */
	#[ORM\OneToMany(mappedBy: 'part', targetEntity: MetaPartParameterCriteria::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $metaPartParameterCriterias;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $status;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $needsReview = false;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $partCondition;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $productionRemarks;
	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
	#[Groups(['readonly'])]
	private ?\DateTimeInterface $createDate = null;
	/** @var Collection<ProjectPart> */
	#[ORM\OneToMany(mappedBy: 'part', targetEntity: ProjectPart::class)]
	private Collection $projectParts;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $internalPartNumber = null;
	#[ORM\Column(type: Types::BOOLEAN, nullable: false)]
	#[Groups(['readonly'])]
	private bool $removals = false;
	#[ORM\Column(type: Types::BOOLEAN, nullable: false)]
	#[Groups(['readonly'])]
	private bool $lowStock = false;
	#[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
	#[Groups(['default'])]
	private bool $metaPart = false;
	#[Groups(['default'])]
	private array $metaPartMatches;


	public function __construct()
	{
		$this->manufacturers = new ArrayCollection;
		$this->distributors = new ArrayCollection;
		$this->attachments = new ArrayCollection;
		$this->stockLevels = new ArrayCollection;
		$this->parameters = new ArrayCollection;
		$this->metaPartParameterCriterias = new ArrayCollection;
		$this->projectParts = new ArrayCollection;
		$this->setCreateDate(new \DateTime);
	}

	public function setCreateDate(?\DateTimeInterface $createDate): self
	{
		$this->createDate = $createDate;
		return $this;
	}

	public function getProductionRemarks(): ?string
	{
		return $this->productionRemarks;
	}

	public function setProductionRemarks(?string $productionRemarks): self
	{
		$this->productionRemarks = $productionRemarks;
		return $this;
	}

	public function getMetaPartMatches(): array
	{
		return $this->metaPartMatches;
	}

	public function setMetaPartMatches(array $metaPartMatches): self
	{
		$this->metaPartMatches = $metaPartMatches;
		return $this;
	}

	public function isLowStock(): bool
	{
		return $this->lowStock;
	}

	public function setLowStock(bool $lowStock): self
	{
		$this->lowStock = $lowStock;
		return $this;
	}

	public function hasRemovals(): mixed
	{
		return $this->removals;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getInternalPartNumber(): ?string
	{
		return $this->internalPartNumber;
	}

	public function setInternalPartNumber(?string $internalPartNumber): self
	{
		$this->internalPartNumber = $internalPartNumber;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;
		return $this;
	}

	public function getPartUnit(): ?PartMeasurementUnit
	{
		return $this->partUnit;
	}

	public function setPartUnit(?PartMeasurementUnit $partUnit): self
	{
		$this->partUnit = $partUnit;
		return $this;
	}

	public function getNeedsReview(): ?bool
	{
		return $this->needsReview;
	}

	public function setNeedsReview(bool $needsReview): self
	{
		$this->needsReview = $needsReview;
		return $this;
	}

	public function getPartCondition(): ?string
	{
		return $this->partCondition;
	}

	public function setPartCondition(?string $partCondition): self
	{
		$this->partCondition = $partCondition;
		return $this;
	}

	public function getCategoryPath(): string
	{
		return $this->category !== null
			? $this->category->getCategoryPath()
			: '';
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

	public function getComment(): ?string
	{
		return $this->comment;
	}

	public function setComment(string $comment): self
	{
		$this->comment = $comment;
		return $this;
	}

	public function getDistributors(): Collection
	{
		return $this->distributors;
	}

	public function getAttachments(): Collection
	{
		return $this->attachments;
	}

	public function getManufacturers(): Collection
	{
		return $this->manufacturers;
	}

	public function getParameters(): Collection
	{
		return $this->parameters;
	}

	public function getMetaPartParameterCriterias(): Collection
	{
		return $this->metaPartParameterCriterias;
	}

	public function getCreateDate(): ?\DateTimeInterface
	{
		return $this->createDate;
	}

	public function getStatus(): ?string
	{
		return $this->status;
	}

	public function setStatus(?string $status): self
	{
		$this->status = $status;
		return $this;
	}

	#[ORM\PrePersist]
	public function onPrePersist(): void
	{
		$this->executeSaveListener();
	}

	public function executeSaveListener(): void
	{
		$this->checkCategoryConsistency();
		$this->checkStorageLocationConsistency();
	}

	private function checkCategoryConsistency(): void
	{
		if ($this->getCategory() === null) {
			throw new CategoryNotAssignedException;
		}
	}

	public function getCategory(): ?PartCategory
	{
		return $this->category;
	}

	public function setCategory(?PartCategory $category): self
	{
		$this->category = $category;
		return $this;
	}

	private function checkStorageLocationConsistency(): void
	{
		if ($this->getStorageLocation() === null && !$this->isMetaPart()) {
			throw new StorageLocationNotAssignedException;
		}
	}

	public function getStorageLocation(): ?StorageLocation
	{
		return $this->storageLocation;
	}

	public function setStorageLocation(?StorageLocation $storageLocation = null): self
	{
		$this->storageLocation = $storageLocation;
		return $this;
	}

	public function isMetaPart(): ?bool
	{
		return $this->metaPart;
	}

	public function setMetaPart(bool $metaPart): self
	{
		$this->metaPart = $metaPart;
		return $this;
	}

	public function setRemovals(bool $removals = false): self
	{
		$this->removals = $removals;
		return $this;
	}

	public function getAveragePrice(): ?string
	{
		return $this->averagePrice;
	}

	public function setAveragePrice(string $averagePrice): self
	{
		$this->averagePrice = $averagePrice;
		return $this;
	}

	#[ORM\PreUpdate]
	public function onPreUpdate(): void
	{
		$this->executeSaveListener();
	}

	public function addStockLevel(StockEntry $stockEntry): self
	{
		$stockEntry->setPart($this);
		$this->stockLevels->add($stockEntry);
		return $this;
	}

	public function removeStockLevel(StockEntry $stockEntry): self
	{
		$stockEntry->setPart(null);
		$this->stockLevels->removeElement($stockEntry);
		return $this;
	}

	public function addParameter(PartParameter $partParameter): self
	{
		$partParameter->setPart($this);
		$this->parameters->add($partParameter);
		return $this;
	}

	public function removeParameter(PartParameter $partParameter): self
	{
		$partParameter->setPart(null);
		$this->parameters->removeElement($partParameter);
		return $this;
	}

	public function addMetaPartParameterCriteria(MetaPartParameterCriteria $metaPartParameterCriteria): self
	{
		$metaPartParameterCriteria->setPart($this);
		$this->metaPartParameterCriterias->add($metaPartParameterCriteria);
		return $this;
	}

	public function removeMetaPartParameterCriteria(MetaPartParameterCriteria $metaPartParameterCriteria): self
	{
		$metaPartParameterCriteria->setPart(null);
		$this->metaPartParameterCriterias->removeElement($metaPartParameterCriteria);
		return $this;
	}

	public function addAttachment(PartAttachment $partAttachment): self
	{
		$partAttachment->setPart($this);
		$this->attachments->add($partAttachment);
		return $this;
	}

	public function removeAttachment(PartAttachment $partAttachment): self
	{
		$partAttachment->setPart(null);
		$this->attachments->removeElement($partAttachment);
		return $this;
	}

	public function addManufacturer(PartManufacturer $partManufacturer): self
	{
		$partManufacturer->setPart($this);
		$this->manufacturers->add($partManufacturer);
		return $this;
	}

	public function removeManufacturer(PartManufacturer $partManufacturer): self
	{
		$partManufacturer->setPart(null);
		$this->manufacturers->removeElement($partManufacturer);
		return $this;
	}

	public function addDistributor(PartDistributor $partDistributor): self
	{
		$partDistributor->setPart($this);
		$this->distributors->add($partDistributor);
		return $this;
	}

	public function removeDistributor(PartDistributor $partDistributor): self
	{
		$partDistributor->setPart(null);
		$this->distributors->removeElement($partDistributor);
		return $this;
	}

	public function getProjectParts(): Collection
	{
		return $this->projectParts;
	}

	#[Groups(['default'])]
	public function getProjectNames(): array
	{
		$projectNames = [];
		foreach ($this->projectParts as $projectPart) {
			if ($projectPart->getProject() instanceof Project) {
				$projectNames[] = $projectPart->getProject()->getName();
			}
		}

		return array_unique($projectNames);
	}

	public function recomputeStockLevels(): self
	{
		$currentStock = $avgPrice = 0;
		$totalPartStockPrice = $lastPosEntryQuant = $lastPosEntryPrice = $negativeStock = 0;

		foreach ($this->getStockLevels() as $stockLevel) {
			$currentStock += $stockLevel->getStockLevel();

			if ($currentStock <= 0) {
				$avgPrice = 0;
				$totalPartStockPrice = 0;
				$negativeStock = $currentStock;
			} else {
				if ($stockLevel->getStockLevel() > 0) {
					$lastPosEntryQuant = $stockLevel->getStockLevel();
					$lastPosEntryPrice = $stockLevel->getPrice();
					$totalPartStockPrice += $lastPosEntryPrice * ($lastPosEntryQuant + $negativeStock);
					$avgPrice = $totalPartStockPrice / $currentStock;
				} else {
					if ($currentStock < $lastPosEntryQuant) {
						$totalPartStockPrice = $currentStock * $lastPosEntryPrice;
						$avgPrice = $totalPartStockPrice / $currentStock;
					} else {
						$totalPartStockPrice += $stockLevel->getStockLevel() * $avgPrice;
						$avgPrice = $totalPartStockPrice / $currentStock;
					}
					$negativeStock = 0;
				}
			}
		}

		$this->setStockLevel($currentStock);
		$this->setAveragePrice($avgPrice);
		$this->setLowStock($currentStock < $this->getMinStockLevel());

		return $this;
	}

	public function getStockLevels(): Collection
	{
		return $this->stockLevels;
	}

	public function getMinStockLevel(): int
	{
		return $this->minStockLevel;
	}

	public function setMinStockLevel(int $minStockLevel): self
	{
		if ($minStockLevel < 0) {
			throw new MinStockLevelOutOfRangeException;
		}

		$this->minStockLevel = $minStockLevel;
		$this->setLowStock($this->getStockLevel() < $this->getMinStockLevel());

		return $this;
	}

	public function getStockLevel(): ?int
	{
		return $this->stockLevel;
	}

	public function setStockLevel(int $stockLevel): self
	{
		$this->stockLevel = $stockLevel;
		return $this;
	}


	public function getRemovals(): ?bool
	{
		return $this->removals;
	}

	public function getLowStock(): ?bool
	{
		return $this->lowStock;
	}

	public function getMetaPart(): ?bool
	{
		return $this->metaPart;
	}
}
