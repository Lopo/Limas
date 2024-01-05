<?php declare(strict_types=1);

namespace Limas\Annotation;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\ORM\Mapping\MappingAttribute;


/**
 * Defines a virtual one to many association, that is, an association which is not persisted into the database
 * @Annotation
 * @NamedArgumentConstructor
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class VirtualOneToMany
	implements MappingAttribute
{
	public function __construct(public string $target)
	{
	}
}
