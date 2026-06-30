<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Part;
use Limas\Entity\PartParameter;
use Limas\Entity\ProjectRun;
use Limas\Entity\ProjectRunPart;
use Limas\Entity\StockEntry;
use Limas\Entity\User;
use Limas\Exceptions\InternalPartNumberNotUniqueException;
use Limas\Exceptions\PartLimitExceededException;
use Limas\Service\PartService;
use Limas\Service\UserService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
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
		$removals = Json::decode($request->query->get('removals'));
		if (!is_array($removals)) {
			throw new \RuntimeException('removals parameter must be an array');
		}

		$projects = Json::decode($request->query->get('projects'));
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
		assert($user instanceof User, 'massRemoveStockAction requires an authenticated user');

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

		$name = $request->query->get('name');
		$valueType = $request->query->get('valueType');

		$qb = $this->entityManager->createQueryBuilder();
		if ($valueType === 'string') {
			return $this->json(
				$qb->select('p.stringValue AS value')
					->from(PartParameter::class, 'p')
					->andWhere($qb->expr()->eq('p.name', ':name'))
					->andWhere($qb->expr()->eq('p.valueType', ':valueType'))
					->groupBy('p.stringValue')
					->setParameter('name', $name)
					->setParameter('valueType', $valueType)
					->getQuery()->getArrayResult()
			);
		}
		return $this->json(
			$qb->select('p.value')
				->from(PartParameter::class, 'p')
				->andWhere($qb->expr()->eq('p.name', ':name'))
				->andWhere($qb->expr()->eq('p.valueType', ':valueType'))
				->groupBy('p.value')
				->setParameter('name', $name)
				->setParameter('valueType', $valueType)
				->getQuery()->getArrayResult()
		);
	}

	public function getPartsAction(Request $request, CollectionProvider $dataProvider): iterable
	{
		$items = $dataProvider->provide(new GetCollection(class: Part::class));
		foreach ($items as $part) {
			if ($part->isMetaPart()) {
				$sum = 0;
				$matches = [];
				foreach ($this->partService->getMatchingMetaParts($part) as $matchingPart) {
					$sum += $matchingPart->getStockLevel();
					// Flat scalar list — FE only needs the IRI + stock to
					// resolve a Meta-Part to a concrete pick in the Project
					// Report flow. Issue #14: this field was on the entity
					// + in the `default` group but never populated.
					$matches[] = [
						'@id' => '/api/parts/' . $matchingPart->getId(),
						'name' => $matchingPart->getName(),
						'stockLevel' => $matchingPart->getStockLevel()
					];
				}
				$part->setStockLevel($sum);
				$part->setMetaPartMatches($matches);
			}
		}

		// Optional flat parameter map for custom list-view columns (PK #1217).
		// Without this the Param Renderer on a column has no PartParameter data
		// to read — list serialisation strips the `parameters` collection
		// (group=detail) to avoid the N+1 fix's old performance hit.
		$wanted = $request->query->all('includeParameters');
		$wanted = array_values(array_filter(array_map('strval', $wanted), static fn(string $s): bool => $s !== ''));
		if ($wanted !== []) {
			$this->attachParamValues($items, $wanted);
		}

		return $items;
	}

	/**
	 * Bulk-load the requested PartParameter rows in ONE query and attach a
	 * flat {name: displayString} map to each Part. Mirrors the FE
	 * Limas.PartManager.formatParameter output so the same renderer works
	 * whether reading list paramValues or a fully detail-loaded record.
	 *
	 * @param iterable<Part> $parts
	 * @param string[] $wantedNames
	 */
	private function attachParamValues(iterable $parts, array $wantedNames): void
	{
		$ids = [];
		$byId = [];
		foreach ($parts as $p) {
			$pid = $p->getId();
			if ($pid === null) {
				continue;
			}
			$ids[] = $pid;
			$byId[$pid] = $p;
		}
		if ($ids === []) {
			return;
		}

		$qb = $this->entityManager->createQueryBuilder()
			->select('pp', 'u', 'sp', 'minSp', 'maxSp', 'IDENTITY(pp.part) AS partId')
			->from(\Limas\Entity\PartParameter::class, 'pp')
			->leftJoin('pp.unit', 'u')
			->leftJoin('pp.siPrefix', 'sp')
			->leftJoin('pp.minSiPrefix', 'minSp')
			->leftJoin('pp.maxSiPrefix', 'maxSp')
			->where('IDENTITY(pp.part) IN (:ids)')
			->andWhere('pp.name IN (:names)')
			->setParameter('ids', $ids)
			->setParameter('names', $wantedNames);

		$staged = [];
		foreach ($qb->getQuery()->getResult() as $row) {
			/** @var \Limas\Entity\PartParameter $pp */
			$pp = $row[0];
			$partId = (int)$row['partId'];
			$staged[$partId][$pp->getName()] = self::formatParameterDisplay($pp);
		}
		foreach ($staged as $partId => $map) {
			if (isset($byId[$partId])) {
				$byId[$partId]->setParamValues($map);
			}
		}
	}

	/**
	 * Backend mirror of assets/limas/Components/Part/PartsManager.js
	 * `formatParameter` — kept here to keep paramValues a flat string map
	 * over the wire instead of round-tripping every PartParameter sub-field
	 */
	private static function formatParameterDisplay(\Limas\Entity\PartParameter $pp): string
	{
		if ($pp->getValueType() === \Limas\Entity\PartParameter::VALUE_TYPE_STRING) {
			return $pp->getStringValue();
		}

		$unit = $pp->getUnit()?->getSymbol() ?? '';
		$siPrefix = $pp->getSiPrefix()?->getSymbol() ?? '';
		$minSi = $pp->getMinSiPrefix()?->getSymbol() ?? '';
		$maxSi = $pp->getMaxSiPrefix()?->getSymbol() ?? '';
		$value = $pp->getValue();
		$minValue = $pp->getMinValue();
		$maxValue = $pp->getMaxValue();

		$parts = [];
		if ($value !== null) {
			$parts[] = self::trim($value) . $siPrefix . $unit;
		}
		if ($minValue !== null || $maxValue !== null) {
			$min = $minValue !== null ? (self::trim($minValue) . $minSi . $unit) : '';
			$max = $maxValue !== null ? (self::trim($maxValue) . $maxSi . $unit) : '';
			$parts[] = $min . '..' . $max;
		}
		return implode(' ', $parts);
	}

	private static function trim(float $f): string
	{
		// Strip trailing zeros: 10.0 → "10", 1.25 → "1.25", 0.0 → "0"
		$s = rtrim(rtrim(sprintf('%.6f', $f), '0'), '.');
		return $s === '' ? '0' : $s;
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
		$data = Json::decode($request->getContent());
		$part = $this->getItem($this->dataProvider, Part::class, $id);
		$user = $this->getUser();
		assert($user instanceof User, 'AddStockAction requires an authenticated user');
		$stock = (new StockEntry)
			->setUser($user)
			->setStockLevel((int)$data->quantity);
		if (($data->price ?? null) !== null) {
			$stock->setPrice((float)$data->price);
		}
		if (($data->comment ?? null) !== null) {
			$stock->setComment($data->comment);
		}

		$part->addStockLevel($stock);
		$this->entityManager->persist($stock);
		$this->entityManager->flush();

		return $part;
	}

	public function RemoveStockAction(Request $request, int $id): Part
	{
		$data = Json::decode($request->getContent());
		$part = $this->getItem($this->dataProvider, Part::class, $id);
		$stock = (new StockEntry)
			->setUser($this->userService->getCurrentUser())
			->setStockLevel(0 - (int)$data->quantity);
		if (($data->comment ?? null) !== null) {
			$stock->setComment($data->comment);
		}

		$part->addStockLevel($stock);
		$this->entityManager->persist($stock);
		$this->entityManager->flush();

		return $part;
	}

	public function SetStockAction(Request $request, int $id): Part
	{
		$data = Json::decode($request->getContent());
		$part = $this->getItem($this->dataProvider, Part::class, $id);
		if (0 !== ($correctionQuantity = (int)$data->quantity - $part->getStockLevel())) {
			$stock = (new StockEntry)
				->setUser($this->userService->getCurrentUser())
				->setStockLevel($correctionQuantity);
			if (($data->comment ?? null) !== null) {
				$stock->setComment($data->comment);
			}
			$part->addStockLevel($stock);
			$this->entityManager->persist($stock);
			$this->entityManager->flush();
		}
		return $part;
	}

	/**
	 * Bulk update of `storageLocation` across many Parts in a single
	 * transaction. Request body shape:
	 *
	 *   {
	 *     "parts": ["/api/parts/1", "/api/parts/42", …],
	 *     "storageLocation": "/api/storage_locations/7"
	 *   }
	 *
	 * Response shape:
	 *
	 *   { "moved": N, "failed": [{"part": "/api/parts/3", "reason": "..."}, …] }
	 *
	 * Failures don't abort — each part is tried independently. The whole
	 * batch commits at the end if at least one part moved; otherwise rolls
	 * back. PK #1193 / #664
	 */
	public function BulkMoveAction(Request $request, IriConverterInterface $iriConverter): JsonResponse
	{
		$data = Json::decode($request->getContent());
		if (!property_exists($data, 'parts') || !is_array($data->parts)) {
			throw new \RuntimeException('"parts" must be an array of IRIs');
		}
		if (!property_exists($data, 'storageLocation') || !is_string($data->storageLocation)) {
			throw new \RuntimeException('"storageLocation" must be an IRI string');
		}

		try {
			$target = $iriConverter->getResourceFromIri($data->storageLocation);
		} catch (\Throwable $e) {
			throw new \RuntimeException('storageLocation IRI did not resolve: ' . $e->getMessage());
		}

		$moved = 0;
		$failed = [];
		foreach ($data->parts as $partIri) {
			if (!is_string($partIri)) {
				$failed[] = ['part' => (string)$partIri, 'reason' => 'IRI is not a string'];
				continue;
			}
			try {
				$part = $iriConverter->getResourceFromIri($partIri);
				if (!$part instanceof Part) {
					$failed[] = ['part' => $partIri, 'reason' => 'IRI did not resolve to a Part'];
					continue;
				}
				$part->setStorageLocation($target);
				$moved++;
			} catch (\Throwable $e) {
				$failed[] = ['part' => $partIri, 'reason' => $e->getMessage()];
			}
		}

		if ($moved > 0) {
			$this->entityManager->flush();
		}

		return new JsonResponse([
			'moved' => $moved,
			'failed' => $failed
		]);
	}
}
