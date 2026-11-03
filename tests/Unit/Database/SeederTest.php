<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Database\SeederRunner;

final class SeederTest extends TestCase
{
    private Connection $db;
    private MigrationRunner $migrationRunner;
    private SeederRunner $seederRunner;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->migrationRunner = new MigrationRunner($this->db);
        $this->migrationRunner->migrate();
        $this->seederRunner = new SeederRunner($this->db);
    }

    public function testRuleRegistrySeederSeedsAll84Rules(): void
    {
        $this->seederRunner->run();

        $countRow = $this->db->fetch('SELECT COUNT(*) as cnt FROM rules');
        self::assertIsArray($countRow);
        self::assertSame(84, (int) $countRow['cnt']);

        // Check specific rule
        $rule = $this->db->fetch('SELECT * FROM rules WHERE rule_id = :id', ['id' => 'TECH_HTTP_STATUS']);
        self::assertIsArray($rule);
        self::assertSame('1.0', $rule['rule_version']);
        self::assertSame('technical', $rule['category']);
        self::assertSame('high', $rule['severity']);
        self::assertSame('S1', $rule['reference_id']);
    }

    public function testSeedersAreIdempotent(): void
    {
        // First run
        $this->seederRunner->run();
        $row1 = $this->db->fetch('SELECT COUNT(*) as cnt FROM rules');
        self::assertIsArray($row1);
        $count1 = (int) $row1['cnt'];

        // Second run
        $this->seederRunner->run();
        $row2 = $this->db->fetch('SELECT COUNT(*) as cnt FROM rules');
        self::assertIsArray($row2);
        $count2 = (int) $row2['cnt'];

        self::assertSame(84, $count1);
        self::assertSame(84, $count2);
    }

    public function testDevelopmentFixtureSeederSeedsSampleEntities(): void
    {
        $this->seederRunner->run();

        // 1. User
        $user = $this->db->fetch('SELECT * FROM users WHERE email = :email', ['email' => 'admin@siteportal.local']);
        self::assertIsArray($user);
        self::assertSame('tr-TR', $user['locale']);

        // 2. Site
        $site = $this->db->fetch(
            'SELECT * FROM sites WHERE normalized_origin = :origin',
            ['origin' => 'https://example.com']
        );
        self::assertIsArray($site);

        // 3. Analysis
        $analysis = $this->db->fetch(
            'SELECT * FROM analyses WHERE public_id = :public_id',
            ['public_id' => 'demo_analysis_001']
        );
        self::assertIsArray($analysis);
        self::assertSame('completed', $analysis['status']);

        // 4. Analysis job (Q-002 queue table)
        $job = $this->db->fetch(
            'SELECT * FROM analysis_jobs WHERE idempotency_key = :key',
            ['key' => 'job_demo_scan_001']
        );
        self::assertIsArray($job);
        self::assertSame('scan_url', $job['type']);
        self::assertSame('pending', $job['status']);
    }
}
