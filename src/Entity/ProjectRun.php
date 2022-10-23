<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class ProjectRun
	extends BaseEntity
{
	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	#[Groups(['default'])]
	private \DateTimeInterface $runDateTime;
	#[ORM\ManyToOne(targetEntity: Project::class)]
	#[Groups(['default'])]
	private ?Project $project;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $quantity;
	/** @var Collection<ProjectRunPart> */
	#[ORM\OneToMany(mappedBy: 'projectRun', targetEntity: ProjectRunPart::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $parts;


	public function __construct()
	{
		$this->parts = new ArrayCollection;
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

	public function getRunDateTime(): ?\DateTimeInterface
	{
		return $this->runDateTime;
	}

	public function setRunDateTime(\DateTimeInterface $runDateTime): self
	{
		$this->runDateTime = $runDateTime;
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

	public function getParts(): Collection
	{
		return $this->parts;
	}

	public function addPart(ProjectRunPart $part): self
	{
		$part->setProjectRun($this);
		$this->parts->add($part);
		return $this;
	}

	public function removePart(ProjectRunPart $part): self
	{
		$part->setProjectRun(null);
		$this->parts->removeElement($part);
		return $this;
	}
}
