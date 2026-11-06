<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;

final class CreateUrlInspectionTables implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Google Search Console URL Inspection API cache tablosu';
    }

    public function up(Connection $db): void
    {
        $idType = $db->isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $tableOptions = $db->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timeType = $db->isSqlite() ? 'DATETIME' : 'TIMESTAMP';
        $mediumText = $db->isSqlite() ? 'TEXT' : 'MEDIUMTEXT';

        // url_inspection_caches tablosu
        $db->query("CREATE TABLE IF NOT EXISTS url_inspection_caches (
            id {$idType},
            site_id INT NOT NULL,
            url_hash VARCHAR(64) NOT NULL,
            inspection_url TEXT NOT NULL,
            gsc_site_url VARCHAR(255) NOT NULL,
            index_verdict VARCHAR(32) NOT NULL,
            coverage_state VARCHAR(128) NULL,
            robots_txt_state VARCHAR(64) NULL,
            indexing_state VARCHAR(64) NULL,
            last_crawl_time VARCHAR(64) NULL,
            page_fetch_state VARCHAR(64) NULL,
            google_canonical TEXT NULL,
            user_canonical TEXT NULL,
            crawled_as VARCHAR(32) NULL,
            raw_payload_json {$mediumText} NOT NULL,
            fetched_at {$timeType} NOT NULL,
            expires_at {$timeType} NOT NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // Indeksler
        if ($db->isSqlite()) {
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS uk_url_inspection_site_hash '
                . 'ON url_inspection_caches (site_id, url_hash)'
            );
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_url_inspection_expiry '
                . 'ON url_inspection_caches (expires_at)'
            );
        } else {
            $existingIndexes = $db->fetchAll(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'url_inspection_caches'"
            );
            $indexNames = array_column($existingIndexes, 'INDEX_NAME');

            if (!in_array('uk_url_inspection_site_hash', $indexNames, true)) {
                $db->query(
                    'ALTER TABLE url_inspection_caches ADD UNIQUE KEY uk_url_inspection_site_hash (site_id, url_hash)'
                );
            }
            if (!in_array('idx_url_inspection_expiry', $indexNames, true)) {
                $db->query('ALTER TABLE url_inspection_caches ADD KEY idx_url_inspection_expiry (expires_at)');
            }
        }
    }

    public function down(Connection $db): void
    {
        $db->query('DROP TABLE IF EXISTS url_inspection_caches');
    }
}
