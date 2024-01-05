<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\FileActions;
use Limas\Controller\Actions\ImageActions;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection,
		new Post,
		new Get,
		new Get(
			uriTemplate: 'part_attachments/{id}/getFile',
			controller: FileActions::class . '::getFileAction',
			name: 'PartAttachmentGet'
		),
		new Get(
			uriTemplate: 'part_attachments/{id}/getMimeTypeIcon',
			controller: FileActions::class . '::getMimeTypeIconAction',
			name: 'PartAttachmentMimeTypeIcon'
		),
		new Get(
			uriTemplate: 'part_attachments/{id}/getImage',
			controller: ImageActions::class . '::getImageAction',
			name: 'PartAttachmentGetImage'
		)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class PartAttachment
	extends UploadedFile
{
	#[ORM\Column(type: Types::BOOLEAN, nullable: true)]
	#[Groups(['default'])]
	private ?bool $isImage = null;
	#[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'attachments')]
	private ?Part $part;


	public function __construct()
	{
		parent::__construct();
		$this->setType('PartAttachment');
	}

	public function setPart(?Part $part): self
	{
		$this->part = $part;
		return $this;
	}

	public function getPart(): ?Part
	{
		return $this->part;
	}

	public function isImage(): ?bool
	{
		return $this->isImage;
	}

	public function setIsImage(?bool $isImage): self
	{
		$this->isImage = $isImage;
		return $this;
	}

	public function getIsImage(): ?bool
	{
		return $this->isImage;
	}
}
