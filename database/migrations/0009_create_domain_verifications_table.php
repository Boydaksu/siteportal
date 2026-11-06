<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;
use Throwable;

final class CreateDomainVerificationsTable implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Domain dogrulama (DNS TXT, HTTP dosya), token yonetimi ve siteler icin dogrulama methodu';
    }

    public function up(Connection $db): void
    {
        $idType = $db->isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $tableOptions = $db->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timeType = $db->isSqlite() ? 'DATETIME' : 'TIMESTAMP';

        // 1. sites tablosuna verification_method kolonu ekleme
        if ($db->isSqlite()) {
            $db->query('ALTER TABLE sites ADD COLUMN verification_method VARCHAR(20) NULL');
        } else {
            $db->query('ALTER TABLE sites ADD COLUMN verification_method VARCHAR(20) NULL AFTER verified_at');
        }

        // 2. domain_verifications tablosu
        $db->query("CREATE TABLE IF NOT EXISTS domain_verifications (
            id {$idType},
            site_id INT NOT NULL,
            user_id INT NOT NULL,
            method VARCHAR(20) NOT NULL,
            token VARCHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            expires_at {$timeType} NOT NULL,
            last_checked_at {$timeType} NULL,
            verified_at {$timeType} NULL,
            failure_reason VARCHAR(255) NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            updated_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // Indeksler
        if ($db->isSqlite()) {
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS uk_domain_verif_token '
                . 'ON domain_verifications(token)'
            );
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_domain_verif_site_status '
                . 'ON domain_verifications(site_id, status)'
            );
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_domain_verif_user_site '
                . 'ON domain_verifications(user_id, site_id)'
            );
        } else {
            $db->query(
                'ALTER TABLE domain_verifications ADD UNIQUE INDEX uk_domain_verif_token (token)'
            );
            $db->query(
                'ALTER TABLE domain_verifications ADD INDEX idx_domain_verif_site_status (site_id, status)'
            );
            $db->query(
                'ALTER TABLE domain_verifications ADD INDEX idx_domain_verif_user_site (user_id, site_id)'
            );
        }
    }

    public function down(Connection $db): void
    {
        $db->query('DROP TABLE IF EXISTS domain_verifications');

        if ($db->isSqlite()) {
            try {
                $db->query('ALTER TABLE sites DROP COLUMN verification_method');
            } catch (Throwable) {
                // Eski SQLite surumleri DROP COLUMN desteklemeyebilir
            }
        } else {
            $db->query('ALTER TABLE sites DROP COLUMN verification_method');
        }
    }
}
