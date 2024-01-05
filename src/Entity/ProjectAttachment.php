<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\FileActions;
use Limas\Controller\Actions\ImageActions;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Get,
		new Get(
			uriTemplate: 'project_attachments/{id}/getFile',
			controller: FileActions::class . '::getFileAction',
			name: 'ProjectAttachmentGet'
		),
		new Get(
			uriTemplate: 'project_attachments/{id}/getMimeTypeIcon',
			controller: FileActions::class . '::getMimeTypeIconAction',
			name: 'ProjectAttachmentMimeTypeIcon'
		),
		new Get(
			uriTemplate: 'project_attachments/{id}/getImage',
			controller: ImageActions::class . '::getImageAction',
			name: 'ProjectAttachmentGetImage'
		)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
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
