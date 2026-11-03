<?php

declare(strict_types=1);

namespace SitePortal\Database\Migrations;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationInterface;

final class CreateCoreTables implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Çekirdek kullanıcı, site, kural, analiz, kuyruk (analysis_jobs), sayfa ve bulgu tabloları';
    }

    public function up(Connection $db): void
    {
        $idType = $db->isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $tableOptions = $db->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timeType = $db->isSqlite() ? 'DATETIME' : 'TIMESTAMP';

        // 1. users
        $db->query("CREATE TABLE IF NOT EXISTS users (
            id {$idType},
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            locale VARCHAR(10) NOT NULL DEFAULT 'tr-TR',
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            updated_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 2. sites
        $db->query("CREATE TABLE IF NOT EXISTS sites (
            id {$idType},
            normalized_origin VARCHAR(255) NOT NULL,
            owner_user_id INT NULL,
            verified_at {$timeType} NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 3. rules
        $db->query("CREATE TABLE IF NOT EXISTS rules (
            id {$idType},
            rule_id VARCHAR(100) NOT NULL,
            rule_version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            category VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            title_tr VARCHAR(255) NOT NULL,
            title_en VARCHAR(255) NOT NULL,
            reference_id VARCHAR(50) NOT NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT uk_rules_id_ver UNIQUE (rule_id, rule_version)
        ){$tableOptions}");

        // 4. analyses
        $db->query("CREATE TABLE IF NOT EXISTS analyses (
            id {$idType},
            public_id VARCHAR(64) NOT NULL UNIQUE,
            site_id INT NULL,
            user_id INT NULL,
            mode VARCHAR(50) NOT NULL DEFAULT 'single_page',
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            ruleset_version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            scoring_version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            scope_count INT NOT NULL DEFAULT 1,
            started_at {$timeType} NULL,
            completed_at {$timeType} NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 5. analysis_jobs (Queue table - Q-002)
        $db->query("CREATE TABLE IF NOT EXISTS analysis_jobs (
            id {$idType},
            analysis_id INT NULL,
            idempotency_key VARCHAR(128) NOT NULL UNIQUE,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            payload_json TEXT NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 3,
            leased_until {$timeType} NULL,
            available_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            worker_id VARCHAR(100) NULL,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            updated_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 6. pages
        $db->query("CREATE TABLE IF NOT EXISTS pages (
            id {$idType},
            analysis_id INT NOT NULL,
            normalized_url_hash VARCHAR(64) NOT NULL,
            url TEXT NOT NULL,
            crawl_status VARCHAR(50) NOT NULL DEFAULT 'pending',
            http_status INT NULL,
            depth INT NOT NULL DEFAULT 0,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT uk_pages_analysis_url UNIQUE (analysis_id, normalized_url_hash)
        ){$tableOptions}");

        // 7. findings
        $db->query("CREATE TABLE IF NOT EXISTS findings (
            id {$idType},
            analysis_id INT NOT NULL,
            rule_id VARCHAR(100) NOT NULL,
            rule_version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            status VARCHAR(20) NOT NULL DEFAULT 'FAIL',
            severity VARCHAR(20) NOT NULL DEFAULT 'MAJOR',
            confidence VARCHAR(20) NOT NULL DEFAULT 'HIGH',
            data_source VARCHAR(50) NOT NULL DEFAULT 'crawler',
            evidence_json TEXT NULL,
            affected_urls_json TEXT NULL,
            observed_at {$timeType} DEFAULT CURRENT_TIMESTAMP,
            created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");
    }

    public function down(Connection $db): void
    {
        $db->query('DROP TABLE IF EXISTS findings');
        $db->query('DROP TABLE IF EXISTS pages');
        $db->query('DROP TABLE IF EXISTS analysis_jobs');
        $db->query('DROP TABLE IF EXISTS analyses');
        $db->query('DROP TABLE IF EXISTS rules');
        $db->query('DROP TABLE IF EXISTS sites');
        $db->query('DROP TABLE IF EXISTS users');
    }
}
