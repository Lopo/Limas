<?php

namespace Limas\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;


abstract class WebTestCase
	extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{
	/**
	 * @param array $options An array of options to pass to the createKernel method
	 * @param array $server An array of server parameters
	 */
	protected function makeAuthenticatedClient(array $options = [], array $server = []): KernelBrowser
	{
		$ctr = self::getContainer();
		$username = $ctr->getParameter('limas.username');
		$password = $ctr->getParameter('limas.password');
		self::ensureKernelShutdown();
		return self::createClient(
			array_merge($options, ['environment' => 'test']),
			array_merge(
				$server,
				[
					'PHP_AUTH_USER' => $username,
					'PHP_AUTH_PW' => $password
				]
			)
		);
	}

	protected function makeClientWithCredentials(string $username, string $password, array $options = [], array $server = []): KernelBrowser
	{
		self::ensureKernelShutdown();
		return self::createClient(
			array_merge($options, ['environment' => 'test']),
			array_merge(
				$server,
				[
					'PHP_AUTH_USER' => $username,
					'PHP_AUTH_PW' => $password
				]
			)
		);
	}
}
