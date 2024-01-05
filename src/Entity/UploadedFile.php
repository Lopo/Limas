<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\MappedSuperclass]
abstract class UploadedFile
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $type;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $filename;
	#[ORM\Column(name: 'originalname', type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $originalFilename;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $mimetype;
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $size;
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	#[Groups(['default'])]
	private ?string $description;
	#[Groups(['default'])]
	private mixed $replacement = null;
	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
	private \DateTimeInterface $created;


	public function __construct()
	{
		$this->filename = Uuid::uuid1()->toString();
		$this->setCreated(new \DateTime);
	}

	public function getCreated(): \DateTimeInterface
	{
		return $this->created;
	}

	public function setCreated(\DateTimeInterface $created): self
	{
		$this->created = $created;
		return $this;
	}

	public function getReplacement(): mixed
	{
		return $this->replacement;
	}

	public function setReplacement(mixed $replacement): self
	{
		$this->replacement = $replacement;
		return $this;
	}

	public function getOriginalFilename(): ?string
	{
		return $this->originalFilename;
	}

	public function setOriginalFilename(?string $originalFilename): self
	{
		$this->originalFilename = $originalFilename;
		return $this;
	}

	public function getSize(): int
	{
		return $this->size;
	}

	public function setSize(int $size): self
	{
		$this->size = $size;
		return $this;
	}

	public function getType(): string
	{
		return $this->type;
	}

	protected function setType(string $type): self
	{
		$this->type = $type;
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

	public function getFilename(): string
	{
		return $this->filename;
	}

	public function setFilename(string $filename): self
	{
		$this->filename = $filename;
		return $this;
	}

	public function getMimetype(): string
	{
		return $this->mimetype;
	}

	public function setMimetype(string $mimetype): self
	{
		$this->mimetype = $mimetype;
		return $this;
	}
}
