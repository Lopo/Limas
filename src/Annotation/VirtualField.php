<?php declare(strict_types=1);

namespace Limas\Annotation;

use Doctrine\ORM\Mapping\Annotation;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;


/**
 * @Annotation
 * @NamedArgumentConstructor
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class VirtualField
	implements Annotation
{
	public function __construct(readonly string $type)
	{
	}
}
