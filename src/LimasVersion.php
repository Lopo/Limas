<?php

namespace Limas;


class LimasVersion
{
	/**
	 * Holds the Limas Version
	 *
	 * If {V_GIT}, then the function will return 'GIT Development Version'.
	 * {V_GIT} will be replaced by the build script with the actual version.
	 *
	 * The reason why we have a separate class for the version constant is that we can easily replace it from scripts.
	 */
	public const LIMAS_VERSION = '{V_GIT}';
}
