<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\BatchJobActions;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Get,
		new Put,
		new Delete,
		new Put(
			uriTemplate: 'batch_jobs/{id}/execute',
			controller: BatchJobActions::class . '::BatchJobExecute',
			name: 'BatchJobExecute'
		)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class BatchJob
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, length: 64, unique: true)]
	#[Groups(['default'])]
	private string $name;
	/** @var Collection<BatchJobQueryField> */
	#[ORM\OneToMany(targetEntity: BatchJobQueryField::class, mappedBy: 'batchJob', cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $batchJobQueryFields;
	/** @var Collection<BatchJobUpdateField> */
	#[ORM\OneToMany(targetEntity: BatchJobUpdateField::class, mappedBy: 'batchJob', cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $batchJobUpdateFields;
	/**
	 * Inventory content a batch job may query and update. Anything else —
	 * users, providers, preferences, stock history, system notices — is off
	 * limits. Kept in sync with the base-entity picker in BatchJobEditor.js.
	 */
	public const array ALLOWED_BASE_ENTITIES = [
		'Limas.Entity.Part',
		'Limas.Entity.PartCategory',
		'Limas.Entity.PartManufacturer',
		'Limas.Entity.PartDistributor',
		'Limas.Entity.PartParameter',
		'Limas.Entity.StorageLocation',
		'Limas.Entity.StorageLocationCategory',
		'Limas.Entity.Footprint',
		'Limas.Entity.FootprintCategory',
		'Limas.Entity.Manufacturer',
		'Limas.Entity.Distributor',
		'Limas.Entity.Project',
		'Limas.Entity.ProjectPart'
	];

	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	#[Assert\Choice(choices: self::ALLOWED_BASE_ENTITIES, message: 'Batch jobs can only target inventory entities.')]
	private string $baseEntity;


	public function __construct()
	{
		$this->batchJobQueryFields = new ArrayCollection;
		$this->batchJobUpdateFields = new ArrayCollection;
	}

	public function getBatchJobUpdateFields(): Collection
	{
		return $this->batchJobUpdateFields;
	}

	public function getBaseEntity(): string
	{
		return $this->baseEntity;
	}

	public function setBaseEntity(string $baseEntity): self
	{
		$this->baseEntity = $baseEntity;
		return $this;
	}

	public function getBatchJobQueryFields(): Collection
	{
		return $this->batchJobQueryFields;
	}

	public function addBatchJobQueryField(BatchJobQueryField $batchJobQueryField): self
	{
		$batchJobQueryField->setBatchJob($this);
		$this->batchJobQueryFields->add($batchJobQueryField);
		return $this;
	}

	public function removeBatchJobQueryField(BatchJobQueryField $batchJobQueryField): self
	{
		$batchJobQueryField->setBatchJob(null);
		$this->batchJobQueryFields->removeElement($batchJobQueryField);
		return $this;
	}

	public function addBatchJobUpdateField(BatchJobUpdateField $batchJobUpdateField): self
	{
		$batchJobUpdateField->setBatchJob($this);
		$this->batchJobUpdateFields->add($batchJobUpdateField);
		return $this;
	}

	public function removeBatchJobUpdateField(BatchJobUpdateField $batchJobUpdateField): self
	{
		$batchJobUpdateField->setBatchJob(null);
		$this->batchJobUpdateFields->removeElement($batchJobUpdateField);
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}
}
