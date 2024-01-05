<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Part;
use Limas\Entity\PartParameter;
use Limas\Entity\ProjectRun;
use Limas\Entity\ProjectRunPart;
use Limas\Entity\StockEntry;
use Limas\Exceptions\InternalPartNumberNotUniqueException;
use Limas\Exceptions\PartLimitExceededException;
use Limas\Service\PartService;
use Limas\Service\UserService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;


class PartActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly ItemProvider           $dataProvider,
		private readonly EntityManagerInterface $entityManager,
		private readonly PartService            $partService,
		private readonly UserService            $userService,
		private readonly SerializerInterface    $serializer
	)
	{
	}

	#[Route(path: '/api/parts/massRemoveStock', defaults: ['method' => 'GET', '_format' => 'json'])]
	public function massRemoveStockAction(Request $request, IriConverterInterface $iriConverter): void
	{
		$removals = Json::decode($request->get('removals'));
		if (!is_array($removals)) {
			throw new \RuntimeException('removals parameter must be an array');
		}

		$projects = Json::decode($request->get('projects'));
		if (!is_array($projects)) {
			throw new \RuntimeException('projects parameter must be an array');
		}

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
				throw new \RuntimeException('Each removal must have the part property defined');
			}
			if (!property_exists($removal, 'amount')) {
				throw new \RuntimeException('Each removal must have the amount property defined');
			}
			if (!property_exists($removal, 'lotNumber')) {
				throw new \RuntimeException('Each removal must have the lotNumber property defined');
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

	public function getParameterNamesAction(): JsonResponse
	{
		return $this->json(
			$this->entityManager->createQueryBuilder()
				->select('p.name, p.description, p.valueType, u.name AS unitName')
				->from(PartParameter::class, 'p')
				->leftJoin('p.unit', 'u')
				->groupBy('p.name, p.description, p.valueType, u.name, u.symbol')
				->getQuery()->getArrayResult()
		);
	}

	public function getParameterValuesAction(Request $request): JsonResponse
	{
		if (!$request->query->has('name')) {
			throw new \InvalidArgumentException("The parameter 'name' must be given");
		}
		if (!$request->query->has('valueType')) {
			throw new \InvalidArgumentException("The parameter 'valueType' must be given");
		}

		$qb = $this->entityManager->createQueryBuilder();
		if ($request->query->get('valueType') === 'string') {
			return $this->json(
				$qb->select('p.stringValue AS value')
					->from(PartParameter::class, 'p')
					->andWhere($qb->expr()->eq('p.name', ':name'))
					->andWhere($qb->expr()->eq('p.valueType', ':valueType'))
					->groupBy('p.stringValue')
					->setParameters([
						'name' => $request->query->get('name'),
						'valueType' => $request->query->get('valueType'),
					])
					->getQuery()->getArrayResult()
			);
		}
		return $this->json(
			$qb->select('p.value')
				->from(PartParameter::class, 'p')
				->andWhere($qb->expr()->eq('p.name', ':name'))
				->andWhere($qb->expr()->eq('p.valueType', ':valueType'))
				->groupBy('p.value')
				->setParameters([
					'name' => $request->query->get('name'),
					'valueType' => $request->query->get('valueType'),
				])
				->getQuery()->getArrayResult()
		);
	}

	public function GetPartsAction(CollectionProvider $dataProvider): iterable
	{
		$items = $dataProvider->provide(new GetCollection(class: Part::class));
		foreach ($items as $part) {
			if ($part->isMetaPart()) {
				$sum = 0;
				foreach ($this->partService->getMatchingMetaParts($part) as $matchingPart) {
					$sum += $matchingPart->getStockLevel();
				}
				$part->setStockLevel($sum);
			}
		}
		return $items;
	}

	public function PartPostAction(Request $request): Part
	{
		if ($this->partService->checkPartLimit()) {
			throw new PartLimitExceededException;
		}
		$part = $this->serializer->deserialize($request->getContent(), Part::class, 'jsonld');
		if (!$this->partService->isInternalPartNumberUnique((string)$part->getInternalPartNumber())) {
			throw new InternalPartNumberNotUniqueException;
		}
		return $part;
	}

	public function PartPutAction(Request $request, int $id): Part
	{
		/*
		 * Workaround to ensure stockLevels are not overwritten in a PUT request
		 * @see https://github.com/partkeepr/PartKeepr/issues/551
		 */
		$data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
		unset($data['stockLevels']);
		$requestData = Json::encode($data);

		$data = $this->getItem($this->dataProvider, Part::class, $id);
		$this->entityManager->refresh($data);

		$part = $this->serializer->deserialize($requestData, Part::class, $request->attributes->get('_api_format') ?? $request->getRequestFormat(), [AbstractNormalizer::OBJECT_TO_POPULATE => $data])
			->recomputeStockLevels();
		$this->entityManager->flush();

		if (!$this->partService->isInternalPartNumberUnique($part->getInternalPartNumber(), $part)) {
			throw new InternalPartNumberNotUniqueException;
		}

		return $part;
	}

	public function AddStockAction(Request $request, int $id): Part
	{
		$part = $this->getItem($this->dataProvider, Part::class, $id);
		$stock = (new StockEntry)
			->setUser($this->getUser())
			->setStockLevel($request->request->getInt('quantity'));
		if ($request->request->get('price') !== null) {
			$stock->setPrice((float)$request->request->get('price'));
		}
		if ($request->request->get('comment') !== null) {
			$stock->setComment($request->request->get('comment'));
		}

		$part->addStockLevel($stock);
		$this->entityManager->persist($stock);
		$this->entityManager->flush();

		return $part;
	}

	public function RemoveStockAction(Request $request, int $id): Part
	{
		$part = $this->getItem($this->dataProvider, Part::class, $id);
		$stock = (new StockEntry)
			->setUser($this->userService->getCurrentUser())
			->setStockLevel(0 - $request->request->getInt('quantity'));
		if ($request->request->get('comment') !== null) {
			$stock->setComment($request->request->get('comment'));
		}

		$part->addStockLevel($stock);
		$this->entityManager->persist($stock);
		$this->entityManager->flush();

		return $part;
	}

	public function SetStockAction(Request $request, int $id): Part
	{
		$part = $this->getItem($this->dataProvider, Part::class, $id);
		if (0 !== ($correctionQuantity = $request->request->getInt('quantity') - $part->getStockLevel())) {
			$stock = (new StockEntry)
				->setUser($this->userService->getCurrentUser())
				->setStockLevel($correctionQuantity);
			if ($request->request->get('comment') !== null) {
				$stock->setComment($request->request->get('comment'));
			}
			$part->addStockLevel($stock);
			$this->entityManager->persist($stock);
			$this->entityManager->flush();
		}
		return $part;
	}
}
