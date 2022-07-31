<?php

namespace Limas\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Service\ReflectionService;
use Nette\Utils\Strings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class ReflectionController
	extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly ReflectionService      $reflectionService
	)
	{
	}

	#[Route('/entity/{cls}', name: 'app_reflection')]
	public function index(string $cls): Response
	{
		if (Strings::match($cls, '/^[A-Z][a-zA-Z]+$/') === null) {
			throw $this->createNotFoundException();
		}
		$entity = 'Limas\Entity\\' . $cls;
		if (!class_exists($entity)) {
			throw $this->createNotFoundException();
		}

		$response = $this->render('reflection/model.js.twig', $this->reflectionService->getEntity($entity));
		$response->headers->set('Content-Type', 'application/javascript');
		return $response;
	}
}
