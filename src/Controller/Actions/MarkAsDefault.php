<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\GridPreset;
use Limas\Service\GridPresetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class MarkAsDefault
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly GridPresetService      $gridPresetService,
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(Request $request, int $id, GridPreset $data): GridPreset
	{
		$gp = $this->getItem($this->entityManager, GridPreset::class, $id);
		$this->gridPresetService->markGridPresetAsDefault($gp);
		return $gp;
	}
}
