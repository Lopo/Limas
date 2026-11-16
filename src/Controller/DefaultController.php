<?php

namespace Limas\Controller;

use Limas\Object\SystemInformationRecord;
use Limas\Service\SystemService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Routing\Attribute\Route;


class DefaultController
	extends AbstractController
{
	private const array ADMIN_ONLY_SYSTEM_INFORMATION = [
		'Operating System Release',
		'Load Average (1, 5, 15 minutes)',
		'Data Directory',
		'Query Cache Implementation',
		'Metadata Cache Implementation'
	];

	#[Route(path: '/api/system_status', name: 'getsystemstatus', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getSystemStatusAction(SystemService $systemService): JsonResponse
	{
		$status = $systemService->getSystemStatus();
		// The pending schema DDL is for admins; the frontend only needs
		// schemaStatus to decide whether to warn
		if (!$this->isGranted('ROLE_ADMIN')) {
			unset($status['schemaQueries']);
		}
		return $this->json($status);
	}

	#[Route(path: '/api/system_information', name: 'getsysteminformation', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getSystemInformationAction(SystemService $systemService): JsonResponse
	{
		$records = $systemService->getSystemInformation();
		// Host details that only help an admin — absolute
		// paths, OS build, load, cache backends — stay admin-only; everyone
		// else still gets versions, upload limits and worker health
		if (!$this->isGranted('ROLE_ADMIN')) {
			$records = array_values(array_filter(
				$records,
				static fn(SystemInformationRecord $r): bool => !in_array($r->name, self::ADMIN_ONLY_SYSTEM_INFORMATION, true)
			));
		}
		return $this->json($records);
	}

	#[Route(path: '/api/disk_space', name: 'getdiskfreespace', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getDiskFreeSpaceAction(SystemService $systemService): JsonResponse
	{
		return $this->json([
			'disk_total' => $systemService->getTotalDiskSpace(),
			'disk_used' => $systemService->getUsedDiskSpace()
		]);
	}

	#[Route(path: '/api/currencies', name: 'getcurrencies', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getCurrenciesAction(): JsonResponse
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
