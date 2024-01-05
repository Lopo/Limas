<?php

namespace Limas\Serializer;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\InvalidValueException;
use ApiPlatform\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use ApiPlatform\Symfony\Security\ResourceAccessCheckerInterface;
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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;


class TemporaryFileDenormalizer
	extends AbstractItemNormalizer
{
//	private const FORMAT = 'jsonld';


	public function __construct(
		private readonly ImageService              $imageService,
		private readonly UploadedFileService       $uploadedFileService,

		PropertyNameCollectionFactoryInterface     $propertyNameCollectionFactory,
		PropertyMetadataFactoryInterface           $propertyMetadataFactory,
		IriConverterInterface                      $iriConverter,
		ResourceClassResolverInterface             $resourceClassResolver,
		PropertyAccessorInterface                  $propertyAccessor = null,
		NameConverterInterface                     $nameConverter = null,
		ClassMetadataFactoryInterface              $classMetadataFactory = null,
		array                                      $defaultContext = [],
		ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null,
		?ResourceAccessCheckerInterface            $resourceAccessChecker = null
	)
	{
		parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $iriConverter, $resourceClassResolver, $propertyAccessor, $nameConverter, $classMetadataFactory, $defaultContext, $resourceMetadataCollectionFactory, $resourceAccessChecker);
	}

	public function supportsNormalization(mixed $data, $format = null, array $context = []): bool
	{
		return false;
	}

//	public function supportsDenormalization(mixed $data, $type, $format = null, array $context = []): bool
//	{
//		return /*self::FORMAT === $format
//			&&*/ parent::supportsDenormalization($data, $type, $format)
//			&& $this->hasTemporaryFileProperty($type);
//	}

	/**
	 * Denormalizes a relation
	 *
	 * @throws LogicException
	 * @throws UnexpectedValueException
	 * @throws ItemNotFoundException
	 * @throws \ReflectionException
	 */
	protected function denormalizeRelation(string $attributeName, ApiProperty $propertyMetadata, string $className, $value, ?string $format, array $context): ?object
	{
		if ($value !== null) {
			if ((new \ReflectionClass($className))->isSubclassOf(\Limas\Entity\UploadedFile::class)) {
				if (!is_array($value) || !isset($value['@id'])) {
					throw new InvalidValueException;
				}
				$item = $this->iriConverter->getResourceFromIri($value['@id']);
				if ($item instanceof TempUploadedFile || $item instanceof TempImage) {
					$newFile = new $className;
					$this->replaceFile($newFile, $item);
					return $newFile;
				}
				return $item;
			}
			if (is_array($value) && isset($value['@id'])) {
				$context[AbstractNormalizer::OBJECT_TO_POPULATE] = $this->iriConverter->getResourceFromIri($value['@id']);
			}
		}
		return parent::denormalizeRelation($attributeName, $propertyMetadata, $className, $value, $format, $context);
	}

//	private function hasTemporaryFileProperty(string $type): bool
//	{
//		$classReflection = new \ReflectionClass($type);
//		foreach ($classReflection->getProperties() as $property) {
//			if ($this->isTemporaryFile($property)) {
//				return true;
//			}
//		}
//		return false;
//	}

	private function isTemporaryFile(\ReflectionProperty $property): bool
	{
		return 0 !== count($property->getAttributes(UploadedFileCollection::class))
			|| 0 !== count($property->getAttributes(UploadedFile::class));
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
