<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\CronLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;


readonly class CronLoggerService
{
	public function __construct(
		private EntityManagerInterface $entityManager,
		private KernelInterface        $kernel
	)
	{
	}

	public function markCronRun(string $cronjob): CronLogger
	{
		try {
			$qb = $this->entityManager->getRepository(CronLogger::class)->createQueryBuilder('c');
			$result = $qb
				->where($qb->expr()->eq('c.cronjob', ':cronjob'))
				->setParameter('cronjob', $cronjob)
				->getQuery()->getSingleResult();
		} catch (\Exception $e) {
			$result = (new CronLogger)
				->setCronjob($cronjob);
			$this->entityManager->persist($result);
		}

		$result->setLastRunDate(new \DateTime);

		$this->entityManager->flush();

		return $result;
	}

	public function getInactiveCronjobs(array $requiredCronjobs): array
	{
		$qb = $this->entityManager->getRepository(CronLogger::class)->createQueryBuilder('c');
		$query = $qb->select('c.cronjob')
			->andWhere($qb->expr()->eq('c.cronjob', ':cronjob'))
			->andWhere($qb->expr()->gt('c.lastRunDate', ':date'))
			->setParameter('date', new \DateTime('1 day ago'))
			->getQuery();

		$failedCronjobs = [];

		foreach ($requiredCronjobs as $cronjob) {
			$query->setParameter('cronjob', $cronjob);

			try {
				$query->getSingleResult();
			} catch (\Exception $e) {
				$failedCronjobs[] = $cronjob;
			}
		}

		return $failedCronjobs;
	}

	public function clear(): void
	{
		$this->entityManager->createQueryBuilder()->delete(CronLogger::class)->getQuery()->execute();
	}

	public function runCrons(): void
	{
		$this->entityManager->beginTransaction();

		$cronJobs = $this->entityManager->getRepository(CronLogger::class)->findAll();

		$application = new Application($this->kernel);
		$application->setAutoExit(false);
		$output = new NullOutput;

		$minRunDate = (new \DateTime('6 hours ago'))->getTimestamp();

		foreach ($cronJobs as $cronJob) {
			if ($minRunDate - $cronJob->getLastRunDate()->getTimestamp() < 0) {
				break;
			}

			$command = $cronJob->getCronjob();

			$input = new ArrayInput([
				'command' => $command,
			]);

			$application->run($input, $output);
		}

		$this->entityManager->commit();
	}
}
