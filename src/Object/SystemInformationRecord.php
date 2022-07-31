<?php

namespace Limas\Object;


/**
 * This class represents a system information record
 *
 * This is basically a category, a name and a value. No logic included within the class.
 *
 * For example, records could hold:
 *
 * Name                    Value                                        Category
 * =====================================================================================
 * Doctrine ORM            2.1.0                                        Libraries
 * Doctrine DBAL           2.1.0                                        Libraries
 * Doctrine Migrations     git-f87afe9223dbfecaaddb                     Libraries
 *
 * PHP Version             5.3.2                                        Server Software
 * Operating System        Linux (Funtoo Linux - baselayout 2.1.8)      Server Software
 */
class SystemInformationRecord
{
	public function __construct(
		public string $name,
		public mixed  $value,
		public string $category
	)
	{
	}
}
