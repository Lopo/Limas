<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\User;
use Limas\Exceptions\UserProtectedException;
use Limas\Service\UserPreferenceService;
use Limas\Service\UserService;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class UserTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;
	protected UserPasswordHasherInterface $hasher;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = $this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
		$this->hasher = $this->getContainer()->get(UserPasswordHasherInterface::class);
	}

	public function testCreateUser(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request(
			'POST',
			'/api/users',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'username' => 'foobartest',
				'newPassword' => '1234'
			])
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertEquals(201, $client->getResponse()->getStatusCode());
		$this->assertEquals('foobartest', $response->{'username'});
//		$this->assertEmpty($response->{'password'});
		$this->assertObjectNotHasAttribute('newPassword', $response);
	}

	public function testChangeUserPassword(): void
	{
		$user = new User('bernd');
		$user->setPassword($this->hasher->hashPassword($user, 'admin'))
			->setProvider($this->getContainer()->get(UserService::class)->getBuiltinProvider());

		$this->getContainer()->get('doctrine.orm.default_entity_manager')->persist($user);
		$this->getContainer()->get('doctrine.orm.default_entity_manager')->flush($user);

		$client = static::makeAuthenticatedClient();

		$iri = $this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($user);

		$client->request('GET', $iri);

		$response = Json::decode($client->getResponse()->getContent());

		unset($response->password);
		$response->newPassword = 'foobar';

		$client->request('PUT', $iri, [], [], [], Json::encode($response));

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertEquals(200, $client->getResponse()->getStatusCode());
//		$this->assertEmpty($response->{'password'});
		$this->assertObjectNotHasAttribute('newPassword', $response);
	}

	public function testSelfChangeUserPassword(): void
	{
		$user = new User('bernd2');
		$user->setPassword($this->hasher->hashPassword($user, 'admin'))
			->setProvider($this->getContainer()->get(UserService::class)->getBuiltinProvider());

		$this->getContainer()->get('doctrine.orm.default_entity_manager')->persist($user);
		$this->getContainer()->get('doctrine.orm.default_entity_manager')->flush($user);

		$client = static::makeClientWithCredentials('bernd2', 'admin');

		$iri = $this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($user) . '/changePassword';

		$parameters = [
			'oldpassword' => 'admin',
			'newpassword' => 'foobar',
		];

		$client->request('PATCH', $iri, [], [], ['CONTENT_TYPE' => 'application/merge-patch+json'], Json::encode($parameters));

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertEquals(200, $client->getResponse()->getStatusCode());
		$this->assertObjectNotHasAttribute('password', $response);
//		$this->assertEmpty($response->{'newPassword'});

		$client = static::makeClientWithCredentials('bernd2', 'foobar');

		$client->request('PATCH', $iri, [], [], ['CONTENT_TYPE' => 'application/merge-patch+json'], Json::encode($parameters));

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertEquals(500, $client->getResponse()->getStatusCode());
		$this->assertObjectHasAttribute('@type', $response);
		$this->assertEquals('hydra:Error', $response->{'@type'});
	}

	public function testUserProtect(): void
	{
		$userService = $this->getContainer()->get(UserService::class);

		$user = $userService->getUser('fuuser', $userService->getBuiltinProvider(), true);

		$userService->protect($user);

		$this->assertTrue($user->isProtected());

		$client = static::makeAuthenticatedClient();

		$iri = $this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($user);

		$client->request(
			'PUT',
			$iri,
			[],
			[],
			[],
			Json::encode([
				'username' => 'foo',
			])
		);

		$response = Json::decode($client->getResponse()->getContent());

		$exception = new UserProtectedException;
		$this->assertEquals(500, $client->getResponse()->getStatusCode());
		$this->assertObjectHasAttribute('hydra:description', $response);
		$this->assertEquals($exception->getMessageKey(), $response->{'hydra:description'});

		$client->request('DELETE', $iri);

		$response = Json::decode($client->getResponse()->getContent());
		$this->assertEquals(500, $client->getResponse()->getStatusCode());
		$this->assertObjectHasAttribute('hydra:description', $response);
		$this->assertEquals($exception->getMessageKey(), $response->{'hydra:description'});
	}

	public function testUserUnprotect(): void
	{
		$userService = $this->getContainer()->get(UserService::class);

		$user = $userService->getUser($this->fixtures->getReference('user.admin')->getUsername(), $userService->getBuiltinProvider(), true);

		$userService->unprotect($user);

		$this->assertFalse($user->isProtected());
	}

	/**
	 * Tests the proper user deletion if user preferences exist
	 *
	 * Unit test for Bug #569
	 *
	 * @see https://github.com/partkeepr/PartKeepr/issues/569
	 */
	public function testUserWithPreferencesDeletion(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request(
			'POST',
			'/api/users',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'username' => 'preferenceuser',
				'newPassword' => '1234',
			])
		);

		$userService = $this->getContainer()->get(UserService::class);

		$user = $userService->getUser('preferenceuser', $userService->getBuiltinProvider());

		$this->getContainer()->get(UserPreferenceService::class)->setPreference($user, 'foo', 'bar');

		$client->request('DELETE', $this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($user));

		$this->assertEquals(204, $client->getResponse()->getStatusCode());
		$this->assertEmpty($client->getResponse()->getContent());
	}
}
