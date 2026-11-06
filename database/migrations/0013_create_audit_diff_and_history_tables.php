<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;
use Throwable;

final class CreateAuditDiffAndHistoryTables implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Taramalar arasi diff karsilastirmasi ve bulgu tarihce takip tablolari';
    }

    public function up(Connection $db): void
    {
        $idType = $db->isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $tableOptions = $db->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timeType = $db->isSqlite() ? 'DATETIME' : 'TIMESTAMP';
        $mediumText = $db->isSqlite() ? 'TEXT' : 'MEDIUMTEXT';

        // 1. findings tablosuna first_seen_analysis_id, first_seen_at ve resolved_at ekle
        if ($db->isSqlite()) {
            $db->query('ALTER TABLE findings ADD COLUMN first_seen_analysis_id INT NULL');
            $db->query("ALTER TABLE findings ADD COLUMN first_seen_at {$timeType} NULL");
            $db->query("ALTER TABLE findings ADD COLUMN resolved_at {$timeType} NULL");
        } else {
            $existingCols = $db->fetchAll(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'findings'"
            );
            $colNames = array_column($existingCols, 'COLUMN_NAME');

            if (!in_array('first_seen_analysis_id', $colNames, true)) {
                $db->query('ALTER TABLE findings ADD COLUMN first_seen_analysis_id INT NULL AFTER analysis_id');
            }
            if (!in_array('first_seen_at', $colNames, true)) {
                $db->query("ALTER TABLE findings ADD COLUMN first_seen_at {$timeType} NULL AFTER observed_at");
            }
            if (!in_array('resolved_at', $colNames, true)) {
                $db->query("ALTER TABLE findings ADD COLUMN resolved_at {$timeType} NULL AFTER first_seen_at");
            }
        }

        // 2. analysis_diffs tablosu
        $db->query("CREATE TABLE IF NOT EXISTS analysis_diffs (
            id {$idType},
            site_id INT NOT NULL,
            base_analysis_id INT NOT NULL,
            target_analysis_id INT NOT NULL,
            score_delta_overall INT NOT NULL DEFAULT 0,
            score_delta_technical INT NOT NULL DEFAULT 0,
            score_delta_onpage INT NOT NULL DEFAULT 0,
            score_delta_links INT NOT NULL DEFAULT 0,
            score_delta_accessibility INT NOT NULL DEFAULT 0,
            ruleset_version_base VARCHAR(20) NOT NULL,
            ruleset_version_target VARCHAR(20) NOT NULL,
            version_mismatch_warning INT NOT NULL DEFAULT 0,
            new_issues_count INT NOT NULL DEFAULT 0,
            resolved_issues_count INT NOT NULL DEFAULT 0,
            persisting_issues_count INT NOT NULL DEFAULT 0,
            diff_summary_json {$mediumText} NOT NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // Indeksler
        if ($db->isSqlite()) {
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS uk_diff_base_target '
                . 'ON analysis_diffs (base_analysis_id, target_analysis_id)'
            );
            $db->query('CREATE INDEX IF NOT EXISTS idx_diff_site ON analysis_diffs (site_id)');
        } else {
            $existingIndexes = $db->fetchAll(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'analysis_diffs'"
            );
            $indexNames = array_column($existingIndexes, 'INDEX_NAME');

            if (!in_array('uk_diff_base_target', $indexNames, true)) {
                $db->query(
                    'ALTER TABLE analysis_diffs ADD UNIQUE KEY uk_diff_base_target '
                    . '(base_analysis_id, target_analysis_id)'
                );
            }
            if (!in_array('idx_diff_site', $indexNames, true)) {
                $db->query('ALTER TABLE analysis_diffs ADD KEY idx_diff_site (site_id)');
            }
        }
    }

    public function down(Connection $db): void
    {
        $db->query('DROP TABLE IF EXISTS analysis_diffs');

        if ($db->isSqlite()) {
            try {
                $db->query('ALTER TABLE findings DROP COLUMN first_seen_analysis_id');
                $db->query('ALTER TABLE findings DROP COLUMN first_seen_at');
                $db->query('ALTER TABLE findings DROP COLUMN resolved_at');
            } catch (Throwable) {
                // SQLite tolerans
            }
        } else {
            $db->query('ALTER TABLE findings 
                DROP COLUMN first_seen_analysis_id, 
                DROP COLUMN first_seen_at, 
                DROP COLUMN resolved_at');
        }
    }
}
