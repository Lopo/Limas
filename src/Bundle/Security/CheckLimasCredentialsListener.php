<?php

namespace Limas\Bundle\Security;

use Limas\Entity\User;
use Limas\Service\UserService;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\Exception\InvalidSearchCredentialsException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;


readonly class CheckLimasCredentialsListener
	implements EventSubscriberInterface
{
	public function __construct(
		private PasswordHasherFactoryInterface $hasherFactory,
		private ContainerInterface             $ldapLocator
	)
	{
	}

	public static function getSubscribedEvents(): array
	{
		return [
			CheckPassportEvent::class => ['checkPassport', 256] // Ldap=144, Csrf=512,
		];
	}

	public function checkPassport(CheckPassportEvent $event): void
	{
		$passport = $event->getPassport();

		if (!$passport->hasBadge(LimasBadge::class)) {
			return;
		}
		/** @var LimasBadge $limasBadge */
		$limasBadge = $passport->getBadge(LimasBadge::class);
		if ($limasBadge->isResolved()) {
			throw new \LogicException('Limas authentication verification cannot be completed because something else has already resolved.');
		}

		if (!$passport->hasBadge(PasswordCredentials::class)) {
			throw new \LogicException(sprintf('Limas authentication requires a passport containing password credentials, authenticator "%s" does not fulfill these requirements.', \get_class($event->getAuthenticator())));
		}
		/** @var PasswordCredentials $passwordCredentials */
		$passwordCredentials = $passport->getBadge(PasswordCredentials::class);
		if ($passwordCredentials->isResolved()) {
			throw new \LogicException('Limas authentication password verification cannot be completed because something else has already resolved the PasswordCredentials.');
		}

		$presentedPassword = $passwordCredentials->getPassword();
		if ('' === $presentedPassword) {
			throw new BadCredentialsException('The presented password cannot be empty.');
		}

		/** @var User $user */
		$user = $passport->getUser();

		switch ($user->getProvider()->getType()) {
			case UserService::BUILTIN_PROVIDER:
				$this->checkBuiltin($user, $presentedPassword);
				if (!$passport->hasBadge(PasswordUpgradeBadge::class)) {
					$passport->addBadge(new PasswordUpgradeBadge($presentedPassword));
				}
				break;
			case UserService::LDAP_PROVIDER:
				$this->checkLdap($passport);
				break;
			default:
				throw new \RuntimeException('Not implemented');
		}
		$limasBadge->markResolved();
		$passwordCredentials->markResolved();
	}

	protected function checkBuiltin(User $user, #[\SensitiveParameter] string $password): void
	{
		if (null === $user->getPassword()) {
			throw new BadCredentialsException('The presented password is invalid.');
		}
		if (!$this->hasherFactory->getPasswordHasher($user)->verify($user->getPassword(), $password)) {
			throw new BadCredentialsException('The presented password is invalid.');
		}
	}

	protected function checkLdap(Passport $passport): void
	{
		if (!$passport->hasBadge(LdapBadge::class)) {
			throw new \LogicException('LdapBadge not found');
		}
		/** @var LdapBadge $ldapBadge */
		$ldapBadge = $passport->getBadge(LdapBadge::class);
		if ($ldapBadge->isResolved()) {
			return;
		}
		/** @var PasswordCredentials $passwordCredentials */
		$passwordCredentials = $passport->getBadge(PasswordCredentials::class);
		if (!$this->ldapLocator->has($ldapBadge->getLdapServiceId())) {
			throw new \LogicException(sprintf('Cannot check credentials using the "%s" ldap service, as such service is not found. Did you maybe forget to add the "ldap" service tag to this service?', $ldapBadge->getLdapServiceId()));
		}
		$presentedPassword = $passwordCredentials->getPassword();
		if ('' === $presentedPassword) {
			throw new BadCredentialsException('The presented password cannot be empty.');
		}
		$user = $passport->getUser();
		$ldap = $this->ldapLocator->get($ldapBadge->getLdapServiceId());
		try {
			if ('' !== ($ldapBadge->getQueryString() ?? '')) {
				if ('' !== $ldapBadge->getSearchDn() && '' !== $ldapBadge->getSearchPassword()) {
					try {
						$ldap->bind($ldapBadge->getSearchDn(), $ldapBadge->getSearchPassword());
					} catch (InvalidCredentialsException) {
						throw new InvalidSearchCredentialsException();
					}
				} else {
					throw new LogicException('Using the "query_string" config without using a "search_dn" and a "search_password" is not supported.');
				}
				$username = $ldap->escape($user->getUserIdentifier(), '', LdapInterface::ESCAPE_FILTER);
				$query = str_replace('{username}', $username, $ldapBadge->getQueryString());
				$result = $ldap->query($ldapBadge->getDnString(), $query)->execute();
				if (1 !== $result->count()) {
					throw new BadCredentialsException('The presented username is invalid.');
				}

				$dn = $result[0]->getDn();
			} else {
				$username = $ldap->escape($user->getUserIdentifier(), '', LdapInterface::ESCAPE_DN);
				$dn = str_replace('{username}', $username, $ldapBadge->getDnString());
			}

			$ldap->bind($dn, $presentedPassword);
		} catch (InvalidCredentialsException $e) {
			throw new BadCredentialsException('The presented password is invalid.');
		}
		$ldapBadge->markResolved();
	}
}
