<?php

namespace Limas\Configuration;

use Doctrine\ORM\QueryBuilder;


class ManyToOneConfiguration
	extends Configuration
{
	private const IMPORTBEHAVIOUR_DONTSET = 'dontSet';
	private const IMPORTBEHAVIOUR_ALWAYSSETTO = 'alwaysSetTo';
	private const IMPORTBEHAVIOUR_MATCHDATA = 'matchData';
	private const importBehaviours = [
		self::IMPORTBEHAVIOUR_DONTSET,
		self::IMPORTBEHAVIOUR_ALWAYSSETTO,
		self::IMPORTBEHAVIOUR_MATCHDATA
	];
	private const UPDATEBEHAVIOUR_DONTUPDATE = 'dontUpdate';
	private const UPDATEBEHAVIOUR_UPDATEDATA = 'updateData';
	private const updateBehaviours = [
		self::UPDATEBEHAVIOUR_DONTUPDATE,
		self::UPDATEBEHAVIOUR_UPDATEDATA
	];
	private const NOTFOUNDBEHAVIOUR_STOPIMPORT = 'stopImport';
	private const NOTFOUNDBEHAVIOUR_SETTOENTITY = 'setToEntity';
	private const NOTFOUNDBEHAVIOUR_CREATEENTITY = 'createEntity';
	private const notFoundBehaviours = [
		self::NOTFOUNDBEHAVIOUR_CREATEENTITY,
		self::NOTFOUNDBEHAVIOUR_SETTOENTITY,
		self::NOTFOUNDBEHAVIOUR_STOPIMPORT
	];

	protected string $associationName;
	protected string $importBehaviour;
	protected array $matchers = [];
	protected string $notFoundBehaviour;
	protected string $updateBehaviour;
	protected string $notFoundSetToEntity;
	protected string $setToEntity;


	public function parseConfiguration($importConfiguration): bool
	{
		if (!property_exists($importConfiguration, 'importBehaviour')) {
			return false;
			throw new \Exception('The key importBehaviour does not exist!');
		}

		if (!in_array($importConfiguration->importBehaviour, self::importBehaviours, true)) {
			throw new \Exception('The key importBehaviour contains an invalid value!');
		}

		$this->importBehaviour = $importConfiguration->importBehaviour;

		switch ($this->importBehaviour) {
			case self::IMPORTBEHAVIOUR_ALWAYSSETTO:
				if (!property_exists($importConfiguration, 'setToEntity')) {
					throw new \Exception('The key setToEntity does not exist for mode alwaysSetTo!');
				}

				// @todo Check if setToEntity contains a valid value
				$this->setToEntity = $importConfiguration->setToEntity;
				break;
			case self::IMPORTBEHAVIOUR_MATCHDATA:
				if (!property_exists($importConfiguration, 'matchers')) {
					throw new \Exception('No matchers defined');
				}
				if (!is_array($importConfiguration->matchers)) {
					throw new \Exception('matchers must be an array');
				}

				foreach ($importConfiguration->matchers as $matcher) {
					if (!property_exists($matcher, 'matchField')
						|| !property_exists($matcher, 'importField')
						|| $matcher->importField === ''
					) {
						throw new \Exception('matcher configuration error');
					}
				}

				$this->matchers = $importConfiguration->matchers;

				if (!property_exists($importConfiguration, 'updateBehaviour')) {
					throw new \Exception('The key updateBehaviour does not exist for mode copyFrom!');
				}
				if (!in_array($importConfiguration->updateBehaviour, self::updateBehaviours, true)) {
					throw new \Exception(sprintf('Invalid value for updateBehaviour: %s', $importConfiguration->updateBehaviour));
				}

				$this->updateBehaviour = $importConfiguration->updateBehaviour;

				if (!property_exists($importConfiguration, 'notFoundBehaviour')) {
					throw new \Exception('The key notFoundBehaviour does not exist for mode copyFrom!');
				}
				if (!in_array($importConfiguration->notFoundBehaviour, self::notFoundBehaviours, true)) {
					throw new \Exception('Invalid value for notFoundBehaviour');
				}

				$this->notFoundBehaviour = $importConfiguration->notFoundBehaviour;

				if ($this->notFoundBehaviour === self::NOTFOUNDBEHAVIOUR_SETTOENTITY) {
					if (!property_exists($importConfiguration, 'notFoundSetToEntity')) {
						throw new \Exception('The key notFoundSetToEntity does not exist for mode copyFrom!');
					}

					// @todo check if notFoundSetToEntity contains a valid entity
					$this->notFoundSetToEntity = $importConfiguration->notFoundSetToEntity;
				}
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
			case self::IMPORTBEHAVIOUR_ALWAYSSETTO:
				$targetEntity = $this->iriConverter->getItemFromIri($this->setToEntity);
				$this->log(sprintf('Set %s to %s#%s', $this->associationName, $this->baseEntity, $targetEntity->getId()));
				return $targetEntity;
			case self::IMPORTBEHAVIOUR_MATCHDATA:
				$configuration = [];

				foreach ($this->matchers as $matcher) {
					$foo = new \stdClass();
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
					$result = $qb->getQuery()->getSingleResult();

//					if ($this->updateBehaviour === self::UPDATEBEHAVIOUR_UPDATEDATA) {
//						// @todo Update the entity with the specified values
//					}

					$this->log(sprintf('Set %s to %s#%s', $this->associationName, $this->baseEntity, $result->getId()));
					return $result;
				} catch (\Exception $e) {
				}

				switch ($this->notFoundBehaviour) {
					case self::NOTFOUNDBEHAVIOUR_STOPIMPORT:
						$this->log(sprintf('Stop import as the match %s for association %s was not found', implode(',', $descriptions), $this->getAssociationName()));
						break;
					case self::NOTFOUNDBEHAVIOUR_SETTOENTITY:
						$targetEntity = $this->iriConverter->getItemFromIri($this->notFoundSetToEntity);
						$this->log(sprintf('Set the association %s to %s, since the match %s for association %s was not found', $this->getAssociationName(), $this->notFoundSetToEntity, implode(',', $descriptions)));
						return $targetEntity;
					case self::NOTFOUNDBEHAVIOUR_CREATEENTITY:
						$this->log(sprintf('Create a new entity of type %s', $this->baseEntity));
						return parent::import($row);
				}
				break;
		}

		return null;
	}

	public function getAssociationName(): string
	{
		return $this->associationName;
	}

	public function setAssociationName(string $associationName): self
	{
		$this->associationName = $associationName;
		return $this;
	}
}
