<?php

namespace Limas\Controller;

use Limas\Service\StatisticService;
use Nette\Utils\DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class StatisticController
	extends AbstractController
{
	public function __construct(private readonly StatisticService $statisticService)
	{
	}

	#[Route('/api/statistics/current', name: 'getcurrentstatistic', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getCurrentStatisticAction(): Response
	{
		$aData = [
			'partCount' => $this->statisticService->getPartCount(),
			'partCategoryCount' => $this->statisticService->getPartCategoryCount(),
			'totalPrice' => $this->statisticService->getTotalPrice(),
			'averagePrice' => $this->statisticService->getAveragePrice(),
			'partsWithPrice' => $this->statisticService->getPartCount(true),
			'units' => $this->statisticService->getUnitCounts()
		];
		$aData['partsWithoutPrice'] = $aData['partCount'] - $aData['partsWithPrice'];
		return $this->json($aData);
	}

	#[Route('/api/statistics/sampled', name: 'getsampledstatistic', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getSampledStatisticAction(Request $request): Response
	{
		return $this->json($this->statisticService->getSampledStatistics(DateTime::from($request->get('start')), DateTime::from($request->get('end'))));
	}

	#[Route('/api/statistics/range', name: 'getstatisticrange', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function getStatisticRangeAction(): Response
	{
		return $this->json($this->statisticService->getStatisticRange());
	}
}
