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

		// Migrations can opt in to container injection by declaring a
		// `setContainer(ContainerInterface)` method themselves;
		// AbstractMigration doesn't, so the call only fires on the
		// container-aware subset. Reflective lookup → method_exists is
		// the conventional dance.
		if (method_exists($instance, 'setContainer')) {
			$instance->setContainer($this->container);
		}

		return $instance;
	}
}
