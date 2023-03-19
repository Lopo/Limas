<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\UserActions;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Limas\Annotation\VirtualField;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'username_provider', fields: ['username', 'provider'])]
#[ApiResource(
	collectionOperations: [
		'get',
		'post' => [
			'controller' => UserActions::class . '::PostAction'
//		],
//		'GetProviders' => [
//			'path' => 'users/get_user_providers',
//			'method' => 'get',
//			'controller' => UserActions::class . '::GetProvidersAction'
		]
	],
	itemOperations: [
		'get' => [
			'controller' => UserActions::class . '::getAction'
		],
		'put' => [
			'path' => 'users/{id}',
			'controller' => UserActions::class . '::PutUserAction'
		],
		'delete' => [
			'path' => 'users/{id}',
			'controller' => UserActions::class . '::DeleteUserAction'
		],
		'changePassword' => [
			'method' => 'patch',
			'path' => 'users/{id}/changePassword',
			'controller' => UserActions::class . '::changePasswordAction',
			'input_formats' => [
				'json' => ['application/merge-patch+json'],
			],
			'denormalization_context' => ['groups' => ['changePassword:write']]
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class User
	extends BaseEntity
	implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
	#[ORM\Column(type: Types::STRING, length: 50)]
	#[Groups(['default', 'login:write'])]
	private string $username;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['login:write'])]
	private ?string $password = null;
	#[VirtualField(type: 'string')]
	#[Groups(['default'])]
	private ?string $newPassword = null;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	#[Assert\Email]
	private ?string $email;
	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
	private ?\DateTimeInterface $lastSeen;
	#[ORM\ManyToOne(targetEntity: UserProvider::class)]
	#[ORM\JoinColumn(nullable: false)]
	#[Groups(['default'])]
	#[ApiProperty(writableLink: true)]
	private UserProvider $provider;
	/** @var Collection<TipOfTheDayHistory> */
	#[ORM\OneToMany(mappedBy: 'user', targetEntity: TipOfTheDayHistory::class, cascade: ['remove'], orphanRemoval: true)]
	private Collection $tipHistories;
	#[VirtualField(type: 'string')]
	#[Groups(['default'])]
	private string $initialUserPreferences;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $active = true;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $protected = false;
	#[ORM\Column(type: Types::JSON)]
	private array $roles = [];


	public function __construct(?string $username = null, ?UserProvider $provider = null)
	{
		$this->tipHistories = new ArrayCollection;
		if ($username !== null) {
			$this->username = $username;
		}
		if ($provider !== null) {
			$this->provider = $provider;
		}
	}

	public function setAdmin(bool $bAdmin): self
	{
		$role = 'ROLE_ADMIN';
		$roles = $this->getRoles();
		if ($bAdmin && !in_array($role, $roles, true)) {
			$roles[] = $role;
			$this->setRoles($roles);
			return $this;
		}
		if (!$bAdmin && in_array($role, $roles, true)) {
			$nRoles = [];
			foreach ($roles as $r) {
				if ($r === $role) {
					continue;
				}
				$nRoles[] = $r;
			}
			$this->setRoles($nRoles);
			return $this;
		}
		return $this;
	}

	public function isProtected(): bool
	{
		return $this->protected;
	}

	public function setProtected(bool $protected): self
	{
		$this->protected = $protected;
		return $this;
	}

	public function isActive(): bool
	{
		return $this->active;
	}

	public function setActive(bool $active): self
	{
		$this->active = $active;
		return $this;
	}

	public function getInitialUserPreferences(): string
	{
		return $this->initialUserPreferences;
	}

	public function setInitialUserPreferences(string $initialUserPreferences): self
	{
		$this->initialUserPreferences = $initialUserPreferences;
		return $this;
	}

	public function getTipHistories(): Collection
	{
		return $this->tipHistories;
	}

	public function setTipHistories(Collection|array $tipHistories): self
	{
		$this->tipHistories = is_array($tipHistories) ? new ArrayCollection($tipHistories) : $tipHistories;
		return $this;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(?string $email): self
	{
		$this->email = $email;
		return $this;
	}

	public function getProvider(): UserProvider
	{
		return $this->provider;
	}

	public function setProvider(UserProvider $provider): self
	{
		$this->provider = $provider;
		return $this;
	}

//	public function setRawUsername($username)
//	{
//		$this->username = $username;
//	}

	public function isAdmin(): bool
	{
		return in_array('ROLE_ADMIN', $this->getRoles(), true);
	}

	public function getPassword(): ?string
	{
		return $this->password;
	}

	public function setPassword(?string $password): self
	{
		$this->password = $password;
		return $this;
	}

	public function getNewPassword(): ?string
	{
		return $this->newPassword;
	}

	public function setNewPassword(?string $newPassword): self
	{
		$this->newPassword = $newPassword;
		return $this;
	}

	public function updateSeen(?\DateTimeInterface $lastSeen): self
	{
		$this->setLastSeen($lastSeen);
		return $this;
	}

	public function getLastSeen(): ?\DateTimeInterface
	{
		return $this->lastSeen;
	}

	public function getRoles(): array
	{
		$roles = $this->roles;
		// guarantee every user at least has ROLE_USER
		$roles[] = 'ROLE_USER';

		return array_unique($roles);
	}

	public function eraseCredentials(): void
	{
		$this->newPassword = null;
	}

	public function isEqualTo(UserInterface $user): bool
	{
		if (!$user instanceof self) {
			return false;
		}
		if ($this->getUsername() !== $user->getUsername()) {
			return false;
		}
		return true;
	}

	public function getUsername(): ?string
	{
		return $this->username;
	}

	public function setUsername(string $username): self
	{
		$this->username = $username;
		return $this;
	}


	public function setLastSeen(?\DateTimeInterface $lastSeen): self
	{
		$this->lastSeen = $lastSeen;
		return $this;
	}

	public function setRoles(array $roles): self
	{
		$this->roles = $roles;
		return $this;
	}

	public function getUserIdentifier(): string
	{
		return $this->username;
	}
}
