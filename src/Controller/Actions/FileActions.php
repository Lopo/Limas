<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Gaufrette\Exception\FileNotFound;
use Limas\Entity\UploadedFile;
use Limas\Service\MimetypeIconService;
use Limas\Service\UploadedFileService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


abstract class FileActions
	extends AbstractController
{
	public function __construct(
		protected readonly EntityManagerInterface $entityManager,
		protected readonly UploadedFileService    $uploadedFileService,
		protected readonly MimetypeIconService    $mimetypeIconService,
		protected readonly LoggerInterface        $logger,
		protected readonly array                  $limas
	)
	{
	}

	protected function getEntityClass(Request $request): string
	{
		return $request->attributes->get('_api_resource_class');
	}
}
