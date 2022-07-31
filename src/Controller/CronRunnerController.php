<?php

namespace Limas\Controller;

use Limas\Service\CronLoggerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class CronRunnerController
	extends AbstractController
{
	#[Route(path: '/api/cron/run', name: 'runcrons', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function runCronsAction(CronLoggerService $service): Response
	{
		$service->runCrons();
		return new Response('');
	}
}
