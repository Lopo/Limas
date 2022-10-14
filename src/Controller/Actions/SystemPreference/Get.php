<?php

namespace Limas\Controller\Actions\SystemPreference;

use Limas\Service\SystemPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class Get
	extends AbstractController
{
	public function __construct(private readonly SystemPreferenceService $service)
	{
	}

	public function __invoke(): JsonResponse
	{
		$preferences = $this->service->getPreferences();
		$data = [];
		foreach ($preferences as $preference) {
			$data[] = [
				'preferenceKey' => $preference->getPreferenceKey(),
				'preferenceValue' => $preference->getPreferenceValue()
			];
		}
		return new JsonResponse($data);
	}
}
