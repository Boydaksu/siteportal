<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;
use Throwable;

final class EnhanceAnalysisJobs implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'analysis_jobs tablosuna priority, last_error alanları ve arama indeksleri ekler';
    }

    public function up(Connection $db): void
    {
        if ($db->isSqlite()) {
            $db->query('ALTER TABLE analysis_jobs ADD COLUMN priority INT NOT NULL DEFAULT 0');
            $db->query('ALTER TABLE analysis_jobs ADD COLUMN last_error TEXT NULL');
            $db->query('CREATE INDEX IF NOT EXISTS idx_analysis_jobs_claim ON analysis_jobs '
                . '(status, available_at, leased_until, priority)');
            $db->query('CREATE INDEX IF NOT EXISTS idx_analysis_jobs_analysis_id ON analysis_jobs (analysis_id)');
        } else {
            $db->query('ALTER TABLE analysis_jobs '
                . 'ADD COLUMN priority INT NOT NULL DEFAULT 0 AFTER payload_json, '
                . 'ADD COLUMN last_error TEXT NULL AFTER worker_id, '
                . 'ADD INDEX idx_analysis_jobs_claim (status, available_at, leased_until, priority), '
                . 'ADD INDEX idx_analysis_jobs_analysis_id (analysis_id)');
        }
    }

    public function down(Connection $db): void
    {
        if ($db->isSqlite()) {
            try {
                $db->query('DROP INDEX IF EXISTS idx_analysis_jobs_claim');
                $db->query('DROP INDEX IF EXISTS idx_analysis_jobs_analysis_id');
                $db->query('ALTER TABLE analysis_jobs DROP COLUMN priority');
                $db->query('ALTER TABLE analysis_jobs DROP COLUMN last_error');
            } catch (Throwable) {
                // SQLite 3.35 öncesi DROP COLUMN desteklemez; geri alma temizliği tolere edilir
            }
        } else {
            $db->query('ALTER TABLE analysis_jobs '
                . 'DROP INDEX idx_analysis_jobs_claim, '
                . 'DROP INDEX idx_analysis_jobs_analysis_id, '
                . 'DROP COLUMN priority, '
                . 'DROP COLUMN last_error');
        }
    }
}
