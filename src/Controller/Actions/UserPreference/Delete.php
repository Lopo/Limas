<?php

namespace Limas\Controller\Actions\UserPreference;

use Limas\Service\UserPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class Delete
	extends AbstractController
{
	public function __construct(
		private readonly UserPreferenceService $userPreferenceService
	)
	{
	}

	public function __invoke(Request $request): void
	{
		$user = $this->getUser();

		if (!$request->request->has('preferenceKey')) {
			throw new \Exception('Invalid format');
		}
		$this->userPreferenceService->deletePreference($user, $request->request->get('preferenceKey'));
	}
}
