<?php

namespace Limas\Controller\Actions\SystemPreference;

use Limas\Service\SystemPreferenceService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class Set
	extends AbstractController
{
	public function __construct(private readonly SystemPreferenceService $service)
	{
	}

	public function __invoke(Request $request): JsonResponse
	{
		$data = Json::decode($request->getContent());
		if (!property_exists($data, 'preferenceKey') || !property_exists($data, 'preferenceValue')) {
			throw new \Exception('Invalid format');
		}
		$preference = $this->service->setSystemPreference($data->preferenceKey, $data->preferenceValue);
		return new JsonResponse([
			'preferenceKey' => $preference->getPreferenceKey(),
			'preferenceValue' => $preference->getPreferenceValue()
		]);
	}
}
