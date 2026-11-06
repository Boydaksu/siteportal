<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;
use Throwable;

final class CreateAuthAndRbacTables implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'E-posta dogrulama, sifre sifirlama, MFA alanlari ve site uyelikleri (site_members) RBAC tablolari';
    }

    public function up(Connection $db): void
    {
        $idType = $db->isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $tableOptions = $db->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timeType = $db->isSqlite() ? 'DATETIME' : 'TIMESTAMP';

        // 1. users tablosuna MFA ve e-posta dogrulama kolonlari ekleme
        if ($db->isSqlite()) {
            $db->query("ALTER TABLE users ADD COLUMN email_verified_at {$timeType} NULL");
            $db->query('ALTER TABLE users ADD COLUMN mfa_secret VARCHAR(64) NULL');
            $db->query('ALTER TABLE users ADD COLUMN mfa_enabled INTEGER NOT NULL DEFAULT 0');
            $db->query('ALTER TABLE users ADD COLUMN mfa_recovery_codes TEXT NULL');
        } else {
            $db->query("ALTER TABLE users ADD COLUMN email_verified_at {$timeType} NULL AFTER role");
            $db->query('ALTER TABLE users ADD COLUMN mfa_secret VARCHAR(64) NULL AFTER email_verified_at');
            $db->query('ALTER TABLE users ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER mfa_secret');
            $db->query('ALTER TABLE users ADD COLUMN mfa_recovery_codes TEXT NULL AFTER mfa_enabled');
        }

        // 2. password_resets tablosu
        $db->query("CREATE TABLE IF NOT EXISTS password_resets (
            id {$idType},
            email VARCHAR(255) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at {$timeType} NOT NULL,
            used_at {$timeType} NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 3. email_verifications tablosu
        $db->query("CREATE TABLE IF NOT EXISTS email_verifications (
            id {$idType},
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at {$timeType} NOT NULL,
            used_at {$timeType} NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 4. site_members tablosu (RBAC: owner, member)
        $db->query("CREATE TABLE IF NOT EXISTS site_members (
            id {$idType},
            site_id INT NOT NULL,
            user_id INT NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'member',
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT uk_site_user UNIQUE (site_id, user_id)
        ){$tableOptions}");

        // Indeksler
        if ($db->isSqlite()) {
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_password_resets_token '
                . 'ON password_resets(token_hash, email)'
            );
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_email_verif_token '
                . 'ON email_verifications(token_hash, user_id)'
            );
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_site_members_lookup '
                . 'ON site_members(user_id, site_id, role)'
            );
        } else {
            $db->query(
                'ALTER TABLE password_resets ADD INDEX idx_password_resets_token (token_hash, email)'
            );
            $db->query(
                'ALTER TABLE email_verifications ADD INDEX idx_email_verif_token (token_hash, user_id)'
            );
            $db->query(
                'ALTER TABLE site_members ADD INDEX idx_site_members_lookup (user_id, site_id, role)'
            );
        }
    }

    public function down(Connection $db): void
    {
        $db->query('DROP TABLE IF EXISTS site_members');
        $db->query('DROP TABLE IF EXISTS email_verifications');
        $db->query('DROP TABLE IF EXISTS password_resets');

        if ($db->isSqlite()) {
            try {
                $db->query('ALTER TABLE users DROP COLUMN mfa_recovery_codes');
                $db->query('ALTER TABLE users DROP COLUMN mfa_enabled');
                $db->query('ALTER TABLE users DROP COLUMN mfa_secret');
                $db->query('ALTER TABLE users DROP COLUMN email_verified_at');
            } catch (Throwable) {
                // Eski SQLite surumleri DROP COLUMN desteklemeyebilir
            }
        } else {
            $db->query('ALTER TABLE users DROP COLUMN mfa_recovery_codes');
            $db->query('ALTER TABLE users DROP COLUMN mfa_enabled');
            $db->query('ALTER TABLE users DROP COLUMN mfa_secret');
            $db->query('ALTER TABLE users DROP COLUMN email_verified_at');
        }
    }
}
