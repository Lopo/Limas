<?php

namespace Limas\Tests;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Limas\Service\CronLoggerService;


class CronLoggerTest
	extends WebTestCase
{
	public function testCronLogger(): void
	{
		$cronlogger = $this->getContainer()->get(CronLoggerService::class);

		$cronLoggerEntry = $cronlogger->markCronRun('test');

		$this->assertEquals('test', $cronLoggerEntry->getCronjob());
		$this->assertInstanceOf(\DateTime::class, $cronLoggerEntry->getLastRunDate());

		$cronLoggerEntry->setLastRunDate(new \DateTime('1999-01-01 00:00:00'));

		$inactiveCronjobs = $cronlogger->getInactiveCronjobs(['test']);
		$this->assertEquals(false, in_array('test', $inactiveCronjobs));

		$this->getContainer()->get('doctrine.orm.entity_manager')->flush();

		$inactiveCronjobs = $cronlogger->getInactiveCronjobs(['test']);

		$this->assertEquals(true, in_array('test', $inactiveCronjobs));
	}
}
