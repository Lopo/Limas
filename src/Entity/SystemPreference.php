<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\SystemPreferenceActions;
use Limas\Annotation\IgnoreIds;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[IgnoreIds]
#[ApiResource(
	collectionOperations: [
		'get' => [
			'controller' => SystemPreferenceActions::class . '::getAction',
			'output_formats' => [
				'json'
			]
		],
		'SystemPreferenceDelete' => [
			'method' => 'delete',
			'path' => 'system_preferences',
			'controller' => SystemPreferenceActions::class . '::deleteAction'
		]
	],
	itemOperations: [],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
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
