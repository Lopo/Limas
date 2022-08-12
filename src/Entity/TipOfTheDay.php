<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\TipOfTheDayActions;
use Limas\Repository\TipOfTheDayRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: TipOfTheDayRepository::class)]
#[ApiResource(
	collectionOperations: [
		'get',
		'post',
		'markAllTipsAsUnread' => [
			'method' => 'post',
			'path' => 'tip_of_the_days/markAllTipsAsUnread',
			'controller' => TipOfTheDayActions::class . '::MarkAllTipsAsUnread',
			'deserialize' => false
		]
	],
	itemOperations: [
		'get',
		'markTipRead' => [
			'method' => 'put',
			'path' => 'tip_of_the_days/{id}/markTipRead',
			'controller' => TipOfTheDayActions::class . '::MarkTipRead',
			'deserialize' => false
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
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
