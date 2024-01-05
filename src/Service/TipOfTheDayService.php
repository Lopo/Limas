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
	 * Syncronizes the tip database against the master wiki
	 */
	public function syncTips(): void
	{
		$this->updateTipDatabase($this->extractPageNames((new Client)->request('GET', $this->limas['tip_of_the_day_list'])->getBody()));
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
