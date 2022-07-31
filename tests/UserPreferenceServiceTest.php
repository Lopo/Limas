<?php

namespace Limas\Tests;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Limas\Entity\UserPreference;
use Limas\Exceptions\UserPreferenceNotFoundException;
use Limas\Service\UserPreferenceService;
use Limas\Service\UserService;


class UserPreferenceServiceTest
	extends WebTestCase
{
	public function testBasics(): void
	{
		$service = $this->getContainer()->get(UserPreferenceService::class);
		$userService = $this->getContainer()->get(UserService::class);

		$user = $userService->getUser('admin', $userService->getBuiltinProvider(), true);

		$service->setPreference($user, 'foo', 'bar');
		$this->assertEquals('bar', $service->getPreferenceValue($user, 'foo'));

		$preferences = $service->getPreferences($user);

		$this->assertIsArray($preferences);

		$this->assertArrayHasKey(0, $preferences);
		$this->assertEquals(UserPreference::class, get_class($preferences[0]));

		$this->assertEquals('bar', $preferences[0]->getPreferenceValue());
		$this->assertEquals('foo', $preferences[0]->getPreferenceKey());
		$this->assertEquals($user, $preferences[0]->getUser());

		$preference = $service->getPreference($user, 'foo');

		$this->assertEquals(UserPreference::class, get_class($preference));

		$this->assertEquals('bar', $preference->getPreferenceValue());
		$this->assertEquals('foo', $preference->getPreferenceKey());
		$this->assertEquals($user, $preference->getUser());

		$service->deletePreference($user, 'foo');

		$preferences = $service->getPreferences($user);

		$this->assertCount(0, $preferences);
	}

	public function testGetPreferenceException(): void
	{
		$service = $this->getContainer()->get(UserPreferenceService::class);
		$userService = $this->getContainer()->get(UserService::class);

		$user = $this->getContainer()->get(UserService::class)->getUser(
			'admin',
			$userService->getBuiltinProvider(),
			true
		);

		$this->expectException(UserPreferenceNotFoundException::class);
		$service->getPreference($user, 'BLA');
	}
}
