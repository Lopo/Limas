<?php

namespace Limas\Configuration;

use ApiPlatform\Api\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Limas\Filter\AdvancedSearchFilter;
use Limas\Service\ReflectionService;


abstract class BaseConfiguration
{
	public static array $logs = [];
	public static array $persistEntities = [];
	private array $path = [];


	public function __construct(
		protected ClassMetadata          $classMetadata,
		protected string                 $baseEntity,
		protected ReflectionService      $reflectionService,
		protected EntityManagerInterface $em,
		protected AdvancedSearchFilter   $advancedSearchFilter,
		protected IriConverterInterface  $iriConverter
	)
	{
	}

	/**
	 * Returns the path of this configuration node with an optional suffix
	 *
	 * @param bool|string $suffix Set to any string to return an additional suffix, or false to skip
	 *
	 * @return array The individual path components
	 */
	public function getPath(bool|string $suffix = false): array
	{
		if ($suffix !== false) {
			$path = $this->path;
			$path[] = $suffix;

			return $path;
		}
		return $this->path;
	}

	/**
	 * Sets a path for this configuration node
	 */
	public function setPath(array $path): self
	{
		$this->path = $path;
		return $this;
	}

	abstract public function import(array $row): mixed;

	public function persist($entity): void
	{
		self::$persistEntities[] = $entity;
	}

	public function getPersistEntities(): array
	{
		return self::$persistEntities;
	}

	public function log($message): void
	{
		self::$logs[] = $message;
	}

	public function getLog(): array
	{
		return self::$logs;
	}

	public function clearLog(): void
	{
		self::$logs = [];
	}
}
