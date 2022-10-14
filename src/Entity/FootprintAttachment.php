<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Limas\Controller\Actions\FileGetFile;
use Limas\Controller\Actions\FileGetMimeTypeIcon;
use Limas\Repository\FootprintAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: FootprintAttachmentRepository::class)]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),

		new Get(),
		new Get(uriTemplate: '/footprint_attachments/{id}/getMimeTypeIcon', controller: FileGetMimeTypeIcon::class),
		new Get(uriTemplate: '/footprint_attachments/{id}/getFile', controller: FileGetFile::class)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class FootprintAttachment
	extends UploadedFile
{
	#[ORM\ManyToOne(targetEntity: Footprint::class, inversedBy: 'attachments')]
	private ?Footprint $footprint = null;


	public function __construct()
	{
		parent::__construct();
		$this->setType('FootprintAttachment');
	}

	public function getFootprint(): ?Footprint
	{
		return $this->footprint;
	}

	public function setFootprint(?Footprint $footprint = null): self
	{
		$this->footprint = $footprint;
		return $this;
	}
}
