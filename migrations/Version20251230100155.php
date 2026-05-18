<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20251230100155
	extends AbstractMigration
{
	public function up(Schema $schema): void
	{
		$this->addSql('CREATE UNIQUE INDEX type ON UserProvider (type)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('DROP INDEX type ON UserProvider');
	}
}
