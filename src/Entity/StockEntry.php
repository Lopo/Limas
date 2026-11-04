<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
// The stock history is the "who took what, when, at what price" record, so
// writes are gated the same way the UI's history grid already pretended they
// were (AbstractStockHistoryGrid.onBeforeEdit): only an admin may change the
// amount, the user or the part, or delete an entry; the entry's own author
// may still fix their price and comment. Regular stock movements go through
// the part's addStock/removeStock/setStock actions, which stamp the caller,
// so the raw POST is admin-only. (GHSA-8883-2whw-gwhm)
#[ApiResource(
	operations: [
		new GetCollection,
		new Post(security: "is_granted('ROLE_ADMIN')"),
		new Get,
		new Put(
			// Populate the existing row so the owner's partial edit doesn't
			// reset the fields they are not allowed to touch
			extraProperties: ['standard_put' => false],
			securityPostDenormalize: "is_granted('ROLE_ADMIN') or (previous_object.getUser() and previous_object.getUser().getId() == user.getId() and object.differsOnlyInPriceOrCommentFrom(previous_object))"
		),
		new Delete(security: "is_granted('ROLE_ADMIN')")
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class StockEntry
	extends BaseEntity
{
	#[ORM\Column(type: Types::INTEGER)]
	#[Groups(['default'])]
	private int $stockLevel;
	#[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'stockLevels')]
	#[Groups(['default'])]
	private ?Part $part;
	#[ORM\ManyToOne(targetEntity: User::class)]
	#[Groups(['default'])]
	private ?User $user;
	#[ORM\Column(type: Types::DECIMAL, precision: 13, scale: 4, nullable: true)]
	#[Groups(['default'])]
	private ?string $price = null;
	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	#[Groups(['default'])]
	private \DateTimeInterface $dateTime;
	#[ORM\Column(type: Types::BOOLEAN)]
	#[Groups(['default'])]
	private bool $correction = false;
	#[ORM\Column(type: Types::STRING, nullable: true)]
	#[Groups(['default'])]
	private ?string $comment;


	public function __construct()
	{
		$this->setDateTime(new \DateTime);
	}

	public function setDateTime(\DateTimeInterface $dateTime): self
	{
		$this->dateTime = $dateTime;
		return $this;
	}

	public function getDateTime(): ?\DateTimeInterface
	{
		return $this->dateTime;
	}

	public function setCorrection(bool $correction): self
	{
		$this->correction = $correction;
		return $this;
	}

	public function getCorrection(): ?bool
	{
		return $this->correction;
	}

	public function setPrice(string|float|null $price): self
	{
		$this->price = $price !== null ? (string)$price : null;
		return $this;
	}

	public function getPrice(): ?string
	{
		return $this->price;
	}

	public function setStockLevel(int $stockLevel): self
	{
		$this->stockLevel = $stockLevel;
		return $this;
	}

	public function getStockLevel(): ?int
	{
		return $this->stockLevel;
	}

	public function setPart(?Part $part): self
	{
		$this->part = $part;
		return $this;
	}

	public function getPart(): ?Part
	{
		return $this->part;
	}

	public function setUser(?User $user): self
	{
		$this->user = $user;
		return $this;
	}

	public function getUser(): ?User
	{
		return $this->user;
	}

	/**
	 * Whether this (already denormalized) state differs from `$previous` only
	 * in price and/or comment — i.e. nothing that changes what the history
	 * records (amount, user, part, correction flag, time) was touched.
	 */
	public function differsOnlyInPriceOrCommentFrom(self $previous): bool
	{
		return $this->stockLevel === $previous->stockLevel
			&& $this->correction === $previous->correction
			&& $this->part?->getId() === $previous->part?->getId()
			&& $this->user?->getId() === $previous->user?->getId()
			&& $this->dateTime->getTimestamp() === $previous->dateTime->getTimestamp();
	}

	public function checkPrice(): void
	{
		if ($this->getStockLevel() < 0 && $this->getPrice() !== null) {
			$this->setPrice(null);
		}
	}

	public function isRemoval(): bool
	{
		return $this->getStockLevel() < 0;
	}

	public function setComment(?string $comment): self
	{
		$this->comment = $comment;
		return $this;
	}

	public function getComment(): ?string
	{
		return $this->comment;
	}
}
