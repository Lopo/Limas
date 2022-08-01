<?php

namespace Limas\Controller;

use Sonata\Exporter\Writer\CsvWriter;
use Sonata\Exporter\Writer\XmlExcelWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class ExportController
	extends AbstractController
{
	#[Route('/api/export', name: 'api_export', defaults: ['method' => 'GET', '_format' => 'json'], priority: 100)]
	public function exportAction(Request $request): Response
	{
		$contentTypes = $request->getAcceptableContentTypes();

		$exporter = false;
		$file = tempnam(sys_get_temp_dir(), 'limas_export');
		unlink($file);

		foreach ($contentTypes as $contentType) {
			switch ($contentType) {
				case 'text/comma-separated-values':
					$exporter = new CsvWriter($file, ',', '"', '\\', false);
					break;
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
					$exporter = new XmlExcelWriter($file, false);
					break;
			}
		}

		if ($exporter === false) {
			throw new \Exception('No or invalid format specified');
		}

		$content = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

		$exporter->open();
		foreach ($content as $item) {
			$exporter->write(array_map(static fn($val) => $val ?? '', $item));
		}
		$exporter->close();

		return new Response(file_get_contents($file), Response::HTTP_OK);
	}
}
