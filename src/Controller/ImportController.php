<?php

namespace Limas\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use Limas\Service\ImporterService;
use Limas\Service\UploadedFileService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


class ImportController
	extends AbstractController
{
	public function __construct(
		private readonly ImporterService       $importerService,
		private readonly IriConverterInterface $iriConverter,
		private readonly UploadedFileService   $uploadedFileService
	)
	{
	}

	#[Route(path: '/getSource/', name: 'getsource')]
	public function getSourceAction(Request $request): JsonResponse
	{
		return new JsonResponse($this->extractCSVData($request->get('file')));
	}

	#[Route(path: '/getPreview/', name: 'getpreview', methods: ['POST'])]
	public function getPreviewAction(Request $request): JsonResponse
	{
		$this->importerService->setBaseEntity($request->get('baseEntity'));
		$this->importerService->setImportConfiguration(Json::decode($request->get('configuration')));
		$this->importerService->setImportData($this->extractCSVData($request->get('file'), false));

		try {
			list($entities, $logs) = $this->importerService->import(true);
		} catch (\Exception $e) {
			$logs = [$e->getMessage()];
		}

		return new JsonResponse(['logs' => $logs]);
	}

	#[Route(path: '/executeImport/', name: 'import', methods: ['POST'])]
	public function importAction(Request $request): JsonResponse
	{
		$this->importerService->setBaseEntity($request->get('baseEntity'));
		$this->importerService->setImportConfiguration(Json::decode($request->get('configuration')));
		$this->importerService->setImportData($this->extractCSVData($request->get('file'), false));
		list($entities, $logs) = $this->importerService->import();

		return new JsonResponse(['logs' => $logs]);
	}

	protected function extractCSVData(string $tempFileIRI, bool $includeHeaders = true): array
	{
		$tempUploadedFile = $this->iriConverter->getItemFromIri($tempFileIRI);

		$tempFile = tempnam(sys_get_temp_dir(), 'import');
		file_put_contents($tempFile, $this->uploadedFileService->getStorage($tempUploadedFile)->read($tempUploadedFile->getFullFilename()));
		$fp = fopen($tempFile, 'r');

		$data = [];

		if (!$includeHeaders) {
			fgetcsv($fp);
		}
		while (($row = fgetcsv($fp)) !== false) {
			$data[] = $row;
		}

		unlink($tempFile);

		return $data;
	}
}
