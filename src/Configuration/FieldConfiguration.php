<?php

namespace Limas\Configuration;


class FieldConfiguration
	extends BaseConfiguration
{
	private const FIELDCONFIGURATION_IGNORE = 'ignore';
	private const FIELDCONFIGURATION_COPYFROM = 'copyFrom';
	private const FIELDCONFIGURATION_FIXEDVALUE = 'fixedValue';
	private const fieldConfigurationModes = [
		self::FIELDCONFIGURATION_COPYFROM,
		self::FIELDCONFIGURATION_FIXEDVALUE,
		self::FIELDCONFIGURATION_IGNORE,
	];

	private string $fieldConfiguration;
	private string $fieldName;
	private string $fixedValue;
	private string $copyFromField;


	public function getFieldName(): string
	{
		return $this->fieldName;
	}

	public function setFieldName(string $fieldName): self
	{
		$this->fieldName = $fieldName;
		return $this;
	}

	public function parseConfiguration($configuration): bool
	{
		if (!property_exists($configuration, 'fieldConfiguration')) {
			return false;
			throw new \Exception('The key fieldConfiguration does not exist!');
		}

		if (!in_array($configuration->fieldConfiguration, self::fieldConfigurationModes, true)) {
			throw new \Exception('The key fieldConfiguration contains an invalid value!');
		}

		$this->fieldConfiguration = $configuration->fieldConfiguration;

		switch ($this->fieldConfiguration) {
			case self::FIELDCONFIGURATION_FIXEDVALUE:
				if (!property_exists($configuration, 'setToValue')) {
					throw new \Exception('The key setToValue does not exist for mode fixedValue!');
				}
				$this->fixedValue = $configuration->setToValue;
				break;
			case self::FIELDCONFIGURATION_COPYFROM:
				if (!property_exists($configuration, 'copyFromField')) {
					throw new \Exception('The key copyFromField does not exist for mode copyFrom!');
				}
				$this->copyFromField = $configuration->copyFromField;
				break;
			default:
				break;
		}

		return true;
	}

	/**
	 * Imports a given row
	 *
	 * @param $row array The row to import
	 */
	public function import(array $row): ?string
	{
		switch ($this->fieldConfiguration) {
			case self::FIELDCONFIGURATION_FIXEDVALUE:
				$this->log(sprintf('Set field %s to fixed value %s', $this->fieldName, $this->fixedValue));
				return $this->fixedValue;
			case self::FIELDCONFIGURATION_COPYFROM:
				if (!array_key_exists($this->copyFromField, $row)) {
					$this->log(sprintf('Error: Column %s for %s does not exist', $this->copyFromField, $this->fieldName));
					return null;
				}
				$this->log(sprintf('Set field %s to value %s (import column %s)', $this->fieldName, $row[$this->copyFromField], $this->copyFromField));
				return $row[$this->copyFromField];
			default:
				return null;
		}
	}
}
