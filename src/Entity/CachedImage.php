<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Limas\Repository\CachedImageRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: CachedImageRepository::class)]
class CachedImage
	extends BaseEntity
{
	#[ORM\Column(type: Types::INTEGER)]
	private int $originalId;
	#[ORM\Column(type: Types::STRING)]
	private string $originalType;
	#[ORM\Column(type: Types::STRING)]
	private string $cacheFile;


	public function __construct(UploadedFile $image, string $cacheFile)
	{
		$this->originalId = $image->getId();
		$this->originalType = $image->getType();
		$this->cacheFile = $cacheFile;
	}

	public function getCacheFile(): ?string
	{
		return $this->cacheFile;
	}

	public function getOriginalId(): ?int
	{
		return $this->originalId;
	}

	public function getOriginalType(): ?string
	{
		return $this->originalType;
	}


	public function setCacheFile(string $cacheFile): self
	{
		$this->cacheFile = $cacheFile;
		return $this;
	}

	public function setOriginalId(int $originalId): self
	{
		$this->originalId = $originalId;
		return $this;
	}

	public function setOriginalType(string $originalType): self
	{
		$this->originalType = $originalType;
		return $this;
	}
}
