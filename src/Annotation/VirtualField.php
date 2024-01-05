<?php declare(strict_types=1);

namespace Limas\Annotation;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\ORM\Mapping\MappingAttribute;


/**
 * @Annotation
 * @NamedArgumentConstructor
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class VirtualField
	implements MappingAttribute
{
	public function __construct(public string $type)
	{
	}
}
