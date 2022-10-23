<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Limas\Controller\Actions\FileActions;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ApiResource(
	itemOperations: [
		'get',
		'FootprintAttachmentMimeTypeIcon' => [
			'method' => 'get',
			'path' => 'footprint_attachments/{id}/getMimeTypeIcon',
			'controller' => FileActions::class . '::getMimeTypeIconAction'
		],
		'FootprintAttachmentGet' => [
			'method' => 'get',
			'path' => 'footprint_attachments/{id}/getFile',
			'controller' => FileActions::class . '::getFileAction'
		]
	],
	denormalizationContext: ['groups' => ['default']],
	normalizationContext: ['groups' => ['default']]
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
