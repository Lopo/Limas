<?php

namespace Limas\Controller;

use Limas\Service\ReflectionService;
use Nette\Utils\Strings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class ReflectionController
	extends AbstractController
{
	public function __construct(private readonly ReflectionService $reflectionService)
	{
	}

	#[Route('/entity/{cls}', name: 'app_reflection')]
	public function index(string $cls): Response
	{
		if (Strings::match($cls, '/^[A-Z][a-zA-Z]+$/') === null) {
			throw $this->createNotFoundException();
		}
		$entity = 'Limas\\Entity\\' . $cls;
		if (!class_exists($entity)) {
			throw $this->createNotFoundException();
		}

		$response = $this->render('reflection/model.js.twig', $this->reflectionService->getEntity($entity));
		$response->headers->set('Content-Type', 'application/javascript');
		return $response;
	}

	#[Route('/asset/models.js', name: 'app_asset_entities')]
	public function assetEntites()
	{
		$content = '';
		foreach ($this->reflectionService->getAssetEntities() as $entity) {
			$content .= $this->renderView('reflection/model.js.twig', $entity);
		}
		return new Response($content, Response::HTTP_OK, ['Content-Type' => 'application/javascript']);
	}
}
