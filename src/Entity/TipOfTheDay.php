<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\TipOfTheDay as Actions;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),
		new Post(uriTemplate: '/tip_of_the_days/markAllTipsAsUnread', controller: Actions\MarkAllTipsAsUnread::class, deserialize: false),

		new Get(),
		new Put(uriTemplate: '/tip_of_the_days/{id}/markTipRead', controller: Actions\MarkTipRead::class, deserialize: false)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class TipOfTheDay
	extends BaseEntity
{
	#[ORM\Column(type: Types::STRING)]
	#[Groups(['default'])]
	private string $name;


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
