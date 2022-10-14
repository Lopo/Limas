<?php

namespace Limas\Controller\Actions\ProjectReport;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\ProjectPart;
use Limas\Entity\Report;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;


#[AsController]
class Create
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly SerializerInterface       $serializer,
		private readonly EntityManagerInterface    $entityManager
	)
	{
	}

	public function __invoke(Report $data): JsonResponse
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
}
