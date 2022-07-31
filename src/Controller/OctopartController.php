<?php

namespace Limas\Controller;

use Limas\Service\OctoPartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


class OctopartController
	extends AbstractController
{
	public function __construct(private readonly OctoPartService $octoPartService)
	{
	}

	#[Route(path: '/api/octopart/get/{id}', name: 'api_octopart_get', defaults: ['_format' => 'json'], methods: ['GET'])]
	public function indexAction(string $id): object
	{
		return $this->octoPartService->getPartByUID($id);
	}

	#[Route(path: '/api/octopart/query/', name: 'api_octopart_query', defaults: ['_format' => 'json'], methods: ['GET'])]
	public function getPartsByQueryAction(Request $request): JsonResponse
	{
		$start = 1;
		$responseData = [];

		$query = $request->query->get('q');

		if ($request->query->has('page')) {
			$start = $request->query->get('page');
		}

		$data = $this->octoPartService->getPartyByQuery($query, $start);

		$errors = $data['errors'];
		$data = $data['data']/*['search']*/
		;

		$responseData['hits'] = $data['hits'];
		$responseData['results'] = [];
		$responseData['errors'] = $errors;

		if ($data) {
			foreach ($data['results'] as $result) {
				$part = $result['part'];
				$responseData['results'][] = [
					'mpn' => $part['mpn'],
					'title' => $part['short_description'],
					'manufacturer' => $part['manufacturer']['name'],
					'numOffers' => count($part['sellers']),
					'numSpecs' => count($part['specs']),
					'numDatasheets' => count($part['document_collections']),
					'url' => 'https://octopart.com' . $part['slug'],
					'uid' => $part['id']
				];
			}
		}

		return new JsonResponse($responseData);
	}
}
