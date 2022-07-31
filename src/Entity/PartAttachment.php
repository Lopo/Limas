<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\FileActions;
use Limas\Controller\Actions\ImageActions;
use Limas\Repository\PartAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: PartAttachmentRepository::class)]
#[ApiResource(
	itemOperations: [
		'get',
		'PartAttachmentGet' => [
			'path' => 'part_attachments/{id}/getFile',
			'method' => 'get',
			'controller' => FileActions::class . '::getFileAction'
		],
		'PartAttachmentMimeTypeIcon' => [
			'path' => 'part_attachments/{id}/getMimeTypeIcon',
			'method' => 'get',
			'controller' => FileActions::class . '::getMimeTypeIconAction'
		],
		'PartAttachmentGetImage' => [
			'path' => 'part_attachments/{id}/getImage',
			'method' => 'get',
			'controller' => ImageActions::class . '::getImageAction'
		]
	]
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
