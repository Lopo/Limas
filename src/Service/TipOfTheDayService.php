<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Limas\Entity\TipOfTheDay;


readonly class TipOfTheDayService
{
	public function __construct(
		private array                  $limas,
		private EntityManagerInterface $entityManager
	)
	{
	}

	/**
	 * Syncronizes the tip database against the master wiki.
	 *
	 * Currently still pointing at partkeepr.org which is gone; the call
	 * is silently swallowed so the cron tick doesn't error out. Plan is
	 * to self-host the tip list on a limas subdomain later.
	 */
	public function syncTips(): void
	{
		$url = $this->limas['tip_of_the_day_list'] ?? null;
		if (!is_string($url) || $url === '') {
			return;
		}
		try {
			$body = (new Client)->request('GET', $url, [
				'connect_timeout' => 3,
				'timeout' => 5,
			])->getBody();
			$this->updateTipDatabase($this->extractPageNames((string)$body));
		} catch (\Throwable) {
			// Source offline / unreachable — leave the existing tip table
			// untouched rather than wiping it on every failed sync.
		}
	}

	/**
	 * Updates the tip database. Expects an array of page names.
	 *
	 * This method clears all page names and re-creates them. This saves alot of engineering, because we don't need
	 * to match contents within the database against contents in an array.
	 *
	 * @param array $aPageNames The page names as array. Page names are stored as string.
	 */
	private function updateTipDatabase(array $aPageNames): void
	{
		$this->entityManager->createQueryBuilder()->delete(TipOfTheDay::class)->getQuery()->execute();

		foreach ($aPageNames as $pageName) {
			$this->entityManager->persist((new TipOfTheDay)
				->setName($pageName)
			);
		}

		$this->entityManager->flush();
	}

	/**
	 * Extracts the page names from the mediawiki JSON returned
	 *
	 * @param string $response The encoded json string
	 *
	 * @return array An array with the titles of each page
	 */
	private function extractPageNames(string $response): array
	{
		$aPageNames = [];
		foreach (json_decode($response, true, 512, JSON_THROW_ON_ERROR)['query']['categorymembers'] as $tip) {
			$aPageNames[] = $tip['title'];
		}
		return $aPageNames;
	}
}
