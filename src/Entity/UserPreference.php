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
use Limas\Controller\Actions\UserPreference as Actions;
use Limas\Repository\UserPreferenceRepository;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: UserPreferenceRepository::class)]
#[IgnoreIds]
#[ApiResource(
	operations: [
		new GetCollection(controller: Actions\GetPreferences::class, deserialize: false),
		new Post(uriTemplate: '/user_preferences', controller: Actions\Set::class, read: false, deserialize: false),

		new Put(uriTemplate: '/user_preferences', controller: Actions\Set::class, read: false, deserialize: false),
		new Delete(uriTemplate: '/user_preferences', controller: Actions\Delete::class, read: false, deserialize: false)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class UserPreference
{
	#[ORM\Id]
	#[ORM\Column(type: Types::STRING, length: 255)]
	#[Groups(['default'])]
	private string $preferenceKey;
	#[ORM\Column(type: Types::TEXT)]
	#[Groups(['default'])]
	private mixed $preferenceValue;
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: User::class)]
	private User $user;


	public function __construct(?User $user, ?string $preferenceKey, ?string $preferenceValue = null)
	{
		$this->user = $user;
		$this->preferenceKey = $preferenceKey;
		if ($preferenceValue) {
			$this->setPreferenceValue($preferenceValue);
		}
	}

	public function setUser(User $user): self
	{
		$this->user = $user;
		return $this;
	}

	public function getUser(): ?User
	{
		return $this->user;
	}

	public function setPreferenceKey(string $preferenceKey): self
	{
		$this->preferenceKey = $preferenceKey;
		return $this;
	}

	public function getPreferenceKey(): string
	{
		return $this->preferenceKey;
	}

	public function setPreferenceValue(string $preferenceValue): self
	{
		$this->preferenceValue = serialize($preferenceValue);
		return $this;
	}

	public function getPreferenceValue(): mixed
	{
		return unserialize($this->preferenceValue);
	}
}
