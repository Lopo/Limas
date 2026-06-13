<?php

namespace Limas\EventSubscriber;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;


/**
 * Tracks running messenger:consume workers by writing a heartbeat
 * timestamp into the shared cache pool on every iteration.
 *
 * The web side reads this via {@see \Limas\Service\MessengerWorkerStatusService}
 * to drive UI gating — features that require a live worker (like
 * Bulk Import) can render disabled when no worker has refreshed the
 * heartbeat recently.
 *
 * Heartbeat key scheme: `messenger.worker.heartbeat.<transports>` where
 * transports is the comma-joined sorted list the worker consumes from.
 * TTL is generous (5 min) — staleness threshold lives in the reader.
 */
final readonly class MessengerWorkerHeartbeatSubscriber
	implements EventSubscriberInterface
{
	public const string CACHE_KEY_PREFIX = 'messenger.worker.heartbeat.';
	public const int TTL_SECONDS = 300;


	public function __construct(
		private CacheItemPoolInterface $cache
	)
	{
	}

	public static function getSubscribedEvents(): array
	{
		return [
			WorkerStartedEvent::class => 'onWorkerStarted',
			WorkerRunningEvent::class => 'onWorkerRunning'
			// No WorkerStoppedEvent handler on purpose. Deleting the
			// heartbeat on clean shutdown sounds nice but in practice
			// it races every other writer (the events fire in a tight
			// loop, the last one is Stopped) and leaves the cache empty.
			// Letting heartbeats expire naturally via the freshness
			// threshold gives the same "stopped" state plus a
			// "last seen" timestamp the operator can read.
		];
	}

	public function onWorkerStarted(WorkerStartedEvent $event): void
	{
		$this->writeHeartbeat($event->getWorker()->getMetadata()->getTransportNames());
	}

	public function onWorkerRunning(WorkerRunningEvent $event): void
	{
		$this->writeHeartbeat($event->getWorker()->getMetadata()->getTransportNames());
	}

	/**
	 * @param string[] $transports
	 */
	private function writeHeartbeat(array $transports): void
	{
		$now = (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM);
		foreach ($transports as $transport) {
			$item = $this->cache->getItem(self::cacheKey($transport));
			$item->set($now);
			$item->expiresAfter(self::TTL_SECONDS);
			$this->cache->save($item);
		}
	}

	public static function cacheKey(string $transport): string
	{
		// Reserved cache key chars: {}()/\@: — alpha-numeric + dot only
		return self::CACHE_KEY_PREFIX . preg_replace('~[^a-zA-Z0-9_-]~', '_', $transport);
	}
}
