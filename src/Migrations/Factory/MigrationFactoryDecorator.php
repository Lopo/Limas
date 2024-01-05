<?php declare(strict_types=1);

namespace Limas\Migrations\Factory;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;


readonly class MigrationFactoryDecorator
	implements MigrationFactory
{
	public function __construct(
		private MigrationFactory   $migrationFactory,
		private ContainerInterface $container
	)
	{
	}

	public function createVersion(string $migrationClassName): AbstractMigration
	{
		$instance = $this->migrationFactory->createVersion($migrationClassName);

		if ((new \ReflectionClass($instance))->hasMethod('setContainer')) {
			$instance->setContainer($this->container);
		}

		return $instance;
	}
}
