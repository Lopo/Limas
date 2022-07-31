<?php

namespace Limas\Controller\Actions;

use Limas\Service\SystemPreferenceService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


class SystemPreferenceActions
	extends AbstractController
{
	public function __construct(private readonly SystemPreferenceService $service)
	{
	}

	public function getAction(): JsonResponse
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

	#[Route(path: '/api/system_preferences', methods: ['POST', 'PUT'])]
	public function setAction(Request $request): JsonResponse
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

	public function deleteAction(Request $request): void
	{
		if ($request->request->has('preferenceKey')) {
			$this->service->deletePreference($request->request->get('preferenceKey'));
		} else {
			throw new \Exception('Invalid format');
		}
	}
}
