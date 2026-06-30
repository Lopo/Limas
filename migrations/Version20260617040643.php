<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260617040643
	extends AbstractMigration
{
	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE FootprintAlias (id INT AUTO_INCREMENT NOT NULL, alias VARCHAR(255) NOT NULL, aliasNormalized VARCHAR(255) NOT NULL, footprint_id INT DEFAULT NULL, source VARCHAR(16) NOT NULL, usageCount INT DEFAULT 0 NOT NULL, verified TINYINT DEFAULT 0 NOT NULL, createdAt DATETIME NOT NULL, INDEX IDX_footprint_alias_footprint (footprint_id), UNIQUE INDEX UNIQ_footprint_alias_norm (aliasNormalized), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
		$this->addSql('ALTER TABLE FootprintAlias ADD CONSTRAINT FK_1F73065A51364C98 FOREIGN KEY (footprint_id) REFERENCES Footprint (id) ON DELETE SET NULL');

		$this->addSql('ALTER TABLE ReportProject DROP FOREIGN KEY `FK_83B0909B166D1F9C`');
		$this->addSql('ALTER TABLE ReportProject ADD CONSTRAINT FK_83B0909B166D1F9C FOREIGN KEY (project_id) REFERENCES Project (id) ON DELETE CASCADE');

		$this->addSql('ALTER TABLE ManufacturerAlias DROP FOREIGN KEY `FK_D66037BEA23B42D`');
		// Existing rows are presumed verified manual mappings (no aggregator
		// ever inserted unverified ones until this migration ships) so seed
		// them with source='user' + verified=1 + createdAt=NOW
		$this->addSql("ALTER TABLE ManufacturerAlias
            ADD source VARCHAR(16) NOT NULL DEFAULT 'user',
            ADD usageCount INT DEFAULT 0 NOT NULL,
            ADD verified TINYINT DEFAULT 1 NOT NULL,
            ADD createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CHANGE manufacturer_id manufacturer_id INT DEFAULT NULL");
		// Drop SQL-level defaults that aren't declared on the entity (source
		// and createdAt rely on the entity-side init). Reset verified to the
		// entity-declared DEFAULT 0 (back-fill used DEFAULT 1 just to seed
		// pre-existing rows as verified).
		$this->addSql('ALTER TABLE ManufacturerAlias
            ALTER source DROP DEFAULT,
            ALTER createdAt DROP DEFAULT,
            ALTER verified SET DEFAULT 0');
		$this->addSql('ALTER TABLE ManufacturerAlias ADD CONSTRAINT FK_D66037BEA23B42D FOREIGN KEY (manufacturer_id) REFERENCES Manufacturer (id) ON DELETE SET NULL');

		$this->addSql('CREATE TABLE PartCategoryDefaultParameter (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, valueType VARCHAR(16) NOT NULL, category_id INT NOT NULL, unit_id INT DEFAULT NULL, INDEX IDX_35B0EAB6F8BD700D (unit_id), INDEX IDX_pcdp_category (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
		$this->addSql('ALTER TABLE PartCategoryDefaultParameter ADD CONSTRAINT FK_35B0EAB612469DE2 FOREIGN KEY (category_id) REFERENCES PartCategory (id) ON DELETE CASCADE');
		$this->addSql('ALTER TABLE PartCategoryDefaultParameter ADD CONSTRAINT FK_35B0EAB6F8BD700D FOREIGN KEY (unit_id) REFERENCES Unit (id)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE PartCategoryDefaultParameter DROP FOREIGN KEY FK_35B0EAB612469DE2');
		$this->addSql('ALTER TABLE PartCategoryDefaultParameter DROP FOREIGN KEY FK_35B0EAB6F8BD700D');
		$this->addSql('DROP TABLE PartCategoryDefaultParameter');

		$this->addSql('ALTER TABLE ManufacturerAlias DROP FOREIGN KEY FK_D66037BEA23B42D');
		$this->addSql('ALTER TABLE ManufacturerAlias DROP source, DROP usageCount, DROP verified, DROP createdAt, CHANGE manufacturer_id manufacturer_id INT NOT NULL');
		$this->addSql('ALTER TABLE ManufacturerAlias ADD CONSTRAINT `FK_D66037BEA23B42D` FOREIGN KEY (manufacturer_id) REFERENCES Manufacturer (id) ON UPDATE NO ACTION ON DELETE CASCADE');

		$this->addSql('ALTER TABLE ReportProject DROP FOREIGN KEY FK_83B0909B166D1F9C');
		$this->addSql('ALTER TABLE ReportProject ADD CONSTRAINT `FK_83B0909B166D1F9C` FOREIGN KEY (project_id) REFERENCES Project (id) ON UPDATE NO ACTION ON DELETE NO ACTION');

		$this->addSql('ALTER TABLE FootprintAlias DROP FOREIGN KEY FK_1F73065A51364C98');
		$this->addSql('DROP TABLE FootprintAlias');
	}

	public function isTransactional(): bool
	{
		return false;
	}
}
