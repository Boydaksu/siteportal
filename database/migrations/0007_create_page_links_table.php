<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;

final class CreatePageLinksTable implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'İç ve dış link grafiği, anchor metinleri, derinlik ve HTTP durumlarını saklayan page_links tablosu';
    }

    public function up(Connection $db): void
    {
        $idType = $db->isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $tableOptions = $db->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timeType = $db->isSqlite() ? 'DATETIME' : 'TIMESTAMP';

        $db->query("CREATE TABLE IF NOT EXISTS page_links (
            id {$idType},
            analysis_id INT NOT NULL,
            source_url VARCHAR(2048) NOT NULL,
            target_url VARCHAR(2048) NOT NULL,
            target_url_hash VARCHAR(64) NOT NULL,
            anchor_text VARCHAR(1000) NULL,
            is_internal INT NOT NULL DEFAULT 1,
            is_nofollow INT NOT NULL DEFAULT 0,
            http_status INT NULL,
            depth INT NOT NULL DEFAULT 0,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        if ($db->isSqlite()) {
            $db->query('CREATE INDEX IF NOT EXISTS idx_page_links_analysis ON page_links(analysis_id)');
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_page_links_target_hash ON page_links(analysis_id, target_url_hash)'
            );
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_page_links_internal_status '
                . 'ON page_links(analysis_id, is_internal, http_status)'
            );
        } else {
            $db->query('ALTER TABLE page_links '
                . 'ADD INDEX idx_page_links_analysis (analysis_id), '
                . 'ADD INDEX idx_page_links_target_hash (analysis_id, target_url_hash), '
                . 'ADD INDEX idx_page_links_internal_status (analysis_id, is_internal, http_status)');
        }
    }

    public function down(Connection $db): void
    {
        $db->query('DROP TABLE IF EXISTS page_links');
    }
}
