<?php

namespace Limas\Entity;

use ApiPlatform\Action\NotFoundAction;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Limas\Controller\Actions\FileGetFile;
use Limas\Controller\Actions\FileGetMimeTypeIcon;
use Limas\Controller\Actions\TempUploadedFileUpload;
use Limas\Controller\Actions\TempUploadedFileWebcamUpload;
use Limas\Repository\TempUploadedFileRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: TempUploadedFileRepository::class)]
#[ApiResource(
	operations: [
		new GetCollection(controller: NotFoundAction::class, output: false, read: false),
		new Post(uriTemplate: '/temp_uploaded_files/upload', controller: TempUploadedFileUpload::class, deserialize: false),
		new Post(uriTemplate: '/temp_uploaded_files/webcamUpload', controller: TempUploadedFileWebcamUpload::class, deserialize: false),

		new Get(),
		new Get(uriTemplate: '/temp_uploaded_files/{id}/getFile', controller: FileGetFile::class),
		new Get(uriTemplate: '/temp_uploaded_files/{id}/getMimeTypeIcon', controller: FileGetMimeTypeIcon::class)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class TempUploadedFile
	extends UploadedFile
{
	public function __construct()
	{
		parent::__construct();
		$this->setType('tempfile');
	}
}
