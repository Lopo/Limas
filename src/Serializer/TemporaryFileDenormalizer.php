<?php

namespace Limas\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\Exception\InvalidValueException;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Security\ResourceAccessCheckerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use Limas\Annotation\UploadedFile;
use Limas\Annotation\UploadedFileCollection;
use Limas\Entity\Image;
use Limas\Entity\TempImage;
use Limas\Entity\TempUploadedFile;
use Limas\Service\ImageService;
use Limas\Service\UploadedFileService;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;


class TemporaryFileDenormalizer
	extends AbstractItemNormalizer
{
//	private const FORMAT = 'jsonld';


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
		bool                                   $allowPlainIdentifiers = false,
		array                                  $defaultContext = [],
		iterable                               $dataTransformers = [],
		ResourceMetadataFactoryInterface       $resourceMetadataFactory = null,
		ResourceAccessCheckerInterface         $resourceAccessChecker = null
	)
	{
		parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $iriConverter, $resourceClassResolver, $propertyAccessor, $nameConverter, $classMetadataFactory, $itemDataProvider, $allowPlainIdentifiers, $defaultContext, $dataTransformers, $resourceMetadataFactory, $resourceAccessChecker);
	}

	public function supportsNormalization(mixed $data, $format = null, array $context = []): bool
	{
		return false;
	}

	public function supportsDenormalization(mixed $data, $type, $format = null, array $context = []): bool
	{
		return /*self::FORMAT === $format
			&&*/ parent::supportsDenormalization($data, $type, $format)
			&& $this->hasTemporaryFileProperty($type);
	}

	/**
	 * Denormalizes a relation
	 *
	 * @throws LogicException
	 * @throws UnexpectedValueException
	 * @throws ItemNotFoundException
	 * @throws \ReflectionException
	 */
	protected function denormalizeRelation(string $attributeName, PropertyMetadata $propertyMetadata, string $className, $value, ?string $format, array $context): ?object
	{
		if ($value !== null) {
			if ((new \ReflectionClass($className))->isSubclassOf(\Limas\Entity\UploadedFile::class)) {
				if (!is_array($value) || !isset($value['@id'])) {
					throw new InvalidValueException;
				}
				$item = $this->iriConverter->getItemFromIri($value['@id']);
				if ($item instanceof TempUploadedFile || $item instanceof TempImage) {
					$newFile = new $className;
					$this->replaceFile($newFile, $item);
					return $newFile;
				}
				return $item;
			}
			if (is_array($value) && isset($value['@id'])) {
				return $this->iriConverter->getItemFromIri($value['@id']);
			}
		}
		return parent::denormalizeRelation($attributeName, $propertyMetadata, $className, $value, $format, $context);
	}

	private function hasTemporaryFileProperty(string $type): bool
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
		if (0 !== count($property->getAttributes(UploadedFileCollection::class))
			|| 0 !== count($property->getAttributes(UploadedFile::class))
		) {
			return true;
		}
		return false;
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
}
