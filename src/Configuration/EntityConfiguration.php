<?php

namespace Limas\Configuration;

use Doctrine\ORM\QueryBuilder;


class EntityConfiguration
	extends Configuration
{
	private const IMPORTBEHAVIOUR_ALWAYSIMPORT = 'alwaysImport';
	private const IMPORTBEHAVIOUR_MATCHDATA = 'matchData';
	private const importBehaviours = [
		self::IMPORTBEHAVIOUR_ALWAYSIMPORT,
		self::IMPORTBEHAVIOUR_MATCHDATA,
	];
	private const UPDATEBEHAVIOUR_DONTUPDATE = 'dontUpdate';
	private const UPDATEBEHAVIOUR_UPDATEDATA = 'update';
	private const updateBehaviours = [
		self::UPDATEBEHAVIOUR_DONTUPDATE,
		self::UPDATEBEHAVIOUR_UPDATEDATA
	];

	protected string $importBehaviour;
	protected string $updateBehaviour;
	protected array $matchers = [];


	public function parseConfiguration($importConfiguration): bool
	{
		if (!property_exists($importConfiguration, 'importBehaviour')) {
			throw new \RuntimeException(sprintf('The key importBehaviour does not exist for path /%s!', implode('/', $this->getPath())));
		}
		if (!in_array($importConfiguration->importBehaviour, self::importBehaviours, true)) {
			throw new \RuntimeException('The key importBehaviour contains an invalid value!');
		}

		$this->importBehaviour = $importConfiguration->importBehaviour;

		switch ($this->importBehaviour) {
			case self::IMPORTBEHAVIOUR_MATCHDATA:
				if (!property_exists($importConfiguration, 'matchers')) {
					throw new \RuntimeException('No matchers defined');
				}
				if (!is_array($importConfiguration->matchers)) {
					throw new \RuntimeException('matchers must be an array');
				}

				foreach ($importConfiguration->matchers as $matcher) {
					if (!property_exists($matcher, 'matchField')
						|| !property_exists($matcher, 'importField')
						|| $matcher->importField === ''
					) {
						throw new \RuntimeException('matcher configuration error');
					}
				}

				$this->matchers = $importConfiguration->matchers;

				if (!property_exists($importConfiguration, 'updateBehaviour')) {
					throw new \RuntimeException('The key updateBehaviour does not exist for mode matchData!');
				}
				if (!in_array($importConfiguration->updateBehaviour, self::updateBehaviours, true)) {
					throw new \RuntimeException('Invalid value for updateBehaviour');
				}

				$this->updateBehaviour = $importConfiguration->updateBehaviour;
				break;
			default:
				break;
		}

		return parent::parseConfiguration($importConfiguration);
	}

	public function import($row, ?object $obj = null): ?object
	{
		$descriptions = [];
		switch ($this->importBehaviour) {
			case self::IMPORTBEHAVIOUR_ALWAYSIMPORT:
				$obj = new $this->baseEntity();
				$this->persist($obj);
				parent::import($row, $obj);
				break;
			case self::IMPORTBEHAVIOUR_MATCHDATA:
				$configuration = [];
				foreach ($this->matchers as $matcher) {
					$foo = new \stdClass;
					$foo->property = $matcher->matchField;
					$foo->operator = '=';
					$foo->value = $row[$matcher->importField];

					$descriptions[] = sprintf('%s = %s', $matcher->matchField, $row[$matcher->importField]);
					$configuration[] = $foo;
				}

				$configuration = $this->advancedSearchFilter->extractConfiguration($configuration, []);

				$filters = $configuration['filters'];
				$sorters = $configuration['sorters'];
				$qb = new QueryBuilder($this->em);
				$qb->select('o')->from($this->baseEntity, 'o');

				$this->advancedSearchFilter->filter($qb, $filters, $sorters);

				try {
					$result = $qb->getQuery()->getArrayResult();

					if (count($result) === 0) {
						$this->log(sprintf('No item of type %s for the configured matcher (%s) found, creating a new one',
							$this->baseEntity,
							implode(', ', $descriptions)
						));
						return parent::import($row);
					}

					if (count($result) === 1) {
						$this->log(sprintf('Found item of type %s for the configured matcher (%s)',
							$this->baseEntity,
							implode(', ', $descriptions)
						));
						return parent::import($row, $result[0]);
					}

					$this->log(sprintf("Found %d items of type %s for the configured matcher (%s). Can't continue since we don't know which item to use. Configure the matcher to narrow the results",
						count($result),
						$this->baseEntity,
						implode(', ', $descriptions)
					));
					return null;
				} catch (\Exception $e) {
				}
		}

		return null;
	}
}
