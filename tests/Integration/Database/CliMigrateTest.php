<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Database;

use PHPUnit\Framework\TestCase;

final class CliMigrateTest extends TestCase
{
    private string $tempDbFile;

    protected function setUp(): void
    {
        $this->tempDbFile = sys_get_temp_dir() . '/siteportal_test_' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDbFile)) {
            @unlink($this->tempDbFile);
        }
    }

    public function testCliStatusUpSeedAndRollback(): void
    {
        $root = dirname(__DIR__, 3);
        $bin = $root . '/bin/migrate.php';
        $php = PHP_BINARY;

        $env = [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->tempDbFile,
        ];

        // 1. Status initially
        $outStatus = $this->runCommand([$php, $bin, 'status'], $env, $root);
        self::assertStringContainsString('BEKLİYOR', $outStatus);

        // 2. Up
        $outUp = $this->runCommand([$php, $bin, 'up'], $env, $root);
        self::assertStringContainsString('0001_create_core_tables', $outUp);

        // 3. Status after up
        $outStatusAfter = $this->runCommand([$php, $bin, 'status'], $env, $root);
        self::assertStringContainsString('UYGULANDI', $outStatusAfter);

        // 4. Idempotent up
        $outUp2 = $this->runCommand([$php, $bin, 'up'], $env, $root);
        self::assertStringContainsString('idempotent', $outUp2);

        // 5. Seed
        $outSeed = $this->runCommand([$php, $bin, 'seed'], $env, $root);
        self::assertStringContainsString('RuleRegistrySeeder', $outSeed);
        self::assertStringContainsString('DevelopmentFixtureSeeder', $outSeed);

        // 6. Rollback
        $outRollback = $this->runCommand([$php, $bin, 'rollback'], $env, $root);
        self::assertStringContainsString('0001_create_core_tables', $outRollback);

        // 7. Status after rollback
        $outStatusRoll = $this->runCommand([$php, $bin, 'status'], $env, $root);
        self::assertStringContainsString('BEKLİYOR', $outStatusRoll);
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $env
     */
    private function runCommand(array $command, array $env, string $cwd): string
    {
        $envVars = array_merge($_ENV, $env);
        $cmdStr = implode(' ', array_map('escapeshellarg', $command));

        $process = proc_open($cmdStr, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $cwd, $envVars);

        if (!is_resource($process)) {
            self::fail('Komut calistirilamadi: ' . $cmdStr);
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        self::assertSame(0, $exitCode, "Komut basarisiz: {$cmdStr}\nStderr: {$stderr}\nStdout: {$stdout}");

        return $stdout;
    }
}
