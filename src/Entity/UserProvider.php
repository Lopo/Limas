<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Repository\UserProviderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: UserProviderRepository::class)]
#[ORM\Table(name: 'UserProvider',
	uniqueConstraints: [
		new ORM\UniqueConstraint(name: 'type', fields: ['type'])
	]
)]
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
class UserProvider
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $type;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $editable;


	public function __construct(?string $type, ?bool $editable = true)
	{
		$this->type = $type;
		$this->editable = $editable;
	}

	public function isEditable(): bool
	{
		return $this->editable;
	}

	public function setEditable(bool $editable): self
	{
		$this->editable = $editable;
		return $this;
	}

	public function getType(): ?string
	{
		return $this->type;
	}

	public function setType(string $type): self
	{
		$this->type = $type;
		return $this;
	}

	public function getEditable(): bool
	{
		return $this->editable;
	}
}
