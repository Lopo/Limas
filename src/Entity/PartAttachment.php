<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use Limas\Controller\Actions\FileGetFile;
use Limas\Controller\Actions\FileGetMimeTypeIcon;
use Limas\Controller\Actions\ImageGetImage;
use Limas\Repository\PartAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: PartAttachmentRepository::class)]
#[ApiResource(
	operations: [
		new GetCollection(),
		new Post(),

		new Get(),
		new Get(uriTemplate: '/part_attachments/{id}/getFile', controller: FileGetFile::class),
		new Get(uriTemplate: '/part_attachments/{id}/getMimeTypeIcon', controller: FileGetMimeTypeIcon::class),
		new Get(uriTemplate: '/part_attachments/{id}/getImage', controller: ImageGetImage::class)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']])]
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
