<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;

final class MigrationRunnerTest extends TestCase
{
    private Connection $db;
    private MigrationRunner $runner;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->runner = new MigrationRunner($this->db);
    }

    public function testMigrateRunsOnEmptyDatabase(): void
    {
        $applied = $this->runner->migrate();

        self::assertNotEmpty($applied);
        self::assertContains('0001_create_core_tables', $applied);

        // Verify tables exist
        $tables = $this->db->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = array_column($tables, 'name');

        self::assertContains('migrations', $tableNames);
        self::assertContains('users', $tableNames);
        self::assertContains('sites', $tableNames);
        self::assertContains('rules', $tableNames);
        self::assertContains('analyses', $tableNames);
        self::assertContains('analysis_jobs', $tableNames);
        self::assertContains('pages', $tableNames);
        self::assertContains('findings', $tableNames);
    }

    public function testMigrateIsIdempotent(): void
    {
        $firstRun = $this->runner->migrate();
        self::assertNotEmpty($firstRun);

        // Second run must apply 0 pending migrations and not fail
        $secondRun = $this->runner->migrate();
        self::assertSame([], $secondRun);
    }

    public function testRollbackReversesMigrations(): void
    {
        $this->runner->migrate();

        $rolledBack = $this->runner->rollback();
        self::assertNotEmpty($rolledBack);
        self::assertContains('0001_create_core_tables', $rolledBack);

        // Verify core tables were dropped
        $tables = $this->db->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = array_column($tables, 'name');

        self::assertNotContains('users', $tableNames);
        self::assertNotContains('sites', $tableNames);
        self::assertNotContains('analysis_jobs', $tableNames);
        self::assertNotContains('analyses', $tableNames);

        // Migrations table should be empty
        $rows = $this->db->fetchAll('SELECT * FROM migrations');
        self::assertEmpty($rows);
    }

    public function testCanReMigrateAfterRollback(): void
    {
        $this->runner->migrate();
        $this->runner->rollback();

        $reMigrated = $this->runner->migrate();
        self::assertNotEmpty($reMigrated);
        self::assertContains('0001_create_core_tables', $reMigrated);

        $tables = $this->db->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = array_column($tables, 'name');
        self::assertContains('analysis_jobs', $tableNames);
    }

    public function testStatusReportsCorrectState(): void
    {
        $statusBefore = $this->runner->status();
        self::assertNotEmpty($statusBefore);
        self::assertFalse($statusBefore[0]['applied']);

        $this->runner->migrate();

        $statusAfter = $this->runner->status();
        self::assertNotEmpty($statusAfter);
        self::assertTrue($statusAfter[0]['applied']);
        self::assertSame(1, $statusAfter[0]['batch']);
        self::assertNotEmpty($statusAfter[0]['description']);
    }
}
