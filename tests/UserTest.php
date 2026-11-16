<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\RefreshToken;
use Limas\Entity\User;
use Limas\Exceptions\UserProtectedException;
use Limas\Listener\JWTDecoded;
use Limas\Service\UserPreferenceService;
use Limas\Service\UserService;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class UserTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;
	protected UserPasswordHasherInterface $hasher;


	protected function setUp(): void
	{
		parent::setUp();
		$container = self::getContainer();
		$this->fixtures = $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
		$this->hasher = $container->get(UserPasswordHasherInterface::class);
	}

	public function testCreateUser(): void
	{
		$client = $this->makeAuthenticatedClient();

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

		self::assertEquals(201, $client->getResponse()->getStatusCode());
		self::assertEquals('foobartest', $response->{'username'});
//		self::assertEmpty($response->{'password'});
		self::assertObjectNotHasProperty('newPassword', $response);
	}

	public function testChangeUserPassword(): void
	{
		$container = self::getContainer();

		$user = new User('bernd');
		$user->setPassword($this->hasher->hashPassword($user, 'admin'))
			->setProvider($container->get(UserService::class)->getBuiltinProvider());

		$container->get(EntityManagerInterface::class)->persist($user);
		$container->get(EntityManagerInterface::class)->flush();

		$client = $this->makeAuthenticatedClient();

		$iri = '/api/users/' . $user->getId();

		$client->request('GET', $iri);

		$response = Json::decode($client->getResponse()->getContent());

		unset($response->password);
		$response->newPassword = 'foobar';
		// Relations are written by IRI (as the frontend's writer does)
		$response->provider = $response->provider->{'@id'};

		$client->request(
			'PUT',
			$iri,
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode($response)
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertEquals(200, $client->getResponse()->getStatusCode());
//		self::assertEmpty($response->{'password'});
		self::assertObjectNotHasProperty('newPassword', $response);
	}

	public function testSelfChangeUserPassword(): void
	{
		$container = self::getContainer();

		$user = new User('bernd2');
		$user->setPassword($this->hasher->hashPassword($user, 'admin'))
			->setProvider($container->get(UserService::class)->getBuiltinProvider());

		$container->get(EntityManagerInterface::class)->persist($user);
		$container->get(EntityManagerInterface::class)->flush();

		$client = $this->makeClientWithCredentials('bernd2', 'admin');

		$iri = '/api/users/' . $user->getId() . '/changePassword';

		$parameters = Json::encode([
			'oldpassword' => 'admin',
			'newpassword' => 'foobar',
		]);

		$client->request(
			'PATCH',
			$iri,
			[],
			[],
			['CONTENT_TYPE' => 'application/merge-patch+json'],
			$parameters
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertEquals(200, $client->getResponse()->getStatusCode());
		self::assertObjectNotHasProperty('password', $response);
//		self::assertEmpty($response->{'newPassword'});

		$client = $this->makeClientWithCredentials('bernd2', 'foobar');

		$client->request(
			'PATCH',
			$iri,
			[],
			[],
			['CONTENT_TYPE' => 'application/merge-patch+json'],
			$parameters
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertEquals(500, $client->getResponse()->getStatusCode());
		self::assertObjectHasProperty('@type', $response);
		self::assertEquals('hydra:Error', $response->{'@type'});
	}

	/**
	 * A non-admin user must not be able to manage other users (account
	 * takeover via password reset), list the user directory, or rewrite
	 * global system preferences
	 */
	public function testNonAdminCannotManageUsersOrSystemPrefs(): void
	{
		$container = self::getContainer();

		$lowpriv = new User('lowpriv');
		$lowpriv->setPassword($this->hasher->hashPassword($lowpriv, 'lowpass'))
			->setProvider($container->get(UserService::class)->getBuiltinProvider());
		$container->get(EntityManagerInterface::class)->persist($lowpriv);
		$container->get(EntityManagerInterface::class)->flush();

		$adminId = $this->fixtures->getReference('user.admin', User::class)->getId();

		$client = $this->makeClientWithCredentials('lowpriv', 'lowpass');

		// Account-takeover attempt: reset the admin's password → 403
		$client->request(
			'PUT',
			'/api/users/' . $adminId,
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode(['newPassword' => 'pwned'])
		);
		self::assertEquals(403, $client->getResponse()->getStatusCode());

		// Listing the user directory → 403
		$client->request('GET', '/api/users');
		self::assertEquals(403, $client->getResponse()->getStatusCode());

		// Rewriting global system config → 403
		$client->request(
			'POST',
			'/api/system_preferences',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode(['preferenceKey' => 'limas.evil', 'preferenceValue' => 'x'])
		);
		self::assertEquals(403, $client->getResponse()->getStatusCode());
	}

	public function testUserProtect(): void
	{
		$userService = self::getContainer()->get(UserService::class);

		$user = $userService->getUser('fuuser', $userService->getBuiltinProvider(), true);

		$userService->protect($user);

		self::assertTrue($user->isProtected());

		$client = $this->makeAuthenticatedClient();

		$iri = '/api/users/' . $user->getId();

		$client->request(
			'PUT',
			$iri,
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'username' => 'foo',
			])
		);

		$response = Json::decode($client->getResponse()->getContent());

		$exception = new UserProtectedException;
		self::assertEquals(500, $client->getResponse()->getStatusCode());
		self::assertObjectHasProperty('hydra:description', $response);
		self::assertEquals($exception->getMessageKey(), $response->{'hydra:description'});

		$client->request('DELETE', $iri);

		$response = Json::decode($client->getResponse()->getContent());
		self::assertEquals(500, $client->getResponse()->getStatusCode());
		self::assertObjectHasProperty('hydra:description', $response);
		self::assertEquals($exception->getMessageKey(), $response->{'hydra:description'});
	}

	public function testUserUnprotect(): void
	{
		$userService = self::getContainer()->get(UserService::class);

		$user = $userService->getUser($this->fixtures->getReference('user.admin', User::class)->getUsername(), $userService->getBuiltinProvider(), true);

		$userService->unprotect($user);

		self::assertFalse($user->isProtected());
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
		$client = $this->makeAuthenticatedClient();

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

		$container = self::getContainer();
		$userService = $container->get(UserService::class);

		$user = $userService->getUser('preferenceuser', $userService->getBuiltinProvider());

		$container->get(UserPreferenceService::class)->setPreference($user, 'foo', 'bar');

		$client->request('DELETE', '/api/users/' . $user->getId());

		self::assertEquals(204, $client->getResponse()->getStatusCode());
		self::assertEmpty($client->getResponse()->getContent());
	}

	/**
	 * Logging out must delete the caller's stored refresh tokens so a copied
	 * token can't mint fresh access tokens afterwards
	 */
	public function testLogoutRevokesRefreshTokens(): void
	{
		$container = self::getContainer();
		$em = $container->get(EntityManagerInterface::class);
		$admin = $this->fixtures->getReference('user.admin', User::class);
		$username = $admin->getUserIdentifier();

		$em->persist(RefreshToken::createForUserWithTtl('logout-test-token', $admin, 3600));
		$em->flush();
		self::assertCount(1, $em->getRepository(RefreshToken::class)->findBy(['username' => $username]));

		$client = $this->makeAuthenticatedClient();
		$client->request('POST', '/api/users/logout');
		self::assertEquals(200, $client->getResponse()->getStatusCode());

		$freshEm = self::getContainer()->get(EntityManagerInterface::class);
		self::assertCount(0, $freshEm->getRepository(RefreshToken::class)->findBy(['username' => $username]));
	}

	/**
	 * Changing the password revokes that user's refresh tokens (the access JWTs die via the passwordChangedAt claim)
	 */
	public function testChangePasswordRevokesRefreshTokens(): void
	{
		$container = self::getContainer();
		$em = $container->get(EntityManagerInterface::class);

		$user = new User('revoker');
		$user->setPassword($this->hasher->hashPassword($user, 'admin'))
			->setProvider($container->get(UserService::class)->getBuiltinProvider());
		$em->persist($user);
		$em->persist(RefreshToken::createForUserWithTtl('revoker-token', $user, 3600));
		$em->flush();
		self::assertCount(1, $em->getRepository(RefreshToken::class)->findBy(['username' => 'revoker']));

		$client = $this->makeClientWithCredentials('revoker', 'admin');
		$client->request(
			'PATCH',
			'/api/users/' . $user->getId() . '/changePassword',
			[],
			[],
			['CONTENT_TYPE' => 'application/merge-patch+json'],
			Json::encode(['oldpassword' => 'admin', 'newpassword' => 'foobar'])
		);
		self::assertEquals(200, $client->getResponse()->getStatusCode());

		$freshEm = self::getContainer()->get(EntityManagerInterface::class);
		self::assertCount(0, $freshEm->getRepository(RefreshToken::class)->findBy(['username' => 'revoker']));
	}

	/**
	 * JWTDecoded rejects an access token whose passwordChangedAt claim predates
	 * the user's current stamp (instant revocation on password change), while a
	 * current token stays valid. Also exercises the setPassword() stamp bump.
	 */
	public function testJwtDecodedInvalidatesTokenAfterPasswordChange(): void
	{
		$container = self::getContainer();
		$em = $container->get(EntityManagerInterface::class);
		$admin = $this->fixtures->getReference('user.admin', User::class);

		// setPassword() stamps passwordChangedAt
		$admin->setPassword($this->hasher->hashPassword($admin, 'admin'));
		$em->flush();
		$stamp = $admin->getPasswordChangedAt();
		self::assertNotNull($stamp);

		$requestStack = $container->get(RequestStack::class);
		$request = Request::create('/');
		$request->server->set('REMOTE_ADDR', '1.2.3.4');
		$requestStack->push($request);

		$listener = $container->get(JWTDecoded::class);

		$stale = new JWTDecodedEvent(['id' => $admin->getId(), 'ip' => '1.2.3.4', 'pca' => $stamp - 100]);
		$listener->onJWTDecoded($stale);
		self::assertFalse($stale->isValid(), 'token issued before the password change must be rejected');

		$current = new JWTDecodedEvent(['id' => $admin->getId(), 'ip' => '1.2.3.4', 'pca' => $stamp]);
		$listener->onJWTDecoded($current);
		self::assertTrue($current->isValid(), 'token issued at/after the password change stays valid');
	}
}
