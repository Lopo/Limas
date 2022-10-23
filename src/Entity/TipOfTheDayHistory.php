<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\GetTipHistoryCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	collectionOperations: [
		'TipHistoriesGet' => [
			'method' => 'get',
			'controller' => GetTipHistoryCollection::class
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class TipOfTheDayHistory
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $name;
	#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tipHistories')]
	#[Groups(['default'])]
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
