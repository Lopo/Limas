<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Limas\Annotation\UploadedFileCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class Manufacturer
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, unique: true)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $address;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $url;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $email;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $comment;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $phone;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $fax;
	/** @var Collection<ManufacturerICLogo> */
	#[ORM\OneToMany(mappedBy: 'manufacturer', targetEntity: ManufacturerICLogo::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[UploadedFileCollection]
	#[Groups(['default'])]
	#[ApiProperty(readableLink: true, writableLink: true)]
	private Collection $icLogos;


	public function __construct()
	{
		$this->icLogos = new ArrayCollection;
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

	public function setPhone(?string $phone): self
	{
		$this->phone = $phone;
		return $this;
	}

	public function getPhone(): ?string
	{
		return $this->phone;
	}

	public function setFax(?string $fax): self
	{
		$this->fax = $fax;
		return $this;
	}

	public function getFax(): ?string
	{
		return $this->fax;
	}

	public function setAddress(?string $address): self
	{
		$this->address = $address;
		return $this;
	}

	public function getAddress(): ?string
	{
		return $this->address;
	}

	public function setComment(?string $comment): self
	{
		$this->comment = $comment;
		return $this;
	}

	public function getComment(): ?string
	{
		return $this->comment;
	}

	public function setEmail(?string $email): self
	{
		$this->email = $email;
		return $this;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setUrl(?string $url): self
	{
		$this->url = $url;
		return $this;
	}

	public function getUrl(): ?string
	{
		return $this->url;
	}

	public function getIcLogos(): Collection
	{
		return $this->icLogos;
	}

	public function addIcLogo(ManufacturerICLogo $icLogo): self
	{
		$icLogo->setManufacturer($this);
		$this->icLogos->add($icLogo);
		return $this;
	}

	public function removeIcLogo(ManufacturerICLogo $icLogo): self
	{
		$icLogo->setManufacturer(null);
		$this->icLogos->removeElement($icLogo);
		return $this;
	}
}
