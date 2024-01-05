<?php

namespace Limas\Bundle\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class HttpBasicAuthenticatorFactory
	extends AbstractFactory
{
	public const PRIORITY = -5;


	public function getPriority(): int
	{
		return self::PRIORITY;
	}

	public function getKey(): string
	{
		return 'limas-httpbasic';
	}

	public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
	{
		$key = str_replace('-', '_', $this->getKey());
		$authenticatorId = 'security.authenticator.' . $key . '.' . $firewallName;

		$container
			->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.' . $key))

			->replaceArgument(0, new Reference($userProviderId))
			->replaceArgument(1, $config['ldap']['service'])
			->replaceArgument(3, $config['ldap']['dn_string'])
		;

		return $authenticatorId;
	}

	public function addConfiguration(NodeDefinition $node): void
	{
		$node
			->children()
				->scalarNode('provider')->end()
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
