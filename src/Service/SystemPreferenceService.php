<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\SystemPreference;
use Limas\Exceptions\SystemPreferenceNotFoundException;


readonly class SystemPreferenceService
{
	public function __construct(private EntityManagerInterface $entityManager)
	{
	}

	public function setSystemPreference(string $key, string $value): SystemPreference
	{
		$systemPreference = $this->entityManager->getRepository(SystemPreference::class)->findOneBy(['preferenceKey' => $key]);
		if ($systemPreference === null) {
			$systemPreference = (new SystemPreference)
				->setPreferenceKey($key);
			$this->entityManager->persist($systemPreference);
		}
		$systemPreference->setPreferenceValue($value);
		$this->entityManager->flush();
		return $systemPreference;
	}

	public function getPreference(string $key): SystemPreference
	{
		$sp = $this->entityManager->getRepository(SystemPreference::class)->findOneBy(['preferenceKey' => $key]);
		if ($sp === null) {
			throw new SystemPreferenceNotFoundException(/*$key*/);
		}
		return $sp;
	}

	public function getSystemPreferenceValue(string $key): string
	{
		return $this->getPreference($key)->getPreferenceValue();
	}

	/**
	 * @return SystemPreference[]
	 */
	public function getPreferences(): array
	{
		return $this->entityManager->getRepository(SystemPreference::class)->findAll();
	}

	public function deletePreference(string $key): void
	{
		if (null !== ($pref = $this->entityManager->getRepository(SystemPreference::class)->findOneBy(['preferenceKey' => $key]))) {
			$this->entityManager->remove($pref);
			$this->entityManager->flush();
		}
	}
}
