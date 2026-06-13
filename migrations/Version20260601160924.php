<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260601160924
	extends AbstractMigration
{
	public function up(Schema $schema): void
	{
		/* Add ManufacturerAlias table for canonicalising free-form manufacturer name strings (e.g. "onsemi" / "ONSEMI" / "ON Semiconductor") used by InfoProviderMerger */
		$this->addSql('CREATE TABLE ManufacturerAlias (id INT AUTO_INCREMENT NOT NULL, alias VARCHAR(255) NOT NULL, aliasNormalized VARCHAR(255) NOT NULL, manufacturer_id INT NOT NULL, INDEX IDX_manufacturer_alias_manufacturer (manufacturer_id), UNIQUE INDEX UNIQ_manufacturer_alias_norm (aliasNormalized), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
		$this->addSql('ALTER TABLE ManufacturerAlias ADD CONSTRAINT FK_D66037BEA23B42D FOREIGN KEY (manufacturer_id) REFERENCES Manufacturer (id) ON DELETE CASCADE');

		// Backfill: existing manufacturers each get a self-referencing alias entry so
		// canonicalize($mfr->getName()) immediately resolves on existing installs.
		// Normalization mirrors ManufacturerCanonicalizer::normalize() — lowercase + trim + collapse whitespace.
		$this->addSql("INSERT INTO ManufacturerAlias (manufacturer_id, alias, aliasNormalized) SELECT id, name, LOWER(TRIM(REGEXP_REPLACE(name, '[[:space:]]+', ' '))) FROM Manufacturer");


		$this->addSql('ALTER TABLE FootprintAttachment ADD sourceUrl VARCHAR(2048) DEFAULT NULL, ADD downloaded TINYINT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE FootprintImage ADD sourceUrl VARCHAR(2048) DEFAULT NULL, ADD downloaded TINYINT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE ManufacturerICLogo ADD sourceUrl VARCHAR(2048) DEFAULT NULL, ADD downloaded TINYINT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE PartAttachment ADD sourceUrl VARCHAR(2048) DEFAULT NULL, ADD downloaded TINYINT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE ProjectAttachment ADD sourceUrl VARCHAR(2048) DEFAULT NULL, ADD downloaded TINYINT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE StorageLocationImage ADD sourceUrl VARCHAR(2048) DEFAULT NULL, ADD downloaded TINYINT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE TempImage ADD sourceUrl VARCHAR(2048) DEFAULT NULL, ADD downloaded TINYINT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE TempUploadedFile ADD sourceUrl VARCHAR(2048) DEFAULT NULL, ADD downloaded TINYINT DEFAULT 1 NOT NULL');

		$this->addSql('ALTER TABLE FootprintAttachment ADD sha256 VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE FootprintImage ADD sha256 VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE ManufacturerICLogo ADD sha256 VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE PartAttachment ADD sha256 VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE ProjectAttachment ADD sha256 VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE StorageLocationImage ADD sha256 VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE TempImage ADD sha256 VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE TempUploadedFile ADD sha256 VARCHAR(64) DEFAULT NULL');

		$this->addSql('CREATE TABLE ParameterAlias (id INT AUTO_INCREMENT NOT NULL, rawName VARCHAR(255) NOT NULL, rawNameNormalized VARCHAR(255) NOT NULL, canonicalName VARCHAR(255) NOT NULL, shortname VARCHAR(100) DEFAULT NULL, vendor VARCHAR(50) DEFAULT NULL, source VARCHAR(16) NOT NULL, usageCount INT DEFAULT 0 NOT NULL, verified TINYINT DEFAULT 0 NOT NULL, createdAt DATETIME NOT NULL, INDEX IDX_param_alias_canonical (canonicalName), UNIQUE INDEX UNIQ_param_alias_norm (rawNameNormalized, vendor), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('DROP TABLE ParameterAlias');

		$this->addSql('ALTER TABLE FootprintAttachment DROP sha256');
		$this->addSql('ALTER TABLE FootprintImage DROP sha256');
		$this->addSql('ALTER TABLE ManufacturerICLogo DROP sha256');
		$this->addSql('ALTER TABLE PartAttachment DROP sha256');
		$this->addSql('ALTER TABLE ProjectAttachment DROP sha256');
		$this->addSql('ALTER TABLE StorageLocationImage DROP sha256');
		$this->addSql('ALTER TABLE TempImage DROP sha256');
		$this->addSql('ALTER TABLE TempUploadedFile DROP sha256');

		$this->addSql('ALTER TABLE FootprintAttachment DROP sourceUrl, DROP downloaded');
		$this->addSql('ALTER TABLE FootprintImage DROP sourceUrl, DROP downloaded');
		$this->addSql('ALTER TABLE ManufacturerICLogo DROP sourceUrl, DROP downloaded');
		$this->addSql('ALTER TABLE PartAttachment DROP sourceUrl, DROP downloaded');
		$this->addSql('ALTER TABLE ProjectAttachment DROP sourceUrl, DROP downloaded');
		$this->addSql('ALTER TABLE StorageLocationImage DROP sourceUrl, DROP downloaded');
		$this->addSql('ALTER TABLE TempImage DROP sourceUrl, DROP downloaded');
		$this->addSql('ALTER TABLE TempUploadedFile DROP sourceUrl, DROP downloaded');


		$this->addSql('ALTER TABLE ManufacturerAlias DROP FOREIGN KEY FK_D66037BEA23B42D');
		$this->addSql('DROP TABLE ManufacturerAlias');
	}

	public function isTransactional(): bool
	{
		return false;
	}
}
