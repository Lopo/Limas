<?php

namespace Limas\Listener;

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Limas\Annotation\UploadedFileCollection;
use Limas\Entity\Image;
use Limas\Entity\TempImage;
use Limas\Entity\TempUploadedFile;
use Limas\Entity\UploadedFile;
use Limas\Service\ImageService;
use Limas\Service\UploadedFileService;
use Nette\Utils\Strings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;


class TemporaryFile
	implements EventSubscriberInterface
{
	public function __construct(
		private readonly UploadedFileService       $uploadedFileService,
		private readonly ImageService              $imageService,
		private readonly PropertyAccessorInterface $propertyAccessor,
		private readonly IriConverterInterface     $iriConverter
	)
	{
	}

	public static function getSubscribedEvents(): array
	{
		return [
			ViewEvent::class => [
				['onKernelView', 100]
			]
		];
	}

	/**
	 * Replaces any temporary images with actual instances of the configured UploadedFile collection.
	 *
	 * Automatically extracts the proper setters and getters from the metadata and instantiates the correct
	 * UploadedFile child class.
	 */
	public function onKernelView(ViewEvent $event): void
	{
		$data = $event->getControllerResult();
		if (!is_object($data)) {
			return;
		}

		$classReflection = new \ReflectionClass($data);
		if (!Strings::startsWith($classReflection->getNamespaceName(), 'Limas\\')) {
			return;
		}

		foreach ($classReflection->getProperties() as $property) {
			$propertyAnnotationCollection = $property->getAttributes(UploadedFileCollection::class);
			$propertyAnnotation = $property->getAttributes(\Limas\Annotation\UploadedFile::class);
			$manyToOneAnnotation = $property->getAttributes(OneToMany::class);
			$oneToOneAnnotation = $property->getAttributes(OneToOne::class);

			if (0 !== count($propertyAnnotationCollection) || 0 !== count($propertyAnnotation)) {
				if (0 !== count($manyToOneAnnotation)) {
					$collection = $this->propertyAccessor->getValue($data, $property->getName());
					foreach ($collection as $key => $item) {
						if ($item instanceof TempUploadedFile || $item instanceof TempImage) {
							$collection[$key] = $this->setReplacementFile($manyToOneAnnotation[0]->newInstance()->target, $item, $data);
						}
					}

					$this->propertyAccessor->setValue($data, $property->getName(), $collection);
				}

				if (0 !== count($oneToOneAnnotation)) {
					$item = $this->propertyAccessor->getValue($data, $property->getName());
					if ($item instanceof TempUploadedFile || $item instanceof TempImage) {
						$this->propertyAccessor->setValue($data, $property->getName(), $this->setReplacementFile($oneToOneAnnotation[0]->newInstance()->target, $item, $data));
					} else {
						$item = $this->propertyAccessor->getValue($data, $property->getName());
						if ($item !== null && $item->getReplacement() !== null) {
							$this->replaceFile($item, $this->iriConverter->getItemFromIri($item->getReplacement()));
						}
					}
				}
			}
		}

		$event->setControllerResult($data);
	}

	/**
	 * Replaces the TemporaryUploadedFile or TempImage with the actual instance. Automatically sets the
	 * reference to the owning entity.
	 */
	protected function setReplacementFile(string $targetEntity, TempUploadedFile|TempImage $source, object $target): object
	{
		$newFile = new $targetEntity();

		$this->replaceFile($newFile, $source);

		$setterName = $this->getReferenceSetter($newFile, $target);
		if ($setterName !== false) {
			$this->propertyAccessor->setValue($newFile, $setterName, $target);
		}

		return $newFile;
	}

	protected function replaceFile(UploadedFile $target, UploadedFile $source): void
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
		$inverseSideReflection = new \ReflectionClass($inverseSideEntity);
		$owningSideReflection = new \ReflectionClass($owningSideEntity);

		foreach ($inverseSideReflection->getProperties() as $inverseSideProperty) {
			$manyToOneAssociation = $inverseSideProperty->getAttributes(ManyToOne::class);
			if (0 !== count($manyToOneAssociation)
				&& $manyToOneAssociation[0]->newInstance()->target === $owningSideReflection->getName()
			) {
				return $inverseSideProperty->getName();
			}
		}

		return false;
	}
}
