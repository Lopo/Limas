<?php

namespace Limas\Controller\Actions\ProjectReport;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\Report;
use Limas\Service\PartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;


#[AsController]
class Get
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly SerializerInterface       $serializer,
		private readonly EntityManagerInterface    $entityManager,
		private readonly PartService               $partService
	)
	{
	}

	public function __invoke($id): JsonResponse
	{
		$report = $this->getItem($this->entityManager, Report::class, $id);
		$this->calculateMissingParts($report);
		$this->prepareMetaPartInformation($report);

		return new JsonResponse($this->serializer->serialize($report, 'jsonld'), Response::HTTP_OK, ['Content-Type' => 'text/json'], true);
	}

	private function calculateMissingParts(Report $report): void
	{
		foreach ($report->getReportParts() as $reportPart) {
			$missing = $reportPart->getQuantity() - $reportPart->getPart()->getStockLevel();
			if ($missing < 0) {
				$missing = 0;
			}
			$reportPart->setMissing($missing);
		}
	}

	private function prepareMetaPartInformation(Report $report): void
	{
		foreach ($report->getReportParts() as $reportPart) {
			$subParts = [];

			if ($reportPart->getPart()->isMetaPart()) {
				$matchingParts = $this->partService->getMatchingMetaParts($reportPart->getPart());
				foreach ($matchingParts as $matchingPart) {
					$subParts[] = $this->serializer->normalize($matchingPart, 'jsonld');
				}
				$reportPart->setMetaPart(true);
				$reportPart->setSubParts($subParts);
			}
		}
	}
}
