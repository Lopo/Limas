<?php

namespace Limas\Bundle\Security;

use Limas\Entity\User;
use Limas\Service\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;


class HttpBasicAuthenticator
	implements AuthenticatorInterface
{
	public function __construct(
		private readonly UserProviderInterface $userProvider,
		private readonly string                $ldapServiceId,
		private readonly ?LoggerInterface      $logger = null,
		private readonly string                $dnString = '{username}'
	)
	{
	}

	public function supports(Request $request): ?bool
	{
		return $request->headers->has('PHP_AUTH_USER');
	}

	public function authenticate(Request $request): Passport
	{
		$username = $request->headers->get('PHP_AUTH_USER');
		$password = $request->headers->get('PHP_AUTH_PW', '');

		$passport = new Passport(
			new UserBadge($username, $this->userProvider->loadUserByIdentifier(...)),
			new PasswordCredentials($password)
		);
		/** @var User $user */
		$user = $this->userProvider->loadUserByIdentifier($username);
		if ($user->getProvider()->getType() === UserService::LDAP_PROVIDER) {
			$passport->addBadge(new LdapBadge($this->ldapServiceId, $this->dnString));
		}
		if ($this->userProvider instanceof PasswordUpgraderInterface) {
			$passport->addBadge(new PasswordUpgradeBadge($password, $this->userProvider));
		}

		$passport->addBadge(new LimasBadge);
		return $passport;
	}

	public function createToken(Passport $passport, string $firewallName): TokenInterface
	{
		return new UsernamePasswordToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
	}

	public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
	{
		return null;
	}

	public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
	{
		$this->logger?->info('Basic authentication failed for user.', ['username' => $request->headers->get('PHP_AUTH_USER'), 'exception' => $exception]);

		$response = new Response;
		$response->setStatusCode(403);

		return $response;
	}
}
