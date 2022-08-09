<?php

namespace Limas\Bundle\Security;

use Limas\Entity\User;
use Limas\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
//use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\Translation\TranslatorInterface;


class JsonAuthenticator
	implements InteractiveAuthenticatorInterface
{
	private array $options;
	private ?TranslatorInterface $translator = null;


	public function __construct(
		private readonly HttpUtils                              $httpUtils,
		private readonly UserProviderInterface                  $userProvider,
		private readonly string                                 $ldapServiceId,

		private readonly ?AuthenticationSuccessHandlerInterface $successHandler = null,
		private readonly ?AuthenticationFailureHandlerInterface $failureHandler = null,
		array                                                   $options = [],
		private ?PropertyAccessorInterface                      $propertyAccessor = null,

		private readonly string                                 $dnString = '{username}'
	)
	{
		$this->options = array_merge([
			'username_path' => 'username',
			'password_path' => 'password',
//			'enable_csrf' => false,
//			'csrf_parameter' => '_csrf_token',
//			'csrf_token_id' => 'authenticate'
		], $options);
		$this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
	}

	public function supports(Request $request): ?bool
	{
		if (!\str_contains($request->getRequestFormat() ?? '', 'json') && !\str_contains($request->getContentType() ?? '', 'json')) {
			return false;
		}
		if (isset($this->options['check_path']) && !$this->httpUtils->checkRequestPath($request, $this->options['check_path'])) {
			return false;
		}
		return true;
	}

	public function authenticate(Request $request): Passport
	{
		try {
			$credentials = $this->getCredentials($request);
		} catch (BadRequestHttpException $e) {
			$request->setRequestFormat('json');
			throw $e;
		}

		$passport = new Passport(
			new UserBadge($credentials['username'], $this->userProvider->loadUserByIdentifier(...)),
			new PasswordCredentials($credentials['password'])
		);
		/** @var User $user */
		$user = $this->userProvider->loadUserByIdentifier($credentials['username']);
		if ($user->getProvider()->getType() === UserService::LDAP_PROVIDER) {
			$passport->addBadge(new LdapBadge($this->ldapServiceId, $this->dnString));
		}
//		if ($this->options['enable_csrf']) {
//			$passport->addBadge(new CsrfTokenBadge($this->options['csrf_token_id'], $credentials['csrf_token']));
//		}
		if ($this->userProvider instanceof PasswordUpgraderInterface) {
			$passport->addBadge(new PasswordUpgradeBadge($credentials['password'], $this->userProvider));
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
		return $this->successHandler?->onAuthenticationSuccess($request, $token);
	}

	public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
	{
		if (null === $this->failureHandler) {
			if (null !== $this->translator) {
				$errorMessage = $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security');
			} else {
				$errorMessage = \strtr($exception->getMessageKey(), $exception->getMessageData());
			}

			return new JsonResponse(['error' => $errorMessage], Response::HTTP_UNAUTHORIZED);
		}

		return $this->failureHandler->onAuthenticationFailure($request, $exception);
	}

	public function isInteractive(): bool
	{
		return true;
	}

	public function setTranslator(TranslatorInterface $translator)
	{
		$this->translator = $translator;
	}

	private function getCredentials(Request $request)
	{
		$data = json_decode($request->getContent());
		if (!$data instanceof \stdClass) {
			throw new BadRequestHttpException('Invalid JSON.');
		}

		$credentials = [];
		try {
			$credentials['username'] = $this->propertyAccessor->getValue($data, $this->options['username_path']);

			if (!\is_string($credentials['username'])) {
				throw new BadRequestHttpException(sprintf('The key "%s" must be a string.', $this->options['username_path']));
			}
			if (\strlen($credentials['username']) > Security::MAX_USERNAME_LENGTH) {
				throw new BadCredentialsException('Invalid username.');
			}
		} catch (AccessException $e) {
			throw new BadRequestHttpException(sprintf('The key "%s" must be provided.', $this->options['username_path']), $e);
		}

		try {
			$credentials['password'] = $this->propertyAccessor->getValue($data, $this->options['password_path']);

			if (!\is_string($credentials['password'])) {
				throw new BadRequestHttpException(sprintf('The key "%s" must be a string.', $this->options['password_path']));
			}
		} catch (AccessException $e) {
			throw new BadRequestHttpException(sprintf('The key "%s" must be provided.', $this->options['password_path']), $e);
		}

		return $credentials;
	}
}
