<?php

namespace Limas\Controller\Actions\SystemPreference;

use Limas\Service\SystemPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class Delete
	extends AbstractController
{
	public function __construct(private readonly SystemPreferenceService $service)
	{
	}

	public function __invoke(Request $request): void
	{
		if (!$request->request->has('preferenceKey')) {
			throw new \Exception('Invalid format');
		}
		$this->service->deletePreference($request->request->get('preferenceKey'));
	}
}
