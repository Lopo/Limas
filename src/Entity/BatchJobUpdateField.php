<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Repository\BatchJobUpdateFieldRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: BatchJobUpdateFieldRepository::class)]
#[ApiResource(
	collectionOperations: [],
	itemOperations: ['get']
)]
class BatchJobUpdateField
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: BatchJob::class, inversedBy: 'batchJobUpdateFields')]
	private ?BatchJob $batchJob = null;
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $property;
	#[ORM\Column(type: Types::TEXT)]
	#[Groups(['default'])]
	private string $value;
	#[ORM\Column(type: Types::TEXT)]
	#[Groups(['default'])]
	private string $description;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $dynamic;


	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(string $description): self
	{
		$this->description = $description;
		return $this;
	}

	public function getBatchJob(): ?BatchJob
	{
		return $this->batchJob;
	}

	public function setBatchJob(?BatchJob $batchJob): self
	{
		$this->batchJob = $batchJob;
		return $this;
	}

	public function getProperty(): ?string
	{
		return $this->property;
	}

	public function setProperty(string $property): self
	{
		$this->property = $property;
		return $this;
	}

	public function getValue(): ?string
	{
		return $this->value;
	}

	public function setValue(string $value): self
	{
		$this->value = $value;
		return $this;
	}

	public function getDynamic(): bool
	{
		return $this->dynamic;
	}

	public function setDynamic(bool $dynamic): self
	{
		$this->dynamic = $dynamic;
		return $this;
	}
}
