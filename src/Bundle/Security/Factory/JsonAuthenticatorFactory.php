<?php

namespace Limas\Bundle\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class JsonAuthenticatorFactory
	extends AbstractFactory
{
	public const PRIORITY = -5;


	public function __construct()
	{
//		$this->addOption('csrf_parameter', '_csrf_token');
//		$this->addOption('enable_csrf', false);
		$this->addOption('ldap', []);
		$this->defaultFailureHandlerOptions = [];
		$this->defaultSuccessHandlerOptions = [];
	}

	public function getPriority(): int
	{
		return self::PRIORITY;
	}

	public function getKey(): string
	{
		return 'limas-json';
	}

	public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
	{
		$key = str_replace('-', '_', $this->getKey());
		$authenticatorId = 'security.authenticator.' . $key . '.' . $firewallName;
		$options = array_intersect_key($config, $this->options);

		$container
			->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.' . $key))

			->replaceArgument(1, new Reference($userProviderId))
			->replaceArgument(2, $config['ldap']['service'])

			->replaceArgument(3, isset($config['success_handler']) ? new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)) : null)
			->replaceArgument(4, isset($config['failure_handler']) ? new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)) : null)
			->replaceArgument(5, $options)

			->replaceArgument(7, $config['ldap']['dn_string'])
		;

		return $authenticatorId;
	}

	public function addConfiguration(NodeDefinition $node): void
	{
		parent::addConfiguration($node);

		$node
			->children()
				->arrayNode('ldap')
					->addDefaultsIfNotSet()
					->children()
						->scalarNode('service')->defaultValue('ldap')->end()
						->scalarNode('dn_string')->defaultValue('{username}')->end()
					->end()
			->end()
		;
	}
}
