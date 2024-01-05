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
class ProjectRunPart
	extends BaseEntity
{
	#[ORM\ManyToOne(targetEntity: ProjectRun::class)]
	#[Groups(['default'])]
	private ?ProjectRun $projectRun;
	#[ORM\ManyToOne(targetEntity: Part::class)]
	#[Groups(['default'])]
	private ?Part $part;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $quantity;
	#[ORM\Column(type: Types::TEXT)]
	#[Groups(['default'])]
	private string $lotNumber;


	public function getPart(): ?Part
	{
		return $this->part;
	}

	public function setPart(?Part $part): self
	{
		$this->part = $part;
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

	public function getLotNumber(): ?string
	{
		return $this->lotNumber;
	}

	public function setLotNumber(string $lotNumber): self
	{
		$this->lotNumber = $lotNumber;
		return $this;
	}

	public function getProjectRun(): ?ProjectRun
	{
		return $this->projectRun;
	}

	public function setProjectRun(?ProjectRun $projectRun): self
	{
		$this->projectRun = $projectRun;
		return $this;
	}

	public function __toString(): string
	{
		return sprintf('Used in project run for project %s on %s',
				$this->getProjectRun()->getProject()->getName(),
				$this->getProjectRun()->getRunDateTime()->format('Y-m-d H:i:s')
			) . ' / ' . parent::__toString();
	}
}
