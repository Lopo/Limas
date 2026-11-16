<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\GetTipHistoryCollection;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection(
			controller: GetTipHistoryCollection::class,
			name: 'TipHistoriesGet'
		),
		// A history row belongs to one user (which tips they have seen); item
		// operations are owner-only, like the collection
		new Get(security: "object.getUser() and object.getUser().getId() == user.getId()"),
		new Put(securityPostDenormalize: "previous_object.getUser() and previous_object.getUser().getId() == user.getId() and object.getUser() and object.getUser().getId() == user.getId()"),
		new Delete(security: "object.getUser() and object.getUser().getId() == user.getId()")
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class TipOfTheDayHistory
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $name;
	// Reference-only (IRI); users are written only through the user endpoints
	#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tipHistories')]
	#[Groups(['default'])]
	#[ApiProperty(writableLink: false)]
	private ?User $user;


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
}
