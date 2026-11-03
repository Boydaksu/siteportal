<?php

declare(strict_types=1);

namespace SitePortal\Database\Seeders;

use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\SeederInterface;

final class DevelopmentFixtureSeeder implements SeederInterface
{
    public function getName(): string
    {
        return 'DevelopmentFixtureSeeder (Gelistirme ve test ornek verileri)';
    }

    public function run(Connection $db): void
    {
        // 1. Seed sample user
        $userCheck = $db->fetch('SELECT id FROM users WHERE email = :email', ['email' => 'admin@siteportal.local']);
        if ($userCheck === null) {
            $db->query(
                'INSERT INTO users (email, password_hash, locale, status) VALUES (:email, :hash, :locale, :status)',
                [
                    'email' => 'admin@siteportal.local',
                    // DEMO/TEST bcrypt hash
                    'hash' => '$2y$12$e80V6dF97c2Jqm9Nl1L6/OaF/Dk5U3h2G4T5y6H7J8K9L0M1N2O3P',
                    'locale' => 'tr-TR',
                    'status' => 'active',
                ]
            );
        }

        // 2. Seed sample site
        $origin = 'https://example.com';
        $siteCheck = $db->fetch('SELECT id FROM sites WHERE normalized_origin = :origin', ['origin' => $origin]);
        $siteId = $siteCheck !== null ? (int) $siteCheck['id'] : null;

        if ($siteId === null) {
            $db->query(
                'INSERT INTO sites (normalized_origin, owner_user_id) VALUES (:origin, :owner)',
                [
                    'origin' => $origin,
                    'owner' => 1,
                ]
            );
            $siteId = (int) $db->lastInsertId();
        }

        // 3. Seed sample analysis
        $analysisPublicId = 'demo_analysis_001';
        $analysisCheck = $db->fetch(
            'SELECT id FROM analyses WHERE public_id = :public_id',
            ['public_id' => $analysisPublicId]
        );
        $analysisId = $analysisCheck !== null ? (int) $analysisCheck['id'] : null;

        if ($analysisId === null) {
            $insertAnalysisSql = 'INSERT INTO analyses (
                public_id, site_id, user_id, mode, status, ruleset_version, scoring_version, scope_count
            ) VALUES (
                :public_id, :site_id, :user_id, :mode, :status, :ruleset_version, :scoring_version, :scope_count
            )';
            $db->query(
                $insertAnalysisSql,
                [
                    'public_id' => $analysisPublicId,
                    'site_id' => $siteId,
                    'user_id' => 1,
                    'mode' => 'single_page',
                    'status' => 'completed',
                    'ruleset_version' => '1.0.0',
                    'scoring_version' => '1.0.0',
                    'scope_count' => 1,
                ]
            );
            $analysisId = (int) $db->lastInsertId();
        }

        // 4. Seed sample analysis_job (Q-002 queue table)
        $jobKey = 'job_demo_scan_001';
        $jobCheck = $db->fetch('SELECT id FROM analysis_jobs WHERE idempotency_key = :key', ['key' => $jobKey]);
        if ($jobCheck === null) {
            $payload = json_encode(['url' => 'https://example.com', 'scope' => 'single'], JSON_THROW_ON_ERROR);
            $insertJobSql = 'INSERT INTO analysis_jobs (
                analysis_id, idempotency_key, type, status, payload_json, attempts, max_attempts
            ) VALUES (
                :analysis_id, :key, :type, :status, :payload, :attempts, :max_attempts
            )';
            $db->query(
                $insertJobSql,
                [
                    'analysis_id' => $analysisId,
                    'key' => $jobKey,
                    'type' => 'scan_url',
                    'status' => 'pending',
                    'payload' => $payload,
                    'attempts' => 0,
                    'max_attempts' => 3,
                ]
            );
        }
    }
}
