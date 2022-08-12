<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\SystemNoticeAcknowledge;
use Limas\Repository\SystemNoticeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: SystemNoticeRepository::class)]
#[ApiResource(
	itemOperations: ['get',
		'acknowledge' => [
			'method' => 'PUT',
			'path' => 'system_notices/{id}/acknowledge',
			'controller' => SystemNoticeAcknowledge::class,
			'deserialize' => false
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class SystemNotice
	extends BaseEntity
{
	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	#[Groups(['default'])]
	private \DateTimeInterface $date;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $title;
	#[ORM\Column(type: Types::TEXT)]
	#[Groups(['default'])]
	private string $description;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $acknowledged = false;
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $type;


	public function setDate(\DateTimeInterface $date): self
	{
		$this->date = $date;
		return $this;
	}

	public function getDate(): ?\DateTimeInterface
	{
		return $this->date;
	}

	public function setTitle(string $title): self
	{
		$this->title = $title;
		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setDescription(string $description): self
	{
		$this->description = $description;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setAcknowledged(bool $acknowledged = true): self
	{
		$this->acknowledged = $acknowledged;
		return $this;
	}

	public function isAcknowledged(): ?bool
	{
		return $this->acknowledged;
	}

	public function setType(string $type): self
	{
		$this->type = $type;
		return $this;
	}

	public function getType(): ?string
	{
		return $this->type;
	}

	public function getAcknowledged(): ?bool
	{
		return $this->acknowledged;
	}
}
