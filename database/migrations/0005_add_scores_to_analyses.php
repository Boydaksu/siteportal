<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;
use Throwable;

final class AddScoresToAnalyses implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'analyses tablosuna genel skor, kategori skorları ve döküm alanları ekler';
    }

    public function up(Connection $db): void
    {
        if ($db->isSqlite()) {
            $db->query('ALTER TABLE analyses ADD COLUMN score_overall INT NULL');
            $db->query('ALTER TABLE analyses ADD COLUMN score_technical INT NULL');
            $db->query('ALTER TABLE analyses ADD COLUMN score_onpage INT NULL');
            $db->query('ALTER TABLE analyses ADD COLUMN score_links INT NULL');
            $db->query('ALTER TABLE analyses ADD COLUMN score_accessibility INT NULL');
            $db->query('ALTER TABLE analyses ADD COLUMN coverage_percent INT NOT NULL DEFAULT 0');
            $db->query('ALTER TABLE analyses ADD COLUMN score_breakdown_json TEXT NULL');
        } else {
            $db->query('ALTER TABLE analyses '
                . 'ADD COLUMN score_overall INT NULL AFTER progress_percent, '
                . 'ADD COLUMN score_technical INT NULL AFTER score_overall, '
                . 'ADD COLUMN score_onpage INT NULL AFTER score_technical, '
                . 'ADD COLUMN score_links INT NULL AFTER score_onpage, '
                . 'ADD COLUMN score_accessibility INT NULL AFTER score_links, '
                . 'ADD COLUMN coverage_percent INT NOT NULL DEFAULT 0 AFTER score_accessibility, '
                . 'ADD COLUMN score_breakdown_json TEXT NULL AFTER coverage_percent');
        }
    }

    public function down(Connection $db): void
    {
        if ($db->isSqlite()) {
            try {
                $db->query('ALTER TABLE analyses DROP COLUMN score_overall');
                $db->query('ALTER TABLE analyses DROP COLUMN score_technical');
                $db->query('ALTER TABLE analyses DROP COLUMN score_onpage');
                $db->query('ALTER TABLE analyses DROP COLUMN score_links');
                $db->query('ALTER TABLE analyses DROP COLUMN score_accessibility');
                $db->query('ALTER TABLE analyses DROP COLUMN coverage_percent');
                $db->query('ALTER TABLE analyses DROP COLUMN score_breakdown_json');
            } catch (Throwable) {
                // SQLite eski sürüm toleransı
            }
        } else {
            $db->query('ALTER TABLE analyses '
                . 'DROP COLUMN score_overall, '
                . 'DROP COLUMN score_technical, '
                . 'DROP COLUMN score_onpage, '
                . 'DROP COLUMN score_links, '
                . 'DROP COLUMN score_accessibility, '
                . 'DROP COLUMN coverage_percent, '
                . 'DROP COLUMN score_breakdown_json');
        }
    }
}
