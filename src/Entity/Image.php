<?php

namespace Limas\Entity;

use Doctrine\ORM\Mapping\MappedSuperclass;
use Limas\Exceptions\InvalidImageTypeException;


#[MappedSuperclass]
abstract class Image
	extends UploadedFile
{
	protected const IMAGE_ICLOGO = 'iclogo';
	protected const IMAGE_TEMP = 'temp';
	protected const IMAGE_PART = 'part';
	protected const IMAGE_STORAGELOCATION = 'storagelocation';
	protected const IMAGE_FOOTPRINT = 'footprint';


	public function __construct(string $type)
	{
		$this->setType($type);
		parent::__construct();
	}

	protected function setType(string $type): self
	{
		switch ($type) {
			case self::IMAGE_ICLOGO:
			case self::IMAGE_TEMP:
			case self::IMAGE_PART:
			case self::IMAGE_FOOTPRINT:
			case self::IMAGE_STORAGELOCATION:
				parent::setType($type);
				break;
			default:
				throw new InvalidImageTypeException($type);
		}
		return $this;
	}
}
