<?php

namespace Limas\Configuration;


class OneToManyConfiguration
	extends Configuration
{
	private const IMPORTBEHAVIOUR_IGNORE = 'ignore';
	private const IMPORTBEHAVIOUR_CREATENEW = 'createNew';
	private const importBehaviours = [
		self::IMPORTBEHAVIOUR_IGNORE,
		self::IMPORTBEHAVIOUR_CREATENEW,
	];

	protected string $associationName;
	protected string $importBehaviour;


	public function parseConfiguration($importConfiguration): bool
	{
		if (!property_exists($importConfiguration, 'importBehaviour')) {
			return false;
		}
		if (!in_array($importConfiguration->importBehaviour, self::importBehaviours, true)) {
			throw new \Exception('The key importBehaviour contains an invalid value!');
		}

		$this->importBehaviour = $importConfiguration->importBehaviour;

		return parent::parseConfiguration($importConfiguration);
	}

	public function import($row, ?object $obj = null): ?object
	{
		switch ($this->importBehaviour) {
			case self::IMPORTBEHAVIOUR_IGNORE:
				return null;
			case self::IMPORTBEHAVIOUR_CREATENEW:
				$this->log(sprintf('Create a new entity of type %s for relation %s', $this->baseEntity, $this->getAssociationName()));
				return parent::import($row);
		}

		return null;
	}

	public function getAssociationName(): mixed
	{
		return $this->associationName;
	}

	public function setAssociationName(mixed $associationName): self
	{
		$this->associationName = $associationName;
		return $this;
	}
}
