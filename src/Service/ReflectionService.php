<?php

namespace Limas\Service;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Limas\Annotation\ByReference;
use Limas\Annotation\IgnoreIds;
use Limas\Annotation\VirtualField;
use Limas\Annotation\VirtualOneToMany;
use Limas\Entity\AbstractCategory;
use Nette\Utils\Json;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;


readonly class ReflectionService
{
	public function __construct(
		private EntityManagerInterface                     $em,
		private IriConverterInterface                      $iriConverter,
		private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory
	)
	{
	}

	public function getEntity(string $entity): array
	{
		$bTree = false;
		$parentClass = 'HydraModel';
		$proxyType = 'Hydra';

		$cm = $this->em->getClassMetadata($entity);
		if ($cm->getReflectionClass()->isSubclassOf(AbstractCategory::class)) {
			$parentClass = 'HydraTreeModel';
			$bTree = true;
			$proxyType = 'HydraTree';
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
			'proxyType' => $proxyType
		];
		try {
//			$renderParams['uri'] = $this->iriConverter->getIriFromResourceClass($entity);
			$op = $this->resourceMetadataCollectionFactory->create($entity)->getOperation(null, true, true);
//			$url = $this->router->generate($this->resourceMetadataCollectionFactory->create($entity)->getOperation(null, true, true)->getName(), [], UrlGeneratorInterface::ABS_PATH);
			$renderParams['uri'] = $this->iriConverter->getIriFromResource($entity, UrlGeneratorInterface::ABS_PATH, $op);
			if (str_contains($renderParams['uri'], '/.well-known/genid/')) {
				$renderParams['uri'] = '';
			}
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

	protected function getExtJSFieldMapping(string $type, ?string $rp): string
	{
		return match ($type) {
			Types::INTEGER => 'int',
			Types::TEXT => 'string',
			Types::DATETIME_MUTABLE, Types::DATETIME_IMMUTABLE => 'date',
			Types::FLOAT => 'number',
			/*Types::ARRAY, */ Types::BOOLEAN, Types::DECIMAL, Types::STRING => $type,
			Types::JSON => $rp ?? 'undefined', // array | object
			default => 'undefined',
		};
	}

	protected function getDatabaseFieldMappings(ClassMetadata $cm): array
	{
		$fieldMappings = [];
		foreach ($cm->getFieldNames() as $field) {
			$currentMapping = $cm->getFieldMapping($field);
			$asserts = $this->getExtJSAssertMappings($cm, $field);

			// Doctrine ORM 3.x exposed FieldMapping via ArrayAccess; ORM 4.x
			// drops it. Read properties directly so we stay forward-compatible.
			$fieldName = $currentMapping->fieldName;
			$fieldType = $currentMapping->type;

			if ($fieldName === 'id') {
				$fieldName = '@id';
				$fieldType = 'string';
			}

			$nullable = $currentMapping->nullable ?? false;

			$name = null;
			if ($currentMapping->type === Types::JSON) {
				// Doctrine's PropertyAccessor knows how to reach inherited
				// private fields on a mapped superclass (e.g.
				// UploadedFile.blob) — PHP's `new ReflectionClass(...)`
				// getProperty() does not walk to a private ancestor.
				$type = $cm->getPropertyAccessor($field)?->getUnderlyingReflector()->getType();
				if ($type instanceof \ReflectionNamedType) {
					$name = $type->getName();
				}
			}
			$fieldMappings[] = [
				'name' => $fieldName,
				'type' => $this->getExtJSFieldMapping($fieldType, $name),
				'nullable' => $nullable,
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
			// Doctrine's PropertyAccessor walks mapped-superclass inheritance
			// correctly; `new ReflectionClass(...)` does not see inherited
			// private props.
			$accessor = $cm->getPropertyAccessor($field);
			if ($accessor === null) {
				return $asserts;
			}
			foreach ($accessor->getUnderlyingReflector()->getAttributes() as $att) {
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
				case ClassMetadata::MANY_TO_MANY:
					$associationType = 'MANY_TO_MANY';
					$getterPlural = true;
					break;
				case ClassMetadata::MANY_TO_ONE:
					$associationType = 'MANY_TO_ONE';
					break;
				case ClassMetadata::ONE_TO_MANY:
					$associationType = 'ONE_TO_MANY';
					$getterPlural = true;
					break;
				case ClassMetadata::ONE_TO_ONE:
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
			// PropertyAccessor handles mapped-superclass private fields
			// correctly (e.g. UploadedFile.blob ManyToOne lives on the
			// abstract parent).
			$accessor = $cm->getPropertyAccessor($association['fieldName']);
			$attributes = $accessor !== null ? $accessor->getUnderlyingReflector()->getAttributes() : [];
			foreach ($attributes as $property) {
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
				// `groups` is the public property; `getGroups()` was
				// deprecated in symfony/serializer 7.4.
				return !in_array('readonly', $atribs[0]->newInstance()->groups, true);
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

	public function getAssetEntities(): array
	{
		$entities = [];
		foreach ($this->em->getMetadataFactory()->getAllMetadata() as $cm) {
			if (0 === count($cm->getReflectionClass()->getAttributes(ApiResource::class))) {
				continue;
			}
			$entities[] = $this->getEntity($cm->getName());
		}
		return $entities;
	}
}
