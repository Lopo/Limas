<?php declare(strict_types=1);

namespace Limas\Annotation;

use Doctrine\ORM\Mapping\MappingAttribute;


/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class IgnoreIds
	implements MappingAttribute
{
}
