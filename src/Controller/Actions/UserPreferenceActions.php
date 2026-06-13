<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\UserPreference;
use Limas\Service\UserPreferenceService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;


class UserPreferenceActions
	extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly UserPreferenceService  $userPreferenceService,
		private readonly NormalizerInterface    $normalizer
	)
	{
	}

	public function getPreferencesAction(): JsonResponse
	{
		$user = $this->getUser();
		assert($user instanceof \Limas\Entity\User);
		$preferences = $this->userPreferenceService->getPreferences($user);
		return new JsonResponse($this->normalizer->normalize($preferences, 'json'));
	}

	#[Route(path: '/api/user_preferences', name: 'UserPreferenceSet', methods: ['POST', 'PUT'])]
	public function setPreferenceAction(Request $request): JsonResponse
	{
		$user = $this->getUser();
		assert($user instanceof \Limas\Entity\User);
		$data = Json::decode($request->getContent());
		if (!property_exists($data, 'preferenceKey') || !property_exists($data, 'preferenceValue')) {
			throw new \RuntimeException('Invalid format');
		}

		$preference = $this->entityManager->getRepository(UserPreference::class)->findOneBy(['user' => $user, 'preferenceKey' => $data->preferenceKey]);
		if ($preference === null) {
			$preference = new UserPreference($user, $data->preferenceKey, $data->preferenceValue);
		} else {
			$preference->setPreferenceValue($data->preferenceValue);
		}
		$this->entityManager->persist($preference);
		$this->entityManager->flush();

		return $this->json([
			'preferenceKey' => $preference->getPreferenceKey(),
			'preferenceValue' => $preference->getPreferenceValue()
		]);
	}

	public function deletePreferenceAction(Request $request): void
	{
		if ($request->request->has('preferenceKey')) {
			$user = $this->getUser();
			assert($user instanceof \Limas\Entity\User);
			$this->userPreferenceService->deletePreference($user, $request->request->get('preferenceKey'));
		} else {
			throw new \RuntimeException('Invalid format');
		}
	}
}
