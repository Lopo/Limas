<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


trait ActionUtilTrait
{
	/**
	 * Gets an item using the data provider. Throws a 404 error if not found.
	 */
	private function getItem(ItemDataProviderInterface $dataProvider, string $resourceType, array|int|object|string $id): object
	{
		if (null === ($data = $dataProvider->getItem($resourceType, $id))) {
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

	/**
	 * Extract resource type and format request attributes. Throws an exception if the request does not contain required
	 * attributes.
	 */
	private function extractAttributes(Request $request): array
	{
		if (null === ($resourceType = $request->attributes->get('_api_resource_class'))
			|| null === ($format = $request->attributes->get('_api_format'))
		) {
			throw new RuntimeException('The API is not properly configured.');
		}
		return [$resourceType, $format];
	}
}
