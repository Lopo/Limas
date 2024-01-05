<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\TemporaryFileActions;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new Post(
			uriTemplate: 'temp_uploaded_files/upload',
			controller: TemporaryFileActions::class . '::uploadAction',
			deserialize: false,
			name: 'TemporaryFileUpload'
		),
		new Get,
		new Get(
			uriTemplate: 'temp_uploaded_files/{id}/getFile',
			controller: TemporaryFileActions::class . '::getFileAction',
			name: 'TemporaryFileGet'
		),
		new Get(
			uriTemplate: 'temp_uploaded_files/{id}/getMimeTypeIcon',
			controller: TemporaryFileActions::class . '::getMimeTypeIconAction',
			name: 'TemporaryFileGetMimeTypeIcon'
		),
		new Post(
			uriTemplate: 'temp_uploaded_files/webcamUpload',
			controller: TemporaryFileActions::class . '::webcamUploadAction',
			deserialize: false,
			name: 'TemporaryFileUploadWebcam'
		),
		new Delete(
			controller: TemporaryFileActions::class . '::deleteFileAction',
		)
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
