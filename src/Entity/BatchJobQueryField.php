<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Get,
		new Put,
		new Delete
	]
)]
class BatchJobQueryField
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: BatchJob::class, inversedBy: 'batchJobQueryFields')]
	private ?BatchJob $batchJob = null;
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $property;
	#[ORM\Column(type: Types::STRING, length: 64)]
	#[Groups(['default'])]
	private string $operator;
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

	public function getOperator(): ?string
	{
		return $this->operator;
	}

	public function setOperator(string $operator): self
	{
		$this->operator = $operator;
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
