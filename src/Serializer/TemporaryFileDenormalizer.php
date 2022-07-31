<?php

namespace Limas\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataTransformer\DataTransformerInitializerInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Exception\InvalidValueException;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Security\ResourceAccessCheckerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Core\Util\ClassInfoTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Limas\Annotation\UploadedFile;
use Limas\Annotation\UploadedFileCollection;
use Limas\Entity\Image;
use Limas\Entity\TempImage;
use Limas\Entity\TempUploadedFile;
use Limas\Service\ImageService;
use Limas\Service\UploadedFileService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;


class TemporaryFileDenormalizer
	extends AbstractItemNormalizer
{
	use ClassInfoTrait;


	public function __construct(
		private readonly ImageService          $imageService,
		private readonly UploadedFileService   $uploadedFileService,

		PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
		PropertyMetadataFactoryInterface       $propertyMetadataFactory,
		IriConverterInterface                  $iriConverter,
		ResourceClassResolverInterface         $resourceClassResolver,
		PropertyAccessorInterface              $propertyAccessor = null,
		NameConverterInterface                 $nameConverter = null,
		ClassMetadataFactoryInterface          $classMetadataFactory = null,
		ItemDataProviderInterface              $itemDataProvider = null,
//		bool                                   $allowPlainIdentifiers = false,
//		array                                  $defaultContext = [],
		private ?LoggerInterface               $logger = null,
//		iterable                               $dataTransformers = [],
		ResourceMetadataFactoryInterface       $resourceMetadataFactory = null,
		ResourceAccessCheckerInterface         $resourceAccessChecker = null
	)
	{
		parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $iriConverter, $resourceClassResolver, $propertyAccessor, $nameConverter, $classMetadataFactory, $itemDataProvider, /*$allowPlainIdentifiers, $defaultContext, $dataTransformers*/ false, [], [], $resourceMetadataFactory, $resourceAccessChecker);
		if ($logger === null) {
			$this->logger = new NullLogger;
		}
	}

	public function supportsNormalization($data, $format = null): bool // mosi ostat inac sa vola pre serialize/normalize a dava nahovno vystupy
	{
		return false;
	}

	private function updateObjectToPopulate(array $data, array &$context): void // asik len pri update
	{
		try {
			$context[self::OBJECT_TO_POPULATE] = $this->iriConverter->getItemFromIri((string)$data['id'], $context + ['fetch_data' => true]);
		} catch (InvalidArgumentException $e) {
			$identifier = null;
			$options = $this->getFactoryOptions($context);

			foreach ($this->propertyNameCollectionFactory->create($context['resource_class'], $options) as $propertyName) {
				if (true === $this->propertyMetadataFactory->create($context['resource_class'], $propertyName)->isIdentifier()) {
					$identifier = $propertyName;
					break;
				}
			}

			if (null === $identifier) {
				throw $e;
			}

			$context[self::OBJECT_TO_POPULATE] = $this->iriConverter->getItemFromIri(sprintf('%s/%s', $this->iriConverter->getIriFromResourceClass($context['resource_class']), $data[$identifier]), $context + ['fetch_data' => true]);
		}
	}

	public function denormalize($data, $class, $format = null, array $context = []): mixed
	{
//		\ApiPlatform\Core\Serializer\ItemNormalizer
		// Avoid issues with proxies if we populated the object
		if (isset($data['id']) && !isset($context[self::OBJECT_TO_POPULATE])) {
			if (isset($context['api_allow_update']) && true !== $context['api_allow_update']) {
				throw new NotNormalizableValueException('Update is not allowed for this operation.');
			}

			if (isset($context['resource_class'])) {
				$this->updateObjectToPopulate($data, $context);
			} else {
				// See https://github.com/api-platform/core/pull/2326 to understand this message.
				$this->logger->warning('The "resource_class" key is missing from the context.', ['context' => $context]);
			}
		}

//		 JsonLD ItemNormalizer
		// Avoid issues with proxies if we populated the object
		if (isset($data['@id']) && !isset($context[self::OBJECT_TO_POPULATE])) {
			if (true !== ($context['api_allow_update'] ?? true)) {
				throw new NotNormalizableValueException('Update is not allowed for this operation.');
			}

			$context[self::OBJECT_TO_POPULATE] = $this->iriConverter->getItemFromIri($data['@id'], $context + ['fetch_data' => true]);
		}
//// AbstractItem data,class,format,context > data, resourceClass, format, context
		if (null === $objectToPopulate = $this->extractObjectToPopulate($class, $context, static::OBJECT_TO_POPULATE)) {
			$normalizedData = is_scalar($data) ? [$data] : $this->prepareForDenormalization($data);
			$class = $this->getClassDiscriminatorResolvedClass($normalizedData, $class);
		}
		$resourceClass = $this->resourceClassResolver->getResourceClass($objectToPopulate, $class);
		$context['api_denormalize'] = true;
		$context['resource_class'] = $resourceClass;

		if (null !== ($inputClass = $this->getInputClass($resourceClass, $context)) && null !== ($dataTransformer = $this->getDataTransformer($data, $resourceClass, $context))) {
			$dataTransformerContext = $context;

//			unset($context['input']);
//			unset($context['resource_class']);

//			if (!$this->serializer instanceof DenormalizerInterface) {
//				throw new LogicException('Cannot denormalize the input because the injected serializer is not a denormalizer');
//			}

//			if ($dataTransformer instanceof DataTransformerInitializerInterface) {
//				$context[AbstractObjectNormalizer::OBJECT_TO_POPULATE] = $dataTransformer->initialize($inputClass, $context);
//				$context[AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE] = true;
//			}

//			try {
//				$denormalizedInput = $this->serializer->denormalize($data, $inputClass, $format, $context);
//			} catch (NotNormalizableValueException $e) {
//				throw new UnexpectedValueException('The input data is misformatted.', $e->getCode(), $e);
//			}

//			if (!\is_object($denormalizedInput)) {
//				throw new UnexpectedValueException('Expected denormalized input to be an object.');
//			}

//			return $dataTransformer->transform($denormalizedInput, $resourceClass, $dataTransformerContext);
		}

		$supportsPlainIdentifiers = $this->supportsPlainIdentifiers();

		if (\is_string($data)) { // pri update ?
			try {
				return $this->iriConverter->getItemFromIri($data, $context + ['fetch_data' => true]);
			} catch (ItemNotFoundException $e) {
				if (!$supportsPlainIdentifiers) {
					throw new UnexpectedValueException($e->getMessage(), $e->getCode(), $e);
				}
			} catch (InvalidArgumentException $e) {
				if (!$supportsPlainIdentifiers) {
					throw new UnexpectedValueException(sprintf('Invalid IRI "%s".', $data), $e->getCode(), $e);
				}
			}
		}

		if (!\is_array($data)) {
			if (!$supportsPlainIdentifiers) {
				throw new UnexpectedValueException(sprintf('Expected IRI or document for resource "%s", "%s" given.', $resourceClass, \gettype($data)));
			}

			$item = $this->itemDataProvider->getItem($resourceClass, $data, null, $context + ['fetch_data' => true]);
			if (null === $item) {
				throw new ItemNotFoundException(sprintf('Item not found for resource "%s" with id "%s".', $resourceClass, $data));
			}

			return $item;
		}
////AbstractObject >data, type, format, context
		$type = $resourceClass;
		if (!isset($context['cache_key'])) {
			$context['cache_key'] = $this->getCacheKey($format, $context);
		}

		$this->validateCallbackContext($context);

		$allowedAttributes = $this->getAllowedAttributes($class, $context, true);
		$normalizedData = $this->prepareForDenormalization($data);
		$extraAttributes = [];

		$reflectionClass = new \ReflectionClass($type);
		$object = $this->instantiateObject($normalizedData, $type, $context, $reflectionClass, $allowedAttributes, $format);
////		$resolvedClass = $this->objectClassResolver ? ($this->objectClassResolver)($object) : \get_class($object);
		$resolvedClass = ($this->getObjectClass(...))($object);

		foreach ($normalizedData as $attribute => $value) {
			if (null !== $this->nameConverter) {
				$attribute = $this->nameConverter->denormalize($attribute, $resolvedClass, $format, $context);
			}

			$attributeContext = $this->getAttributeDenormalizationContext($resolvedClass, $attribute, $context);

			if ((false !== $allowedAttributes && !\in_array($attribute, $allowedAttributes, true)) || !$this->isAllowedAttribute($resolvedClass, $attribute, $format, $context)) {
				if (!($context[self::ALLOW_EXTRA_ATTRIBUTES] ?? $this->defaultContext[self::ALLOW_EXTRA_ATTRIBUTES])) {
					$extraAttributes[] = $attribute;
				}

				continue;
			}

			if ($attributeContext[self::DEEP_OBJECT_TO_POPULATE] ?? $this->defaultContext[self::DEEP_OBJECT_TO_POPULATE] ?? false) {
				try {
					$attributeContext[self::OBJECT_TO_POPULATE] = $this->getAttributeValue($object, $attribute, $format, $attributeContext);
				} catch (NoSuchPropertyException) {
				}
			}

			$types = $this->getTypes($resolvedClass, $attribute);

			if (null !== $types) {
//				try {
//					$value = $this->validateAndDenormalize($types, $resolvedClass, $attribute, $value, $format, $attributeContext);
//				} catch (NotNormalizableValueException $exception) {
//					if (isset($context['not_normalizable_value_exceptions'])) {
//						$context['not_normalizable_value_exceptions'][] = $exception;
//						continue;
//					}
//					throw $exception;
//				}
			}

			$value = $this->applyCallbacks($value, $resolvedClass, $attribute, $format, $attributeContext);

// $object=result ; $reflectionClass [of result] ;
			try {
				if ($this->isTemporaryFile($property = $reflectionClass->getProperty($attribute))) {
					$oneToMany = $property->getAttributes(OneToMany::class);
					if (0 !== count($oneToMany)) {
						$collection = new ArrayCollection;
						foreach ($value as $key => $item) {
							if (is_array($item) && isset($item['@id'])) {
								$item = $this->iriConverter->getItemFromIri($item['@id']);
							} else {
								throw new InvalidValueException;
							}
							if ($item instanceof TempUploadedFile || $item instanceof TempImage) {
								$targetEntity = $oneToMany[0]->newInstance()->targetEntity;
								$collection[$key] = $this->setReplacementFile($targetEntity, $item, $object);
							}
						}
						$this->propertyAccessor->setValue($object, $attribute, $collection);
						continue;
					}
					$oneToOne = $property->getAttributes(OneToOne::class);
					if (0 !== count($oneToOne)) {
						if ($value !== null) {
							if (is_array($value) && isset($value['@id'])) {
								$item = $this->iriConverter->getItemFromIri($value['@id']);
							} else {
								throw new InvalidValueException;
							}
							if ($item instanceof TempUploadedFile || $item instanceof TempImage) {
								$this->propertyAccessor->setValue($object, $attribute, $this->setReplacementFile($oneToOne[0]->newInstance()->targetEntity, $item, $object));
								continue;
							} else {
								$item = $this->propertyAccessor->getValue($data, $property->getName());
								if ($item !== null && $item->getReplacement() !== null) {
									$this->replaceFile($item, $this->iriConverter->getItemFromIri($item->getReplacement()));
								}
							}
						}
					}
				}
//$attribute [current name] ; $value [array/serialized ld temp] ; $format [req content-type] ; $attrContext [array]
				$this->setAttributeValue($object, $attribute, $value, $format, $attributeContext);
			} catch (\Symfony\Component\PropertyAccess\Exception\InvalidArgumentException $e) {
				$exception = NotNormalizableValueException::createForUnexpectedDataType(
					sprintf('Failed to denormalize attribute "%s" value for class "%s": ' . $e->getMessage(), $attribute, $type),
					$data,
					['unknown'],
					$context['deserialization_path'] ?? null,
					false,
					$e->getCode(),
					$e
				);
				if (isset($context['not_normalizable_value_exceptions'])) {
					$context['not_normalizable_value_exceptions'][] = $exception;
					continue;
				}
				throw $exception;
			}
		}

		if (0 !== count($extraAttributes)) {
			throw new ExtraAttributesException($extraAttributes);
		}

		return $object;
	}

	public function supportsDenormalization($data, $type, $format = null): bool
	{
		return /*self::FORMAT === $format
			&&*/ parent::supportsDenormalization($data, $type, $format)
			&& $this->hasTemporaryFileProperty($type);
	}

	private function hasTemporaryFileProperty($type): bool
	{
		$classReflection = new \ReflectionClass($type);
		foreach ($classReflection->getProperties() as $property) {
			if ($this->isTemporaryFile($property)) {
				return true;
			}
		}
		return false;
	}

	private function isTemporaryFile(\ReflectionProperty $property): bool
	{
		if (0 !== count($property->getAttributes(UploadedFileCollection::class))) {
			return true;
		}
		if (0 !== count($property->getAttributes(UploadedFile::class))) {
			return true;
		}
		return false;
	}

	/**
	 * Replaces the TemporaryUploadedFile or TempImage with the actual instance. Automatically sets the
	 * reference to the owning entity.
	 */
	protected function setReplacementFile(string $targetEntity, TempUploadedFile|TempImage $source, object $target): object
	{
		/** @var UploadedFile $newFile */
		$newFile = new $targetEntity;

		$this->replaceFile($newFile, $source);

		$setterName = $this->getReferenceSetter($newFile, $target);

		if ($setterName !== false) {
			$this->propertyAccessor->setValue($newFile, $setterName, $target);
		}

		return $newFile;
	}

	protected function replaceFile(\Limas\Entity\UploadedFile $target, \Limas\Entity\UploadedFile $source): void
	{
		if ($target instanceof Image) {
			$this->imageService->replaceFromUploadedFile($target, $source);
		} else {
			$this->uploadedFileService->replaceFromUploadedFile($target, $source);
		}
		$target->setDescription($source->getDescription());
	}

	/**
	 * Returns the setter name for the inverse side
	 */
	protected function getReferenceSetter($inverseSideEntity, $owningSideEntity): bool|string
	{
		$inverseSideReflection = new \ReflectionClass($inverseSideEntity); // FPI
		$owningSideReflection = new \ReflectionClass($owningSideEntity); // FP

		foreach ($inverseSideReflection->getProperties() as $inverseSideProperty) {
			$assoc = $inverseSideProperty->getAttributes(ManyToOne::class);
			if (0 !== count($assoc) && $assoc[0]->newInstance()->targetEntity === $owningSideReflection->getName()) {
				return $inverseSideProperty->getName();
			}
		}

		return false;
	}

	private function supportsPlainIdentifiers(): bool
	{
		return $this->allowPlainIdentifiers && null !== $this->itemDataProvider;
	}

	private function getCacheKey(?string $format, array $context): bool|string
	{
		foreach ($context[self::EXCLUDE_FROM_CACHE_KEY] ?? $this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] as $key) {
			unset($context[$key]);
		}
		unset($context[self::EXCLUDE_FROM_CACHE_KEY]);
		unset($context[self::OBJECT_TO_POPULATE]);
		unset($context['cache_key']); // avoid artificially different keys

		try {
			return md5($format . serialize([
					'context' => $context,
					'ignored' => $context[self::IGNORED_ATTRIBUTES] ?? $this->defaultContext[self::IGNORED_ATTRIBUTES],
				]));
		} catch (\Exception) {
			// The context cannot be serialized, skip the cache
			return false;
		}
	}

	/**
	 * Computes the denormalization context merged with current one. Metadata always wins over global context, as more specific.
	 */
	private function getAttributeDenormalizationContext(string $class, string $attribute, array $context): array
	{
		$context['deserialization_path'] = ($context['deserialization_path'] ?? false) ? $context['deserialization_path'] . '.' . $attribute : $attribute;

		if (null === $metadata = $this->getAttributeMetadata($class, $attribute)) {
			return $context;
		}

		return array_merge($context, $metadata->getDenormalizationContextForGroups($this->getGroups($context)));
	}

	private function getAttributeMetadata(object|string $objectOrClass, string $attribute): ?AttributeMetadataInterface
	{
		if (null === $this->classMetadataFactory) {
			return null;
		}

		return $this->classMetadataFactory->getMetadataFor($objectOrClass)->getAttributesMetadata()[$attribute] ?? null;
	}

	/**
	 * @return Type[]|null
	 */
	private function getTypes(string $currentClass, string $attribute): ?array
	{
//		if (null === $this->propertyTypeExtractor) {
		return null;
//		}
		/*
				$key = $currentClass.'::'.$attribute;
				if (isset($this->typesCache[$key])) {
					return false === $this->typesCache[$key] ? null : $this->typesCache[$key];
				}

				if (null !== $types = $this->propertyTypeExtractor->getTypes($currentClass, $attribute)) {
					return $this->typesCache[$key] = $types;
				}

				if (null !== $this->classDiscriminatorResolver && null !== $discriminatorMapping = $this->classDiscriminatorResolver->getMappingForClass($currentClass)) {
					if ($discriminatorMapping->getTypeProperty() === $attribute) {
						return $this->typesCache[$key] = [
							new Type(Type::BUILTIN_TYPE_STRING),
						];
					}

					foreach ($discriminatorMapping->getTypesMapping() as $mappedClass) {
						if (null !== $types = $this->propertyTypeExtractor->getTypes($mappedClass, $attribute)) {
							return $this->typesCache[$key] = $types;
						}
					}
				}

				$this->typesCache[$key] = false;

				return null;*/
	}

//	/**
//	 * Denormalizes a relation
//	 *
//	 * @throws LogicException
//	 * @throws UnexpectedValueException
//	 * @throws ItemNotFoundException
//	 */
//	protected function denormalizeRelation(string $attributeName, PropertyMetadata $propertyMetadata, string $className, mixed $value, ?string $format, array $context): object|null
//	{
//		$supportsPlainIdentifiers = $this->supportsPlainIdentifiers();
//
//		if (\is_string($value)) {
//			try {
//				return $this->iriConverter->getItemFromIri($value, $context + ['fetch_data' => true]);
//			} catch (ItemNotFoundException $e) {
//				if (!$supportsPlainIdentifiers) {
//					throw new UnexpectedValueException($e->getMessage(), $e->getCode(), $e);
//				}
//			} catch (InvalidArgumentException $e) {
//				if (!$supportsPlainIdentifiers) {
//					throw new UnexpectedValueException(sprintf('Invalid IRI "%s".', $value), $e->getCode(), $e);
//				}
//			}
//		}
//
//		if ($propertyMetadata->isWritableLink()) {
//			$context['api_allow_update'] = true;
//
//			if (!$this->serializer instanceof DenormalizerInterface) {
//				throw new LogicException(sprintf('The injected serializer must be an instance of "%s".', DenormalizerInterface::class));
//			}
//
//			try {
//				$item = $this->serializer->denormalize($value, $className, $format, $context);
//				if (!\is_object($item) && null !== $item) {
//					throw new \UnexpectedValueException('Expected item to be an object or null.');
//				}
//
//				return $item;
//			} catch (InvalidValueException $e) {
//				if (!$supportsPlainIdentifiers) {
//					throw $e;
//				}
//			}
//		}
//
//		if (!\is_array($value)) {
//			if (!$supportsPlainIdentifiers) {
//				throw new UnexpectedValueException(sprintf('Expected IRI or nested document for attribute "%s", "%s" given.', $attributeName, \gettype($value)));
//			}
//
//			$item = $this->itemDataProvider->getItem($className, $value, null, $context + ['fetch_data' => true]);
//			if (null === $item) {
//				throw new ItemNotFoundException(sprintf('Item not found for resource "%s" with id "%s".', $className, $value));
//			}
//
//			return $item;
//		}
//
//		throw new UnexpectedValueException(sprintf('Nested documents for attribute "%s" are not allowed. Use IRIs instead.', $attributeName));
//	}

	/**
	 * Sets a value of the object using the PropertyAccess component.
	 *
	 * @param object $object
	 * @param mixed $value
	 */
	private function setValue($object, string $attributeName, $value): void
	{
		try {
			$this->propertyAccessor->setValue($object, $attributeName, $value);
		} catch (NoSuchPropertyException $exception) {
			// Properties not found are ignored
		}
	}
}
