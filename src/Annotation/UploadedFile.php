<?php declare(strict_types=1);

namespace Limas\Annotation;


use Doctrine\ORM\Mapping\Annotation;


/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class UploadedFile
	implements Annotation
{
}
