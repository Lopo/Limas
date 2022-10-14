<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Api\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Part;
use Limas\Entity\ProjectRun;
use Limas\Entity\ProjectRunPart;
use Limas\Entity\StockEntry;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;


#[AsController]
class PartActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	#[Route(path: '/api/parts/massRemoveStock', defaults: ['method' => 'GET', '_format' => 'json'])]
	public function massRemoveStockAction(Request $request, IriConverterInterface $iriConverter): void
	{
		$removals = Json::decode($request->get('removals'));
		if (!is_array($removals)) {
			throw new \Exception('removals parameter must be an array');
		}

		$projects = Json::decode($request->get('projects'));
		if (!is_array($projects)) {
			throw new \Exception('projects parameter must be an array');
		}

		/**
		 * @var ProjectRun[] $projectRuns
		 */
		$projectRuns = [];
		foreach ($projects as $projectInfo) {
			$projectRuns[$projectInfo->project] = (new ProjectRun)
				->setQuantity($projectInfo->quantity)
				->setRunDateTime(new \DateTime)
				->setProject($iriConverter->getResourceFromIri($projectInfo->project));
		}

		$user = $this->getUser();

		foreach ($removals as $removal) {
			if (!property_exists($removal, 'part')) {
				throw new \Exception('Each removal must have the part property defined');
			}
			if (!property_exists($removal, 'amount')) {
				throw new \Exception('Each removal must have the amount property defined');
			}
			if (!property_exists($removal, 'lotNumber')) {
				throw new \Exception('Each removal must have the lotNumber property defined');
			}

			/**
			 * @var Part $part
			 */
			$part = $iriConverter->getResourceFromIri($removal->part);

			$stock = (new StockEntry)
				->setStockLevel(0 - (int)$removal->amount)
				->setUser($user);
			if (property_exists($removal, 'comment')) {
				$stock->setComment($removal->comment);
			}

			$part->addStockLevel($stock);

			$projectRunPart = (new ProjectRunPart)
				->setLotNumber($removal->lotNumber)
				->setPart($part)
				->setQuantity($removal->amount);

			foreach ($projectRuns as $projectRun) {
				$projectRun->addPart($projectRunPart);
			}
		}

		foreach ($projectRuns as $projectRun) {
			$this->entityManager->persist($projectRun);
		}

		$this->entityManager->flush();
	}
}
