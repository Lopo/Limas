<?php

namespace Limas\Entity;

use ApiPlatform\Action\NotFoundAction;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Annotation\IgnoreIds;
use Limas\Controller\Actions\SystemPreferenceActions;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[IgnoreIds]
#[ApiResource(
//	collectionOperations: [
//		'get' => [
//			'controller' => SystemPreferenceActions::class . '::getAction',
//			'output_formats' => [
//				'json'
//			]
//		],
//		'SystemPreferenceDelete' => [
//			'method' => 'delete',
//			'path' => 'system_preferences',
//			'controller' => SystemPreferenceActions::class . '::deleteAction'
//		]
//	],
//	itemOperations: [],
	operations: [
		new GetCollection(
			uriTemplate: 'system_preferences',
			outputFormats: ['json'],
			controller: SystemPreferenceActions::class . '::getAction',
			name: 'SystemPreferenceGet'
		),
		new Delete(
			uriTemplate: 'system_preferences',
			controller: SystemPreferenceActions::class . '::deleteAction',
			name: 'SystemPreferenceDelete'
		),
		new Get(
			controller: NotFoundAction::class,
			output: false,
			read: false
		)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class SystemPreference
{
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[ORM\Id]
	#[Groups(['default'])]
	private string $preferenceKey;
	#[ORM\Column(type: Types::TEXT)]
	#[Groups(['default'])]
	private string $preferenceValue;


	public function getPreferenceKey(): ?string
	{
		return $this->preferenceKey;
	}

	public function setPreferenceKey(string $preferenceKey): self
	{
		$this->preferenceKey = $preferenceKey;
		return $this;
	}

	public function getPreferenceValue(): mixed
	{
		return unserialize($this->preferenceValue);
	}

	public function setPreferenceValue(mixed $preferenceValue): self
	{
		$this->preferenceValue = serialize($preferenceValue);
		return $this;
	}
}
