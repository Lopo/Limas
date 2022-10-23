<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\ProjectReportActions;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	collectionOperations: [
		'get',
		'ProjectReportPost' => [
			'method' => 'POST',
			'path' => 'reports',
			'controller' => ProjectReportActions::class . '::createReportAction'
		]
	],
	itemOperations: [
		'ProjectReportGet' => [
			'method' => 'get',
			'path' => 'reports/{id}',
			'controller' => ProjectReportActions::class . '::getReportAction'
		],
		'put', 'delete'
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class Report
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $name;
	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	#[Groups(['default'])]
	private \DateTimeInterface $createDateTime;
	/** @var Collection<ReportProject> */
	#[ORM\OneToMany(mappedBy: 'report', targetEntity: ReportProject::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $reportProjects;
	/** @var Collection<ReportPart> */
	#[ORM\OneToMany(mappedBy: 'report', targetEntity: ReportPart::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	private Collection $reportParts;


	public function __construct()
	{
		$this->reportProjects = new ArrayCollection;
		$this->reportParts = new ArrayCollection;
		$this->setCreateDateTime(new \DateTime);
	}

	public function getCreateDateTime(): ?\DateTimeInterface
	{
		return $this->createDateTime;
	}

	public function setCreateDateTime(\DateTimeInterface $createDateTime): self
	{
		$this->createDateTime = $createDateTime;
		return $this;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(?string $name): self
	{
		$this->name = $name ?? 'NewReport';
		return $this;
	}

	public function getReportProjects(): Collection
	{
		return $this->reportProjects;
	}

	public function addReportProject(ReportProject $reportProject): self
	{
		$reportProject->setReport($this);
		$this->reportProjects->add($reportProject);
		return $this;
	}

	public function removeReportProject(ReportProject $reportProject): self
	{
		$reportProject->setReport(null);
		$this->reportProjects->removeElement($reportProject);
		return $this;
	}

	public function removeReportPart(ReportPart $reportPart): self
	{
		$reportPart->setReport(null);
		$this->reportParts->removeElement($reportPart);
		return $this;
	}

	public function addPartQuantity(Part $part, ProjectPart $projectPart, ?int $quantity): self
	{
		$reportPart = $this->getReportPartByPart($part);
		if ($reportPart === null) {
			$reportPart = (new ReportPart)
				->setPart($part)
				->setReport($this);
			$this->addReportPart($reportPart);
		}

		$reportPart->setQuantity($reportPart->getQuantity() + $quantity);

		$reportPart->getProjectParts()->add($projectPart);
		return $this;
	}

	public function getReportPartByPart(Part $part): ?ReportPart
	{
		foreach ($this->getReportParts() as $reportPart) {
			if ($reportPart->getPart() === $part) {
				return $reportPart;
			}
		}
		return null;
	}

	public function getReportParts(): Collection
	{
		return $this->reportParts;
	}

	public function addReportPart(ReportPart $reportPart): self
	{
		$reportPart->setReport($this);
		$this->reportParts->add($reportPart);
		return $this;
	}
}
