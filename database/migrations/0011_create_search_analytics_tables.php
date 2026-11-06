<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;

final class CreateSearchAnalyticsTables implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Google Search Console Search Analytics cache ve site mapping tablolari';
    }

    public function up(Connection $db): void
    {
        $idType = $db->isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $tableOptions = $db->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timeType = $db->isSqlite() ? 'DATETIME' : 'TIMESTAMP';
        $mediumText = $db->isSqlite() ? 'TEXT' : 'MEDIUMTEXT';

        // 1. search_analytics_caches tablosu
        $db->query("CREATE TABLE IF NOT EXISTS search_analytics_caches (
            id {$idType},
            site_id INT NOT NULL,
            query_hash VARCHAR(64) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            dimensions_json TEXT NOT NULL,
            summary_json TEXT NOT NULL,
            rows_json {$mediumText} NOT NULL,
            fetched_at {$timeType} NOT NULL,
            expires_at {$timeType} NOT NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 2. gsc_site_mappings tablosu
        $db->query("CREATE TABLE IF NOT EXISTS gsc_site_mappings (
            id {$idType},
            site_id INT NOT NULL,
            gsc_site_url VARCHAR(255) NOT NULL,
            permission_level VARCHAR(50) NOT NULL DEFAULT 'siteFullUser',
            verified INT NOT NULL DEFAULT 1,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            updated_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // Indeksler
        if ($db->isSqlite()) {
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS uk_sa_cache_query ON search_analytics_caches (site_id, query_hash)'
            );
            $db->query('CREATE INDEX IF NOT EXISTS idx_sa_cache_expiry ON search_analytics_caches (expires_at)');
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS uk_gsc_site_mapping ON gsc_site_mappings (site_id, gsc_site_url)'
            );
            $db->query('CREATE INDEX IF NOT EXISTS idx_gsc_site_url ON gsc_site_mappings (gsc_site_url)');
        } else {
            $this->createIndexIfNotExists(
                $db,
                'search_analytics_caches',
                'uk_sa_cache_query',
                'UNIQUE (site_id, query_hash)'
            );
            $this->createIndexIfNotExists(
                $db,
                'search_analytics_caches',
                'idx_sa_cache_expiry',
                '(expires_at)'
            );
            $this->createIndexIfNotExists(
                $db,
                'gsc_site_mappings',
                'uk_gsc_site_mapping',
                'UNIQUE (site_id, gsc_site_url)'
            );
            $this->createIndexIfNotExists(
                $db,
                'gsc_site_mappings',
                'idx_gsc_site_url',
                '(gsc_site_url)'
            );
        }
    }

    public function down(Connection $db): void
    {
        $db->query('DROP TABLE IF EXISTS search_analytics_caches');
        $db->query('DROP TABLE IF EXISTS gsc_site_mappings');
    }

    private function createIndexIfNotExists(Connection $db, string $table, string $indexName, string $columns): void
    {
        $exists = $db->fetch(
            'SELECT COUNT(*) as cnt FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :tbl AND index_name = :idx',
            ['tbl' => $table, 'idx' => $indexName]
        );

        if ((int) ($exists['cnt'] ?? 0) === 0) {
            $isUnique = str_starts_with($columns, 'UNIQUE');
            if ($isUnique) {
                $cols = trim(substr($columns, 6));
                $db->query("ALTER TABLE {$table} ADD CONSTRAINT {$indexName} UNIQUE {$cols}");
            } else {
                $db->query("CREATE INDEX {$indexName} ON {$table} {$columns}");
            }
        }
    }
}
