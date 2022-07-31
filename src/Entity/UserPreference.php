<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\UserPreferenceActions;
use Limas\Annotation\IgnoreIds;
use Limas\Repository\UserPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: UserPreferenceRepository::class)]
#[IgnoreIds]
#[ApiResource(
	collectionOperations: [
		'get' => [
			'controller' => UserPreferenceActions::class . '::getPreferencesAction',
			'deserialize' => false,
			'normalization_context' => [
				'groups' => ['default']
			]
		],
		'UserPreferenceDelete' => [
			'method' => 'delete',
			'path' => 'user_preferences',
			'controller' => UserPreferenceActions::class . '::deletePreferenceAction',
			'deserialize' => false,
		]
	],
	itemOperations: ['get']
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
