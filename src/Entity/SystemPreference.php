<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Annotation\IgnoreIds;
use Limas\Controller\Actions\SystemPreference as Actions;
use Limas\Repository\SystemPreferenceRepository;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: SystemPreferenceRepository::class)]
#[IgnoreIds]
#[ApiResource(
	operations: [
		new GetCollection(outputFormats: ['json'], controller: Actions\Get::class),
		new Post(uriTemplate: '/system_preferences', controller: Actions\Set::class, read: false),

		new Put(uriTemplate: '/system_preferences', controller: Actions\Set::class, read: false),
		new Delete(uriTemplate: '/system_preferences', controller: Actions\Delete::class, read: false)
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
