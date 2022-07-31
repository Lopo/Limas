<?php

namespace Limas\Configuration;

use Symfony\Component\PropertyAccess\PropertyAccess;


class Configuration
	extends BaseConfiguration
{
	private array $fields = [];
	private array $manyToOneAssociations = [];
	private array $oneToManyAssociations = [];


	public function parseConfiguration($importConfiguration): bool
	{
		if (property_exists($importConfiguration, 'fields')) {
			foreach ($importConfiguration->fields as $field => $configuration) {
				if ($this->classMetadata->hasField($field)) {
					$fieldConfiguration = (new FieldConfiguration(
						$this->classMetadata,
						$this->baseEntity,
						$this->reflectionService,
						$this->em,
						$this->advancedSearchFilter,
						$this->iriConverter
					))
						->setFieldName($field)
						->setPath($this->getPath($field));
					if ($fieldConfiguration->parseConfiguration($configuration) !== false) {
						$this->fields[] = $fieldConfiguration;
					}
				} else {
					//throw new \Exception('Field $field not found in '.$this->baseEntity);
				}
			}
		}

		if (property_exists($importConfiguration, 'manytoone')) {
			foreach ($importConfiguration->manytoone as $manyToOne => $configuration) {
				if ($this->classMetadata->hasAssociation($manyToOne)) {
					$targetClass = $this->classMetadata->getAssociationTargetClass($manyToOne);
					$manyToOneconfiguration = (new ManyToOneConfiguration(
						$this->em->getClassMetadata($targetClass),
						$targetClass,
						$this->reflectionService,
						$this->em,
						$this->advancedSearchFilter,
						$this->iriConverter
					))
						->setAssociationName($manyToOne)
						->setPath($this->getPath($manyToOne));
					if ($manyToOneconfiguration->parseConfiguration($configuration) !== false) {
						$this->manyToOneAssociations[] = $manyToOneconfiguration;
					}
				} else {
					//throw new \Exception('Association $manyToOne not found in '.$this->baseEntity);
				}
			}
		}

		if (property_exists($importConfiguration, 'onetomany')) {
			foreach ($importConfiguration->onetomany as $oneToMany => $configuration) {
				if ($this->classMetadata->hasAssociation($oneToMany)) {
					$targetClass = $this->classMetadata->getAssociationTargetClass($oneToMany);
					$oneToManyConfiguration = (new OneToManyConfiguration(
						$this->em->getClassMetadata($targetClass),
						$targetClass,
						$this->reflectionService,
						$this->em,
						$this->advancedSearchFilter,
						$this->iriConverter
					))
						->setAssociationName($oneToMany)
						->setPath($this->getPath($oneToMany));
					if ($oneToManyConfiguration->parseConfiguration($configuration) !== false) {
						$this->oneToManyAssociations[] = $oneToManyConfiguration;
					}
				} else {
					//throw new \Exception('Association $oneToMany not found in '.$this->baseEntity);
				}
			}
		}

		return true;
	}

	public function import(array $row, ?object $obj = null): ?object
	{
		if ($obj === null) {
			$obj = new $this->baseEntity();
			$this->persist($obj);
		}

		$accessor = PropertyAccess::createPropertyAccessor();

		foreach ($this->fields as $field) {
			$name = $field->getFieldName();
			$data = $field->import($row);

			if ($data !== null) {
				$accessor->setValue($obj, $name, $data);
			}
		}

		foreach ($this->manyToOneAssociations as $manyToOneAssociation) {
			$name = $manyToOneAssociation->getAssociationName();
			$data = $manyToOneAssociation->import($row);
			if ($data !== null) {
				$accessor->setValue($obj, $name, $data);
			}
		}

		foreach ($this->oneToManyAssociations as $oneToManyAssociation) {
			$name = $oneToManyAssociation->getAssociationName();
			$data = $oneToManyAssociation->import($row);
			if ($data !== null) {
				$accessor->setValue($obj, $name, [$data]);
			}
		}

		return $obj;
	}
}
