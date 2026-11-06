<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;

final class CreateOauthTables implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'OAuth baglantilari (sifreli refresh tokenlar) ve PKCE durum (state) tablolari';
    }

    public function up(Connection $db): void
    {
        $idType = $db->isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $tableOptions = $db->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timeType = $db->isSqlite() ? 'DATETIME' : 'TIMESTAMP';

        // 1. oauth_connections tablosu
        $db->query("CREATE TABLE IF NOT EXISTS oauth_connections (
            id {$idType},
            user_id INT NOT NULL,
            site_id INT NULL,
            provider VARCHAR(50) NOT NULL DEFAULT 'google',
            provider_user_id VARCHAR(255) NULL,
            provider_email VARCHAR(255) NULL,
            access_token_encrypted TEXT NOT NULL,
            refresh_token_encrypted TEXT NOT NULL,
            token_type VARCHAR(50) NOT NULL DEFAULT 'Bearer',
            scopes TEXT NOT NULL,
            expires_at {$timeType} NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            revoked_at {$timeType} NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            updated_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 2. oauth_states tablosu (PKCE & Replay korumasi)
        $db->query("CREATE TABLE IF NOT EXISTS oauth_states (
            id {$idType},
            state VARCHAR(64) NOT NULL,
            user_id INT NOT NULL,
            site_id INT NULL,
            provider VARCHAR(50) NOT NULL DEFAULT 'google',
            code_verifier VARCHAR(128) NOT NULL,
            scopes TEXT NOT NULL,
            expires_at {$timeType} NOT NULL,
            used_at {$timeType} NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // Indeksler
        if ($db->isSqlite()) {
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_oauth_conn_user_provider '
                . 'ON oauth_connections(user_id, provider, status)'
            );
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_oauth_conn_site '
                . 'ON oauth_connections(site_id)'
            );
            $db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS uk_oauth_states_state '
                . 'ON oauth_states(state)'
            );
            $db->query(
                'CREATE INDEX IF NOT EXISTS idx_oauth_states_user '
                . 'ON oauth_states(user_id, state)'
            );
        } else {
            $db->query(
                'ALTER TABLE oauth_connections ADD INDEX idx_oauth_conn_user_provider (user_id, provider, status)'
            );
            $db->query(
                'ALTER TABLE oauth_connections ADD INDEX idx_oauth_conn_site (site_id)'
            );
            $db->query(
                'ALTER TABLE oauth_states ADD UNIQUE INDEX uk_oauth_states_state (state)'
            );
            $db->query(
                'ALTER TABLE oauth_states ADD INDEX idx_oauth_states_user (user_id, state)'
            );
        }
    }

    public function down(Connection $db): void
    {
        $db->query('DROP TABLE IF EXISTS oauth_states');
        $db->query('DROP TABLE IF EXISTS oauth_connections');
    }
}
