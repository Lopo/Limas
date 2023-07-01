<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\TemporaryFileActions;


#[ORM\Entity]
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
		],
		'delete' => [
			'method' => 'delete',
			'path' => 'temp_uploaded_files/{id}',
			'controller' => TemporaryFileActions::class . '::deleteFileAction'
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
