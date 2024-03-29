<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use Limas\Entity\PartMeasurementUnit;
use Limas\Service\PartMeasurementUnitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class SetDefaultUnit
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(private readonly ItemProvider $dataProvider)
	{
	}

	public function __invoke(Request $request, int $id, PartMeasurementUnitService $partMeasurementUnitService): PartMeasurementUnit
	{
		$partMeasurementUnit = $this->getItem($this->dataProvider, PartMeasurementUnit::class, $id);
		$partMeasurementUnitService->setDefault($partMeasurementUnit);
		return $partMeasurementUnit;
	}
}
