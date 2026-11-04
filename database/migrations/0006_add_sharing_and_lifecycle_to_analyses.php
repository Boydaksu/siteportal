<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;
use Throwable;

final class AddSharingAndLifecycleToAnalyses implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'analyses tablosuna görünürlük (visibility), paylaşım tokenı (share_token), '
            . 'paylaşım geçerlilik süresi ve analiz yaşam süresi (expires_at, is_revoked) sütunlarını ekler';
    }

    public function up(Connection $db): void
    {
        if ($db->isSqlite()) {
            $db->query("ALTER TABLE analyses ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'unlisted'");
            $db->query('ALTER TABLE analyses ADD COLUMN share_token VARCHAR(64) NULL');
            $db->query('ALTER TABLE analyses ADD COLUMN share_token_expires_at DATETIME NULL');
            $db->query('ALTER TABLE analyses ADD COLUMN expires_at DATETIME NULL');
            $db->query('ALTER TABLE analyses ADD COLUMN is_revoked INT NOT NULL DEFAULT 0');
            $db->query('CREATE INDEX IF NOT EXISTS idx_analyses_share_token ON analyses(share_token)');
            $db->query('CREATE INDEX IF NOT EXISTS idx_analyses_expires_at ON analyses(expires_at)');
        } else {
            $db->query('ALTER TABLE analyses '
                . "ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'unlisted' AFTER mode, "
                . 'ADD COLUMN share_token VARCHAR(64) NULL AFTER visibility, '
                . 'ADD COLUMN share_token_expires_at DATETIME NULL AFTER share_token, '
                . 'ADD COLUMN expires_at DATETIME NULL AFTER score_breakdown_json, '
                . 'ADD COLUMN is_revoked TINYINT(1) NOT NULL DEFAULT 0 AFTER expires_at, '
                . 'ADD INDEX idx_analyses_share_token (share_token), '
                . 'ADD INDEX idx_analyses_expires_at (expires_at)');
        }
    }

    public function down(Connection $db): void
    {
        if ($db->isSqlite()) {
            try {
                $db->query('DROP INDEX IF EXISTS idx_analyses_share_token');
                $db->query('DROP INDEX IF EXISTS idx_analyses_expires_at');
                $db->query('ALTER TABLE analyses DROP COLUMN visibility');
                $db->query('ALTER TABLE analyses DROP COLUMN share_token');
                $db->query('ALTER TABLE analyses DROP COLUMN share_token_expires_at');
                $db->query('ALTER TABLE analyses DROP COLUMN expires_at');
                $db->query('ALTER TABLE analyses DROP COLUMN is_revoked');
            } catch (Throwable) {
                // SQLite eski sürüm toleransı
            }
        } else {
            $db->query('ALTER TABLE analyses '
                . 'DROP INDEX idx_analyses_share_token, '
                . 'DROP INDEX idx_analyses_expires_at, '
                . 'DROP COLUMN visibility, '
                . 'DROP COLUMN share_token, '
                . 'DROP COLUMN share_token_expires_at, '
                . 'DROP COLUMN expires_at, '
                . 'DROP COLUMN is_revoked');
        }
    }
}
