<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use Limas\Controller\Actions\FileGetFile;
use Limas\Controller\Actions\FileGetMimeTypeIcon;
use Limas\Controller\Actions\ImageGetImage;
use Limas\Repository\ProjectAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ProjectAttachmentRepository::class)]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),

		new Get(),
		new Get(uriTemplate: '/project_attachments/{id}/getFile', controller: FileGetFile::class),
		new Get(uriTemplate: '/project_attachments/{id}/getMimeTypeIcon', controller: FileGetMimeTypeIcon::class),
		new Get(uriTemplate: '/project_attachments/{id}/getImage', controller: ImageGetImage::class)
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
