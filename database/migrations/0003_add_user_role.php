<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;
use Throwable;

final class AddUserRole implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'users tablosuna role ekler, admin yetkilendirir ve rate_limits tablosunu olusturur';
    }

    public function up(Connection $db): void
    {
        // 1. users tablosuna role kolonu ekleme
        if ($db->isSqlite()) {
            $db->query("ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user'");
        } else {
            $db->query("ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user' AFTER status");
        }

        // admin@siteportal.local kullanicisini admin olarak guncelle
        $db->execute(
            'UPDATE users SET role = :role WHERE email = :email',
            ['role' => 'admin', 'email' => 'admin@siteportal.local']
        );

        // 2. rate_limits tablosu
        $tableOptions = $db->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->query("CREATE TABLE IF NOT EXISTS rate_limits (
            key_hash VARCHAR(64) NOT NULL PRIMARY KEY,
            hits INTEGER NOT NULL DEFAULT 1,
            reset_at INTEGER NOT NULL
        ){$tableOptions}");
    }

    public function down(Connection $db): void
    {
        // rate_limits tablosunu kaldir
        $db->query('DROP TABLE IF EXISTS rate_limits');

        // role kolonunu kaldir
        if ($db->isSqlite()) {
            try {
                $db->query('ALTER TABLE users DROP COLUMN role');
            } catch (Throwable) {
                // SQLite 3.35 oncesi tolere edilir
            }
        } else {
            $db->query('ALTER TABLE users DROP COLUMN role');
        }
    }
}
