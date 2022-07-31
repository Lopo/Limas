<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\SystemNotice;


class SystemNoticeService
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function createUniqueSystemNotice(string $type, string $title, string $description): SystemNotice
	{
		try {
			$notice = $this->entityManager->getRepository(SystemNotice::class)->findOneBy(['type' => $type]);
			if ($notice === null) {
				$notice = new SystemNotice;
				$this->entityManager->persist($notice);
			}
		} catch (\Exception $e) {
			$notice = new SystemNotice;
			$this->entityManager->persist($notice);
		}

		$notice
			->setDate(new \DateTime)
			->setTitle($title)
			->setDescription($description)
			->setType($type);

		$this->entityManager->flush();

		return $notice;
	}
}
