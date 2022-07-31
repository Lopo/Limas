<?php

namespace Limas\Controller;

use Limas\Service\SystemService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Routing\Annotation\Route;


class DefaultController
	extends AbstractController
{
	#[Route(path: '/api/system_status', name: 'getsystemstatus', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getSystemStatusAction(SystemService $systemService): Response
	{
		return $this->json($systemService->getSystemStatus());
	}

	#[Route(path: '/api/system_information', name: 'getsysteminformation', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getSystemInformationAction(SystemService $systemService): Response
	{
		return $this->json($systemService->getSystemInformation());
	}

	#[Route(path: '/api/disk_space', name: 'getdiskfreespace', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getDiskFreeSpaceAction(SystemService $systemService): Response
	{
		return $this->json([
			'disk_total' => $systemService->getTotalDiskSpace(),
			'disk_used' => $systemService->getUsedDiskSpace()
		]);
	}

	#[Route(path: '/api/currencies', name: 'getcurrencies', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getCurrenciesAction(): Response
	{
		$currencies = [];
		foreach (Currencies::getNames() as $code => $name) {
			$currencies[] = [
				'code' => $code,
				'name' => $name,
				'symbol' => Currencies::getSymbol($code),
			];
		}

		return $this->json($currencies);
	}
}
