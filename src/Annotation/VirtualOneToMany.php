<?php declare(strict_types=1);

namespace Limas\Annotation;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\ORM\Mapping\Annotation;


/**
 * Defines a virtual one to many association, that is, an association which is not persisted into the database
 * @Annotation
 * @NamedArgumentConstructor
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class VirtualOneToMany
	implements Annotation
{
	public function __construct(readonly string $target)
	{
	}
}
