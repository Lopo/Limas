<?php

namespace Limas\Controller\Actions\Part;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\Part;
use Limas\Exceptions\InternalPartNumberNotUniqueException;
use Limas\Service\PartService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;


#[AsController]
class Put
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly PartService            $partService,
		private readonly SerializerInterface    $serializer
	)
	{
	}

	public function __invoke(Request $request, int $id): Part
	{
		/*
		 * Workaround to ensure stockLevels are not overwritten in a PUT request
		 * @see https://github.com/partkeepr/PartKeepr/issues/551
		 */
		$data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
		unset($data['stockLevels']);
		$requestData = Json::encode($data);

		$data = $this->getItem($this->entityManager, Part::class, $id);
		$part = $this->serializer->deserialize($requestData, Part::class, $request->attributes->get('_api_format') ?? $request->getRequestFormat(), [AbstractNormalizer::OBJECT_TO_POPULATE => $data])
			->recomputeStockLevels();
		$this->entityManager->flush();

		if (!$this->partService->isInternalPartNumberUnique($part->getInternalPartNumber(), $part)) {
			throw new InternalPartNumberNotUniqueException;
		}

		return $part;
	}
}
