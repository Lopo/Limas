<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Exception\RuntimeException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


trait ActionUtilTrait
{
	/**
	 * Gets an item using the data provider. Throws a 404 error if not found.
	 * @psalm-param class-string<T> $resourceType
	 * @psalm-return ?T
	 * @template T
	 */
	private function getItem(EntityManagerInterface $entityManager, string $resourceType, array|int|object|string $id): object
	{
		try {
			if (null === ($data = $entityManager->find($resourceType, $id))) {
				throw new NotFoundHttpException('Not found');
			}
		} catch (\Throwable $e) {
			throw new NotFoundHttpException('Not found');
		}
		return $data;
	}

	private function getResourceClass(Request $request): string
	{
		if (null === ($resourceClass = $request->attributes->get('_api_resource_class'))) {
			throw new RuntimeException('The API is not properly configured.');
		}
		return $resourceClass;
	}
}
