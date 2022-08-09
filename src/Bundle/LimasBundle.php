<?php

namespace Limas\Bundle;

use Limas\Bundle\Security\Factory\HttpBasicAuthenticatorFactory;
use Limas\Bundle\Security\Factory\JsonAuthenticatorFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;


class LimasBundle
	extends Bundle
{
	public function build(ContainerBuilder $container): void
	{
		parent::build($container);
		/** @var SecurityExtension $extension */
		$extension = $container->getExtension('security');
		$extension->addAuthenticatorFactory(new JsonAuthenticatorFactory);
		$extension->addAuthenticatorFactory(new HttpBasicAuthenticatorFactory);
	}
}
