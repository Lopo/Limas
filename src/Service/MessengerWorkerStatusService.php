<?php

namespace Limas\Service;

use Limas\EventSubscriber\MessengerWorkerHeartbeatSubscriber;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;


/**
 * Reads messenger worker heartbeat state + queue counts.
 *
 * Heartbeat is written by {@see MessengerWorkerHeartbeatSubscriber}; we
 * just decode it back into structured state for the API + System
 * Information panel. The "alive" threshold (30 s) is well under the
 * subscriber's 300 s cache TTL so stale heartbeats from a crashed
 * worker eventually flip the UI even if WorkerStoppedEvent never fired.
 */
final readonly class MessengerWorkerStatusService
{
	public const int ALIVE_THRESHOLD_SECONDS = 30;


	public function __construct(
		private CacheItemPoolInterface $cache,
		// `messenger.receiver_locator` exposes all configured transports
		// keyed by their config name (async, failed, sync). Same shape
		// the messenger:stats command pulls from.
		#[Autowire(service: 'messenger.receiver_locator')]
		private ContainerInterface     $transportLocator
	)
	{
	}

	/**
	 * @return array<string, array{alive: bool, lastHeartbeatAt: ?string, secondsSinceLastSeen: ?int, queued: ?int}>
	 */
	public function getStatusAll(): array
	{
		$out = [];
		foreach ($this->getKnownTransportNames() as $name) {
			$out[$name] = $this->getStatus($name);
		}
		return $out;
	}

	/**
	 * @return array{alive: bool, lastHeartbeatAt: ?string, secondsSinceLastSeen: ?int, queued: ?int}
	 */
	public function getStatus(string $transport): array
	{
		$item = $this->cache->getItem(MessengerWorkerHeartbeatSubscriber::cacheKey($transport));
		$lastHeartbeatAt = null;
		$secondsSinceLastSeen = null;
		$alive = false;
		if ($item->isHit()) {
			$raw = $item->get();
			if (is_string($raw) && ($dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $raw)) !== false) {
				$lastHeartbeatAt = $raw;
				$secondsSinceLastSeen = max(0, time() - $dt->getTimestamp());
				$alive = $secondsSinceLastSeen <= self::ALIVE_THRESHOLD_SECONDS;
			}
		}
		return [
			'alive' => $alive,
			'lastHeartbeatAt' => $lastHeartbeatAt,
			'secondsSinceLastSeen' => $secondsSinceLastSeen,
			'queued' => $this->queueCount($transport)
		];
	}

	/**
	 * @return string[]
	 */
	private function getKnownTransportNames(): array
	{
		if (!$this->transportLocator instanceof ServiceProviderInterface) {
			return [];
		}
		// `sync` is in-process — no real worker behind it, exclude
		return array_values(array_filter(
			array_keys($this->transportLocator->getProvidedServices()),
			static fn(string $name) => $name !== 'sync'
		));
	}

	private function queueCount(string $transport): ?int
	{
		if (!$this->transportLocator->has($transport)) {
			return null;
		}
		$receiver = $this->transportLocator->get($transport);
		if (!$receiver instanceof TransportInterface || !method_exists($receiver, 'getMessageCount')) {
			return null;
		}
		try {
			return (int)$receiver->getMessageCount();
		} catch (\Throwable) {
			return null;
		}
	}
}
