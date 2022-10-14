<?php

namespace Limas\Controller\Actions\UserPreference;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\UserPreference;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class Set
	extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(Request $request): JsonResponse
	{
		$user = $this->getUser();
		$data = Json::decode($request->getContent());
		if (!property_exists($data, 'preferenceKey') || !property_exists($data, 'preferenceValue')) {
			throw new \Exception('Invalid format');
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
}
