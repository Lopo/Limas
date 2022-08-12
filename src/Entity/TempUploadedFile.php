<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Limas\Controller\Actions\TemporaryFileActions;
use Limas\Repository\TempUploadedFileRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: TempUploadedFileRepository::class)]
#[ApiResource(
	collectionOperations: [
		'upload' => [
			'method' => 'post',
			'path' => 'temp_uploaded_files/upload',
			'controller' => TemporaryFileActions::class . '::uploadAction',
			'deserialize' => false
		],
		'webcamUpload' => [
			'method' => 'post',
			'path' => 'temp_uploaded_files/webcamUpload',
			'controller' => TemporaryFileActions::class . '::webcamUploadAction',
			'deserialize' => false
		]
	],
	itemOperations: [
		'get',
		'TemporaryFileGetMimeTypeIcon' => [
			'method' => 'get',
			'path' => 'temp_uploaded_files/{id}/getMimeTypeIcon',
			'controller' => TemporaryFileActions::class . '::getMimeTypeIconAction'
		],
		'get_file' => [
			'method' => 'get',
			'path' => 'temp_uploaded_files/{id}/getFile',
			'controller' => TemporaryFileActions::class . '::getFileAction'
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
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
