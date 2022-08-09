<?php

namespace Limas\Bundle\DependencyInjection;

use Limas\Bundle\Security\HttpBasicAuthenticator;
use Limas\Bundle\Security\JsonAuthenticator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\AbstractExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;


class LimasExtension
	extends AbstractExtension
{
	public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
	{
		$container->services()
			->set('security.authenticator.limas_json', JsonAuthenticator::class)
			->abstract()
			->args([
				service('security.http_utils'),
				abstract_arg('user provider'),
				abstract_arg('LDAP service ID'),

				abstract_arg('authentication success handler'),
				abstract_arg('authentication failure handler'),
				abstract_arg('options'),
				service('property_accessor')->nullOnInvalid(),

				abstract_arg('DN string')
			])
			->call('setTranslator', [service('translator')->ignoreOnInvalid()])

			->set('security.authenticator.limas_httpbasic', HttpBasicAuthenticator::class)
			->abstract()
			->args([
				abstract_arg('user provider'),
				abstract_arg('LDAP service ID'),
				service('logger')->nullOnInvalid(),
				abstract_arg('DN string')
			])
			->tag('monolog.logger', ['channel' => 'security'])
		;
	}
}
