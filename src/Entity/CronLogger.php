<?php

namespace Limas\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class CronLogger
	extends BaseEntity
{
	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	private \DateTimeInterface $lastRunDate;
	#[ORM\Column(type: Types::STRING, unique: true)]
	private string $cronjob;


	public function getLastRunDate(): ?\DateTimeInterface
	{
		return $this->lastRunDate;
	}

	public function setLastRunDate(\DateTimeInterface $lastRunDate): self
	{
		$this->lastRunDate = $lastRunDate;
		return $this;
	}

	public function getCronjob(): string
	{
		return $this->cronjob;
	}

	public function setCronjob(string $cronjob): self
	{
		$this->cronjob = $cronjob;
		return $this;
	}
}
