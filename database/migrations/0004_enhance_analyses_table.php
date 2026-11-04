<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;
use Throwable;

final class EnhanceAnalysesTable implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'analyses tablosuna hedef URL, ilerleme ve hata alanları ekler';
    }

    public function up(Connection $db): void
    {
        if ($db->isSqlite()) {
            $db->query("ALTER TABLE analyses ADD COLUMN target_url VARCHAR(2048) NOT NULL DEFAULT ''");
            $db->query('ALTER TABLE analyses ADD COLUMN progress_percent INT NOT NULL DEFAULT 0');
            $db->query('ALTER TABLE analyses ADD COLUMN error_code VARCHAR(100) NULL');
            $db->query('ALTER TABLE analyses ADD COLUMN error_message TEXT NULL');
            $db->query('CREATE INDEX IF NOT EXISTS idx_analyses_status_created ON analyses (status, created_at)');
        } else {
            $db->query('ALTER TABLE analyses '
                . "ADD COLUMN target_url VARCHAR(2048) NOT NULL DEFAULT '' AFTER public_id, "
                . 'ADD COLUMN progress_percent INT NOT NULL DEFAULT 0 AFTER status, '
                . 'ADD COLUMN error_code VARCHAR(100) NULL AFTER scope_count, '
                . 'ADD COLUMN error_message TEXT NULL AFTER error_code, '
                . 'ADD INDEX idx_analyses_status_created (status, created_at)');
        }
    }

    public function down(Connection $db): void
    {
        if ($db->isSqlite()) {
            try {
                $db->query('DROP INDEX IF EXISTS idx_analyses_status_created');
                $db->query('ALTER TABLE analyses DROP COLUMN target_url');
                $db->query('ALTER TABLE analyses DROP COLUMN progress_percent');
                $db->query('ALTER TABLE analyses DROP COLUMN error_code');
                $db->query('ALTER TABLE analyses DROP COLUMN error_message');
            } catch (Throwable) {
                // SQLite eski sürümlerde DROP COLUMN toleransı
            }
        } else {
            $db->query('ALTER TABLE analyses '
                . 'DROP INDEX idx_analyses_status_created, '
                . 'DROP COLUMN target_url, '
                . 'DROP COLUMN progress_percent, '
                . 'DROP COLUMN error_code, '
                . 'DROP COLUMN error_message');
        }
    }
}
