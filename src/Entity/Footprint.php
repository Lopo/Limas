<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Limas\Annotation\UploadedFile;
use Limas\Annotation\UploadedFileCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class Footprint
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, length: 64, unique: true)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $description;
	#[ORM\ManyToOne(targetEntity: FootprintCategory::class, inversedBy: 'footprints')]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private ?FootprintCategory $category;
	#[ORM\OneToOne(mappedBy: 'footprint', targetEntity: FootprintImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[Groups(['default'])]
	#[UploadedFile]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private ?FootprintImage $image;
	/** @var Collection<FootprintAttachment> */
	#[ORM\OneToMany(mappedBy: 'footprint', targetEntity: FootprintAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[UploadedFileCollection]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private Collection $attachments;


	public function __construct()
	{
		$this->attachments = new ArrayCollection;
	}

	public function getCategoryPath(): string
	{
		return $this->category !== null
			? $this->category->getCategoryPath()
			: '';
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setCategory(?FootprintCategory $category): self
	{
		$this->category = $category;
		return $this;
	}

	public function getCategory(): ?FootprintCategory
	{
		return $this->category;
	}

	public function setImage(?FootprintImage $image): self
	{
		if ($image instanceof FootprintImage) {
			$image->setFootprint($this);
			$this->image = $image;
		} else {
			// Because this is a 1:1 relationship. only allow the temporary image to be set when no image exists.
			// If an image exists, the frontend needs to deliver the old file ID with the replacement property set.
			if ($this->getImage() === null) {
				$this->image = $image;
			}
		}
		return $this;
	}

	public function getImage(): ?FootprintImage
	{
		return $this->image;
	}

	public function getAttachments(): Collection
	{
		return $this->attachments;
	}

	public function addAttachment(FootprintAttachment $attachment): self
	{
		$attachment->setFootprint($this);
		$this->attachments->add($attachment);
		return $this;
	}

	public function removeAttachment(FootprintAttachment $attachment): self
	{
		$attachment->setFootprint(null);
		$this->attachments->removeElement($attachment);
		return $this;
	}
}
