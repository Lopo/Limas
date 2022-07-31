<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\BatchJobActions;
use Limas\Repository\BatchJobRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: BatchJobRepository::class)]
#[ApiResource(
	itemOperations: [
		'get',
		'put',
		'delete',
		'BatchJobExecute' => [
			'path' => 'batch_jobs/{id}/execute',
			'method' => 'put',
			'controller' => BatchJobActions::class . '::BatchJobExecute'
		]
	]
)]
class BatchJob
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, length: 64, unique: true)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\OneToMany(mappedBy: 'batchJob', targetEntity: BatchJobQueryField::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $batchJobQueryFields;
	#[ORM\OneToMany(mappedBy: 'batchJob', targetEntity: BatchJobUpdateField::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $batchJobUpdateFields;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
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
