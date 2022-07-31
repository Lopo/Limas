<?php declare(strict_types=1);

namespace Limas\Annotation;

use Doctrine\ORM\Mapping\Annotation;


/**
 * Tells the system to pass the association by reference and not by value
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ByReference
	implements Annotation
{
}
