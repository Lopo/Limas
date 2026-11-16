<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Annotation\VirtualField;
use Limas\Controller\Actions\UserActions;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'username_provider', fields: ['username', 'provider'])]
#[ApiResource(
	operations: [
		// User management is admin-only. Reading/deleting/creating other
		// users and listing the directory require ROLE_ADMIN; a user may read
		// their own record and change their own password (the latter still
		// verifies the old password in changePasswordAction). Without this,
		// any authenticated user could PUT /api/users/{id} with a new
		// password and take over any non-protected account.
		new GetCollection(security: "is_granted('ROLE_ADMIN')"),
		new Post(
			controller: UserActions::class . '::PostAction',
			security: "is_granted('ROLE_ADMIN')"
		),
//		new GetCollection(
//			uriTemplate: 'users/get_user_providers',
//			controller: UserActions::class . '::GetProvidersAction'
//		),
		new Get(
			controller: UserActions::class . '::getAction',
			security: "is_granted('ROLE_ADMIN') or object == user"
		),
		new Put(
			uriTemplate: 'users/{id}',
			controller: UserActions::class . '::PutUserAction',
			security: "is_granted('ROLE_ADMIN')"
		),
		new Delete(
			uriTemplate: 'users/{id}',
			controller: UserActions::class . '::DeleteUserAction',
			security: "is_granted('ROLE_ADMIN')"
		),
		new Patch(
			uriTemplate: 'users/{id}/changePassword',
			inputFormats: [
				'json' => ['application/merge-patch+json']
			],
			controller: UserActions::class . '::changePasswordAction',
			denormalizationContext: [
				'groups' => ['changePassword:write']
			],
			security: "is_granted('ROLE_ADMIN') or object == user",
			name: 'changePassword'
		)
	],
	// `user:detail` carries fields that only the user endpoints themselves
	// (admin-only, or the user's own record) may expose. Other resources that
	// embed a user — stock entries, projects, import jobs — serialize with
	// their own `default` group and so stay limited to the public fields.
	// Writes use the dedicated `user:write` group, so nothing that merely
	// shares `default` with an embedding resource is writable here; relations
	// pointing at User are additionally `writableLink: false`.
	normalizationContext: ['groups' => ['default', 'user:detail']],
	denormalizationContext: ['groups' => ['user:write']]
)]
class User
	extends BaseEntity
	implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
	#[ORM\Column(type: Types::STRING, length: 50)]
	#[Groups(['default', 'login:write', 'user:write'])]
	private string $username;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['login:write'])]
	private ?string $password = null;
	// Unix timestamp of the last password change. Embedded as a JWT claim at
	// issue time; a token whose claim predates this value is rejected, so a
	// password change instantly invalidates every previously issued access
	// token. Nullable: legacy rows have no value until their next change.
	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	private ?int $passwordChangedAt = null;
	#[VirtualField(type: Types::STRING)]
	#[Groups(['user:write'])]
	private ?string $newPassword = null;
	// Not in `default`: every stock entry embeds its user, and the history is
	// readable by any account, which leaked the directory's e-mail addresses
	// that the admin-only /api/users routes protect. (GHSA-8883-2whw-gwhm)
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['user:detail', 'user:write'])]
	#[Assert\Email]
	private ?string $email;
	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
	private ?\DateTimeInterface $lastSeen;
	#[ORM\ManyToOne(targetEntity: UserProvider::class)]
	#[ORM\JoinColumn(nullable: false)]
	#[Groups(['default', 'user:write'])]
	#[ApiProperty(writableLink: false)]
	private UserProvider $provider;
	/** @var Collection<TipOfTheDayHistory> */
	#[ORM\OneToMany(targetEntity: TipOfTheDayHistory::class, mappedBy: 'user', cascade: ['remove'], orphanRemoval: true)]
	private Collection $tipHistories;
	#[VirtualField(type: Types::STRING)]
	#[Groups(['default'])]
	private string $initialUserPreferences = '[]';
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default', 'user:write'])]
	private bool $active = true;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $protected = false;
	// Readable on the user's own record (the frontend derives isAdmin() from
	// it); never writable through the API
	#[ORM\Column(type: Types::JSON)]
	#[Groups(['user:detail'])]
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

	public function setProvider(?UserProvider $provider): self
	{
		// Nullable to keep API Platform denormalisation happy when FE omits
		// the field on new-user POSTs — `UserActions::PostAction` then sets
		// the built-in provider before flush. The property itself stays
		// non-nullable, so internal callers still get a hard type guarantee.
		if ($provider !== null) {
			$this->provider = $provider;
		}
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

	public function setPassword(#[\SensitiveParameter] ?string $password): self
	{
		$this->password = $password;
		// Any password change bumps the stamp so previously issued JWTs (which
		// carry the old value) stop validating. Covers every path — create,
		// admin edit, self-service change.
		$this->passwordChangedAt = time();
		return $this;
	}

	public function getPasswordChangedAt(): ?int
	{
		return $this->passwordChangedAt;
	}

	public function getNewPassword(): ?string
	{
		return $this->newPassword;
	}

	public function setNewPassword(#[\SensitiveParameter] ?string $newPassword): self
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

	public function __serialize(): array
	{
		$data = (array)$this;
		$data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
		return $data;
	}

	#[\Deprecated(message: 'Credentials are erased automatically via __serialize()')]
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
