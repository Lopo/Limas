<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Limas\Controller\Actions\FileActions;
use Limas\Controller\Actions\ImageActions;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ApiResource(
	collectionOperations: [
		'get', 'post'
	],
	itemOperations: [
		'get',
		'ProjectAttachmentGet' => [
			'method' => 'get',
			'path' => 'project_attachments/{id}/getFile',
			'controller' => FileActions::class . '::getFileAction'
		],
		'ProjectAttachmentMimeTypeIcon' => [
			'method' => 'get',
			'path' => 'project_attachments/{id}/getMimeTypeIcon',
			'controller' => FileActions::class . '::getMimeTypeIconAction'
		],
		'ProjectAttachmentGetImage' => [
			'method' => 'get',
			'path' => 'project_attachments/{id}/getImage',
			'controller' => ImageActions::class . '::getImageAction'
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
)]
class ProjectAttachment
	extends UploadedFile
{
	#[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'attachments')]
	private ?Project $project = null;


	public function __construct()
	{
		parent::__construct();
		$this->setType('ProjectAttachment');
	}

	public function getProject(): ?Project
	{
		return $this->project;
	}

	public function setProject(?Project $project = null): self
	{
		$this->project = $project;
		return $this;
	}
}
