<?php

namespace Limas\Service;

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Limas\Annotation\ByReference;
use Limas\Annotation\IgnoreIds;
use Limas\Annotation\VirtualField;
use Limas\Annotation\VirtualOneToMany;
use Limas\Entity\AbstractCategory;
use Nette\Utils\Json;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;


class ReflectionService
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly IriConverterInterface  $iriConverter
	)
	{
	}

	public function getEntity(string $entity): array
	{
		$bTree = false;
		$parentClass = 'HydraModel';

		$cm = $this->em->getClassMetadata($entity);
		if ($cm->getReflectionClass()->isSubclassOf(AbstractCategory::class)) {
			$parentClass = 'HydraTreeModel';
			$bTree = true;
		}

		$fieldMappings = array_merge($this->getVirtualFieldMappings($cm), $this->getDatabaseFieldMappings($cm));

		$associationMappings = $this->getDatabaseAssociationMappings($cm, $bTree);
		$associationMappings['ONE_TO_MANY'] = array_merge(
			$associationMappings['ONE_TO_MANY'],
			$this->getVirtualOneToManyRelationMappings($cm)
		);

		$renderParams = [
			'fields' => $fieldMappings,
			'associations' => $associationMappings,
			'className' => $this->convertPHPToExtJSClassName($entity),
			'parentClass' => $parentClass,
		];
		try {
			$renderParams['uri'] = $this->iriConverter->getIriFromResourceClass($entity);
		} catch (\Throwable $e) {
			$renderParams['uri'] = '';
		}
		$renderParams['ignoreIds'] = count($cm->getReflectionClass()->getAttributes(IgnoreIds::class)) > 0;
		return $renderParams;
	}

	public function convertExtJSToPHPClassName(string $className): string
	{
		return str_replace('.', '\\', $className);
	}

	private function convertPHPToExtJSClassName(string $className): string
	{
		return str_replace('\\', '.', $className);
	}

	protected function getVirtualFieldMappings(ClassMetadata $cm): array
	{
		$fieldMappings = [];
		foreach ($cm->getReflectionClass()->getProperties() as $property) {
			if (0 !== count($virts = $property->getAttributes(VirtualField::class))) {
				$virtual = $virts[0]->newInstance();
				$fieldMappings[] = [
					'persist' => true,
					'name' => $property->getName(),
					'type' => $virtual->type
//					'type' => $this->getExtJSFieldMapping($virtualFieldAnnotation->type)
				];
			}
		}
		return $fieldMappings;
	}

	protected function getExtJSFieldMapping(string $type): string
	{
		return match ($type) {
			'integer' => 'int',
			'text' => 'string',
			'datetime' => 'date',
			'float' => 'number',
			'array', 'boolean', 'decimal', 'string' => $type,
			default => 'undefined',
		};
	}

	protected function getDatabaseFieldMappings(ClassMetadata $cm): array
	{
		$fieldMappings = [];
		foreach ($cm->getFieldNames() as $field) {
			$currentMapping = $cm->getFieldMapping($field);
			$asserts = $this->getExtJSAssertMappings($cm, $field);

			if ($currentMapping['fieldName'] === 'id') {
				$currentMapping['fieldName'] = '@id';
				$currentMapping['type'] = 'string';
			}

			if (!array_key_exists('nullable', $currentMapping)) {
				$currentMapping['nullable'] = false;
			}

			$fieldMappings[] = [
				'name' => $currentMapping['fieldName'],
				'type' => $this->getExtJSFieldMapping($currentMapping['type']),
				'nullable' => $currentMapping['nullable'],
				'validators' => Json::encode($asserts),
				'persist' => $this->allowPersist($cm, $field)
			];
		}

		return $fieldMappings;
	}

	public function getExtJSAssertMapping(Constraint $assert): array|false
	{
		return match (get_class($assert)) {
			NotBlank::class => ['type' => 'presence', 'message' => $assert->message],
			default => false,
		};
	}

	public function getExtJSAssertMappings(ClassMetadata $cm, $field): array
	{
		$asserts = [];
		try {
			foreach ((new \ReflectionClass($cm->getName()))->getProperty($field)->getAttributes() as $att) {
				$ai = $att->newInstance();
				if ($ai instanceof Constraint) {
					$assertMapping = $this->getExtJSAssertMapping($ai);
					if ($assertMapping !== false) {
						$asserts[] = $assertMapping;
					}
				}
			}
		} catch (\ReflectionException $e) {
			return $asserts;
		}
		return $asserts;
	}

	protected function getDatabaseAssociationMappings(ClassMetadata $cm, bool $bTree = false): array
	{
		$byReferenceMappings = $this->getByReferenceMappings($cm);

		$associationMappings = [
			'ONE_TO_ONE' => [],
			'MANY_TO_ONE' => [],
			'ONE_TO_MANY' => [],
			'MANY_TO_MANY' => [],
		];

		foreach ($cm->getAssociationMappings() as $association) {
			$getterPlural = false;
			$associationType = $association['type'];

			switch ($association['type']) {
				case ClassMetadataInfo::MANY_TO_MANY:
					$associationType = 'MANY_TO_MANY';
					$getterPlural = true;
					break;
				case ClassMetadataInfo::MANY_TO_ONE:
					$associationType = 'MANY_TO_ONE';
					break;
				case ClassMetadataInfo::ONE_TO_MANY:
					$associationType = 'ONE_TO_MANY';
					$getterPlural = true;
					break;
				case ClassMetadataInfo::ONE_TO_ONE:
					$associationType = 'ONE_TO_ONE';
					break;
			}

			$getter = 'get' . ucfirst($association['fieldName']);
			$getterField = lcfirst($cm->getReflectionClass()->getShortName()) . str_replace(
					'.',
					'',
					$this->convertPHPToExtJSClassName($association['targetEntity'])
				);

			if ($getterPlural) {
				$getterField .= 's';
			}

			$nullable = true;
			foreach ((new \ReflectionClass($cm->getName()))->getProperty($association['fieldName'])->getAttributes() as $property) {
				$pi = $property->newInstance();
				if ($pi instanceof NotNull) {
					$nullable = false;
				}
			}

			// The self-referencing association may not be written for trees, because ExtJS can't load all nodes
			// in one go.
			if (!($bTree && $association['targetEntity'] === $cm->getName())) {
				$associationMappings[$associationType][] = [
					'name' => $association['fieldName'],
					'nullable' => $nullable,
					'target' => $this->convertPHPToExtJSClassName($association['targetEntity']),
					'byReference' => in_array($association['fieldName'], $byReferenceMappings, true),
					'getter' => $getter,
					'getterField' => $getterField,
				];
			}
		}

		return $associationMappings;
	}

	protected function getVirtualOneToManyRelationMappings(ClassMetadata $cm): array
	{
		$virtualRelationMappings = [];
		foreach ($cm->getReflectionClass()->getProperties() as $property) {
			if (0 !== count($virtualOneToManyRelation = $property->getAttributes(VirtualOneToMany::class))) {
				$virtualRelationMappings[] = [
					'name' => $property->getName(),
					'target' => $this->convertPHPToExtJSClassName($virtualOneToManyRelation[0]->newInstance()->target)
				];
			}
		}
		return $virtualRelationMappings;
	}

	public function allowPersist(ClassMetadata $cm, string $field): bool
	{
		$class = $cm->getReflectionClass();
		if ($class->hasProperty($field)) {
			$atribs = $class->getProperty($field)->getAttributes(Groups::class);
			if (0 !== count($atribs)) {
				return !in_array('readonly', $atribs[0]->newInstance()->getGroups(), true);
			}
			return true;
		}
		if ($parent = get_parent_class($class->name)) {
			return $this->allowPersist($this->em->getClassMetadata($parent), $field);
		}
		return true;
	}

	protected function getByReferenceMappings(ClassMetadata $cm): array
	{
		$byReferenceMappings = [];
		foreach ($cm->getReflectionClass()->getProperties() as $property) {
			if (0 !== count($property->getAttributes(ByReference::class))) {
				$byReferenceMappings[] = $property->getName();
			}
		}
		return $byReferenceMappings;
	}
}
