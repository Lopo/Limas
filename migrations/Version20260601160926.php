<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260601160926
	extends AbstractMigration
{
	public function up(Schema $schema): void
	{
		// Renames are equivalent across rows — no data movement, just the index name lookup tables shift.
		// Doctrine accepts both index naming styles equally well after the rename.
		$this->addSql('ALTER TABLE FootprintAttachment RENAME INDEX fk_blob_5ddf58fc3b TO IDX_7B7388A1ED3E8EA5');
		$this->addSql('ALTER TABLE FootprintImage RENAME INDEX fk_blob_7ad36deb59 TO IDX_3B226991ED3E8EA5');
		$this->addSql('ALTER TABLE ManufacturerICLogo RENAME INDEX fk_blob_db994552ae TO IDX_3F1EF213ED3E8EA5');
		$this->addSql('ALTER TABLE PartAttachment RENAME INDEX fk_blob_272f9e0304 TO IDX_76D73D86ED3E8EA5');
		$this->addSql('ALTER TABLE ProjectAttachment RENAME INDEX fk_blob_832197c718 TO IDX_44010C5BED3E8EA5');
		$this->addSql('ALTER TABLE StorageLocationImage RENAME INDEX fk_blob_ddaffc0a43 TO IDX_666717F0ED3E8EA5');
		$this->addSql('ALTER TABLE TempImage RENAME INDEX fk_blob_b8bbb2177d TO IDX_722156A9ED3E8EA5');
		$this->addSql('ALTER TABLE TempUploadedFile RENAME INDEX fk_blob_da17a0cab8 TO IDX_4898A3C9ED3E8EA5');

		$this->addSql('CREATE TABLE BulkImportJob (id INT AUTO_INCREMENT NOT NULL, createdAt DATETIME NOT NULL, status VARCHAR(20) NOT NULL, totalRows INT NOT NULL, processedRows INT NOT NULL, duplicatesBehavior VARCHAR(20) NOT NULL, createdBy_id INT DEFAULT NULL, defaultCategory_id INT NOT NULL, defaultStorage_id INT NOT NULL, INDEX IDX_6A0BF9023174800F (createdBy_id), INDEX IDX_6A0BF902BFB2B62E (defaultCategory_id), INDEX IDX_6A0BF902CF6651FA (defaultStorage_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
		$this->addSql('CREATE TABLE BulkImportJobItem (id INT AUTO_INCREMENT NOT NULL, line INT NOT NULL, rawMpn VARCHAR(255) NOT NULL, rawManufacturer VARCHAR(255) DEFAULT NULL, rawCategory VARCHAR(512) DEFAULT NULL, rawStorage VARCHAR(512) DEFAULT NULL, status VARCHAR(20) NOT NULL, errorMessage LONGTEXT DEFAULT NULL, job_id INT NOT NULL, part_id INT DEFAULT NULL, existingPart_id INT DEFAULT NULL, INDEX IDX_E1A65B79BE04EA9 (job_id), INDEX IDX_E1A65B794CE34BEC (part_id), INDEX IDX_E1A65B79965A861F (existingPart_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
		$this->addSql('ALTER TABLE BulkImportJob ADD CONSTRAINT FK_6A0BF9023174800F FOREIGN KEY (createdBy_id) REFERENCES User (id) ON DELETE SET NULL');
		$this->addSql('ALTER TABLE BulkImportJob ADD CONSTRAINT FK_6A0BF902BFB2B62E FOREIGN KEY (defaultCategory_id) REFERENCES PartCategory (id)');
		$this->addSql('ALTER TABLE BulkImportJob ADD CONSTRAINT FK_6A0BF902CF6651FA FOREIGN KEY (defaultStorage_id) REFERENCES StorageLocation (id)');
		$this->addSql('ALTER TABLE BulkImportJobItem ADD CONSTRAINT FK_E1A65B79BE04EA9 FOREIGN KEY (job_id) REFERENCES BulkImportJob (id) ON DELETE CASCADE');
		$this->addSql('ALTER TABLE BulkImportJobItem ADD CONSTRAINT FK_E1A65B794CE34BEC FOREIGN KEY (part_id) REFERENCES Part (id) ON DELETE SET NULL');
		$this->addSql('ALTER TABLE BulkImportJobItem ADD CONSTRAINT FK_E1A65B79965A861F FOREIGN KEY (existingPart_id) REFERENCES Part (id) ON DELETE SET NULL');

		$this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

		$this->addSql('ALTER TABLE BulkImportJobItem ADD rawQuantity VARCHAR(64) DEFAULT NULL, ADD quantityApplied INT DEFAULT NULL');

		$this->addSql('DROP TABLE CronLogger');

		$this->addSql('ALTER TABLE FootprintAttachment ADD sourceAdapter VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE FootprintImage ADD sourceAdapter VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE ManufacturerICLogo ADD sourceAdapter VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE PartAttachment ADD sourceAdapter VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE ProjectAttachment ADD sourceAdapter VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE StorageLocationImage ADD sourceAdapter VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE TempImage ADD sourceAdapter VARCHAR(64) DEFAULT NULL');
		$this->addSql('ALTER TABLE TempUploadedFile ADD sourceAdapter VARCHAR(64) DEFAULT NULL');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE FootprintAttachment DROP sourceAdapter');
		$this->addSql('ALTER TABLE FootprintImage DROP sourceAdapter');
		$this->addSql('ALTER TABLE ManufacturerICLogo DROP sourceAdapter');
		$this->addSql('ALTER TABLE PartAttachment DROP sourceAdapter');
		$this->addSql('ALTER TABLE ProjectAttachment DROP sourceAdapter');
		$this->addSql('ALTER TABLE StorageLocationImage DROP sourceAdapter');
		$this->addSql('ALTER TABLE TempImage DROP sourceAdapter');
		$this->addSql('ALTER TABLE TempUploadedFile DROP sourceAdapter');

		$this->addSql('CREATE TABLE CronLogger (id INT AUTO_INCREMENT NOT NULL, lastRunDate DATETIME NOT NULL, cronjob VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX UNIQ_B4000D4FA5DA7C8A (cronjob), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');

		$this->addSql('ALTER TABLE BulkImportJobItem DROP rawQuantity, DROP quantityApplied');

		$this->addSql('DROP TABLE messenger_messages');

		$this->addSql('ALTER TABLE BulkImportJob DROP FOREIGN KEY FK_6A0BF9023174800F');
		$this->addSql('ALTER TABLE BulkImportJob DROP FOREIGN KEY FK_6A0BF902BFB2B62E');
		$this->addSql('ALTER TABLE BulkImportJob DROP FOREIGN KEY FK_6A0BF902CF6651FA');
		$this->addSql('ALTER TABLE BulkImportJobItem DROP FOREIGN KEY FK_E1A65B79BE04EA9');
		$this->addSql('ALTER TABLE BulkImportJobItem DROP FOREIGN KEY FK_E1A65B794CE34BEC');
		$this->addSql('ALTER TABLE BulkImportJobItem DROP FOREIGN KEY FK_E1A65B79965A861F');
		$this->addSql('DROP TABLE BulkImportJob');
		$this->addSql('DROP TABLE BulkImportJobItem');

		$this->addSql('ALTER TABLE FootprintAttachment RENAME INDEX idx_7b7388a1ed3e8ea5 TO FK_blob_5ddf58fc3b');
		$this->addSql('ALTER TABLE FootprintImage RENAME INDEX idx_3b226991ed3e8ea5 TO FK_blob_7ad36deb59');
		$this->addSql('ALTER TABLE ManufacturerICLogo RENAME INDEX idx_3f1ef213ed3e8ea5 TO FK_blob_db994552ae');
		$this->addSql('ALTER TABLE PartAttachment RENAME INDEX idx_76d73d86ed3e8ea5 TO FK_blob_272f9e0304');
		$this->addSql('ALTER TABLE ProjectAttachment RENAME INDEX idx_44010c5bed3e8ea5 TO FK_blob_832197c718');
		$this->addSql('ALTER TABLE StorageLocationImage RENAME INDEX idx_666717f0ed3e8ea5 TO FK_blob_ddaffc0a43');
		$this->addSql('ALTER TABLE TempImage RENAME INDEX idx_722156a9ed3e8ea5 TO FK_blob_b8bbb2177d');
		$this->addSql('ALTER TABLE TempUploadedFile RENAME INDEX idx_4898a3c9ed3e8ea5 TO FK_blob_da17a0cab8');
	}

	public function isTransactional(): bool
	{
		return false;
	}
}
