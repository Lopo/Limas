<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),

		new Get(),
		new Put(),
		new Delete()
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class ReportProject
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: Report::class, inversedBy: 'reportProjects')]
	private ?Report $report;
	#[ORM\ManyToOne(targetEntity: Project::class)]
	#[Groups(['default'])]
	#[Assert\NotNull]
	private ?Project $project;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $quantity;


	public function getReport(): ?Report
	{
		return $this->report;
	}

	public function setReport(?Report $report): self
	{
		$this->report = $report;
		return $this;
	}

	public function getProject(): ?Project
	{
		return $this->project;
	}

	public function setProject(?Project $project): self
	{
		$this->project = $project;
		return $this;
	}

	public function getQuantity(): ?int
	{
		return $this->quantity;
	}

	public function setQuantity(int $quantity): self
	{
		$this->quantity = $quantity;
		return $this;
	}
}
