<?php

namespace Limas\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Service\CronLoggerService;


class CronLoggerTest
	extends WebTestCase
{
	public function testCronLogger(): void
	{
		$container = self::getContainer();
		$cronlogger = $container->get(CronLoggerService::class);

		$cronLoggerEntry = $cronlogger->markCronRun('test');

		self::assertEquals('test', $cronLoggerEntry->getCronjob());
		self::assertInstanceOf(\DateTime::class, $cronLoggerEntry->getLastRunDate());

		$cronLoggerEntry->setLastRunDate(new \DateTime('1999-01-01 00:00:00'));

		$inactiveCronjobs = $cronlogger->getInactiveCronjobs(['test']);
		self::assertEquals(false, in_array('test', $inactiveCronjobs, true));

		$container->get(EntityManagerInterface::class)->flush();

		$inactiveCronjobs = $cronlogger->getInactiveCronjobs(['test']);

		self::assertEquals(true, in_array('test', $inactiveCronjobs, true));
	}
}
