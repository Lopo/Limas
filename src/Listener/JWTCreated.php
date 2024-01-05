<?php

namespace Limas\Listener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;


readonly class JWTCreated
	implements EventSubscriberInterface
{
	public function __construct(private RequestStack $requestStack)
	{
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'lexik_jwt_authentication.on_jwt_created' => 'onJWTCreated'
		];
	}

	public function onJWTCreated(JWTCreatedEvent $event): void
	{
		$request = $this->requestStack->getCurrentRequest();

		$payload = $event->getData();
		$payload['ip'] = $request->getClientIp();
		$payload['id'] = $event->getUser()->getId();
		$event->setData($payload);

		$header = $event->getHeader();
		$header['cty'] = 'JWT';
		$event->setHeader($header);
	}
}
