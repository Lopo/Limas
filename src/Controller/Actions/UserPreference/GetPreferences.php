<?php

namespace Limas\Controller\Actions\UserPreference;

use Limas\Service\UserPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;


#[AsController]
class GetPreferences
	extends AbstractController
{
	public function __construct(
		private readonly UserPreferenceService $userPreferenceService,
		private readonly SerializerInterface   $serializer
	)
	{
	}

	public function __invoke(): JsonResponse
	{
		$user = $this->getUser();
		$preferences = $this->userPreferenceService->getPreferences($user);
		return new JsonResponse($this->serializer->normalize($preferences, 'json'));
	}
}
