<?php

namespace Limas\Listener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;


class JWTDecoded
	implements EventSubscriberInterface
{
	public function __construct(private readonly RequestStack $requestStack)
	{
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'lexik_jwt_authentication.on_jwt_decoded' => 'onJWTDecoded'
		];
	}

	public function onJWTDecoded(JWTDecodedEvent $event): void
	{
		$payload = $event->getPayload();
		if (!isset($payload['ip']) || $payload['ip'] !== $this->requestStack->getCurrentRequest()->getClientIp()) {
			$event->markAsInvalid();
		}

		$event->setPayload($payload);
	}
}
