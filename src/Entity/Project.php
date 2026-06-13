<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiProperty;
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
use Limas\Annotation\UploadedFileCollection;
use Limas\State\ProjectProcessor;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Get,
		new Put(processor: ProjectProcessor::class),
		new Delete
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class Project
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\ManyToOne(targetEntity: User::class)]
	private ?User $user = null;
	/** @var Collection<ProjectPart> */
	#[ORM\OneToMany(targetEntity: ProjectPart::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private Collection $parts;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $description;
	/** @var Collection<ProjectAttachment> */
	#[ORM\OneToMany(targetEntity: ProjectAttachment::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[UploadedFileCollection]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private Collection $attachments;


	public function __construct()
	{
		$this->parts = new ArrayCollection;
		$this->attachments = new ArrayCollection;
	}

	public function getUser(): ?User
	{
		return $this->user;
	}

	public function setUser(?User $user): self
	{
		$this->user = $user;
		return $this;
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

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;
		return $this;
	}

	/** @return array<ProjectPart> */
	public function getParts(): array
	{
		return $this->parts->getValues();
	}

	public function setParts(iterable $parts): self
	{
		// remove old parts (orphanRemoval will delete them)
		foreach ($this->parts->toArray() as $existingPart) {
			$this->removePart($existingPart);
		}
		// add new
		foreach ($parts as $part) {
			$this->addPart($part);
		}
		return $this;
	}

	public function addPart(ProjectPart $projectPart): self
	{
		$projectPart->setProject($this);
		$this->parts->add($projectPart);
		return $this;
	}

	public function removePart(ProjectPart $projectPart): self
	{
		$projectPart->setProject(null);
		$this->parts->removeElement($projectPart);
		return $this;
	}

	public function getAttachments(): Collection
	{
		return $this->attachments;
	}

	public function addAttachment(ProjectAttachment $projectAttachment): self
	{
		$projectAttachment->setProject($this);
		$this->attachments->add($projectAttachment);
		return $this;
	}

	public function removeAttachment(ProjectAttachment $projectAttachment): self
	{
		$projectAttachment->setProject(null);
		$this->attachments->removeElement($projectAttachment);
		return $this;
	}
}
