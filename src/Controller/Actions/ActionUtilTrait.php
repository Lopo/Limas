<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


trait ActionUtilTrait
{
	/**
	 * Gets an item using the data provider. Throws a 404 error if not found.
	 */
	private function getItem(ItemProvider $dataProvider, string $resourceType, array|int|object|string $id): object
	{
		if (null === ($data = $dataProvider->provide(new Get(uriVariables: ['id' => new Link(parameterName: 'id', fromClass: $resourceType, identifiers: ['id'])], class: $resourceType), ['id' => $id]))) {
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
	 * Extract resource type and format request attributes
	 *
	 * @throws RuntimeException if the request does not contain required attributes
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
