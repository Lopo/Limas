<?php declare(strict_types=1);

namespace Limas\Annotation;

use Doctrine\ORM\Mapping\Annotation;


/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class UploadedFileCollection
	implements Annotation
{
}
