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
		self::assertEquals('bar', $service->getPreferenceValue($user, 'foo'));

		$preferences = $service->getPreferences($user);

		self::assertIsArray($preferences);

		self::assertArrayHasKey(0, $preferences);
		self::assertEquals(UserPreference::class, get_class($preferences[0]));

		self::assertEquals('bar', $preferences[0]->getPreferenceValue());
		self::assertEquals('foo', $preferences[0]->getPreferenceKey());
		self::assertEquals($user, $preferences[0]->getUser());

		$preference = $service->getPreference($user, 'foo');

		self::assertEquals(UserPreference::class, get_class($preference));

		self::assertEquals('bar', $preference->getPreferenceValue());
		self::assertEquals('foo', $preference->getPreferenceKey());
		self::assertEquals($user, $preference->getUser());

		$service->deletePreference($user, 'foo');

		$preferences = $service->getPreferences($user);

		self::assertCount(0, $preferences);
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
