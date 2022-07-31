<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\ProjectPart;
use Limas\Entity\Report;
use Limas\Service\PartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;


#[AsController]
class ProjectReportActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly SerializerInterface       $serializer,
		private readonly EntityManagerInterface    $entityManager,
		private readonly PartService               $partService,
		private readonly ItemDataProviderInterface $dataProvider
	)
	{
	}

	public function createReportAction(Report $data): JsonResponse
	{
		foreach ($data->getReportProjects() as $reportProject) {
			foreach ($reportProject->getProject()->getParts() as $projectPart) {
				$overage = $projectPart->getOverageType() === ProjectPart::OVERAGE_TYPE_PERCENT
					? $reportProject->getQuantity() * $projectPart->getQuantity() * ($projectPart->getOverage() / 100)
					: $projectPart->getOverage();

				$quantity = $reportProject->getQuantity() * $projectPart->getQuantity() + $overage;
				$data->addPartQuantity($projectPart->getPart(), $projectPart, $quantity);
			}
		}

		$this->entityManager->persist($data);
		$this->entityManager->flush();

		return new JsonResponse($this->serializer->serialize($data, 'jsonld'), Response::HTTP_OK, ['Content-Type' => 'text/json'], true);
	}

	public function getReportAction($id): JsonResponse
	{
		$report = $this->getItem($this->dataProvider, Report::class, $id);
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
