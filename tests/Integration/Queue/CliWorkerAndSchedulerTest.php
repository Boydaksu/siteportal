<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Queue;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Queue\DatabaseQueue;

final class CliWorkerAndSchedulerTest extends TestCase
{
    private string $tempDbFile;
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
        $this->tempDbFile = sys_get_temp_dir() . '/siteportal_cli_queue_' . bin2hex(random_bytes(6)) . '.sqlite';

        $db = Connection::create(['driver' => 'sqlite', 'database' => $this->tempDbFile]);
        $runner = new MigrationRunner($db);
        $runner->migrate();

        $queue = new DatabaseQueue($db);
        $queue->push('test_job', ['msg' => 'cli_hello'], 'cli_k1');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDbFile)) {
            @unlink($this->tempDbFile);
        }
    }

    public function testWorkerCliHelpAndOnce(): void
    {
        $php = PHP_BINARY;
        $bin = $this->root . '/bin/worker.php';
        $env = [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->tempDbFile,
        ];

        // 1. --help
        $helpOut = $this->runCommand([$php, $bin, '--help'], $env);
        self::assertStringContainsString('Kullanım: php bin/worker.php', $helpOut);

        // 2. --once (1 işi çalıştırıp çıkmalı)
        $onceOut = $this->runCommand([$php, $bin, '--once'], $env);
        self::assertStringContainsString('1 iş başarıyla işlendi', $onceOut);

        // 3. Kuyruk boşken --once
        $emptyOut = $this->runCommand([$php, $bin, '--once'], $env);
        self::assertStringContainsString('iş bulunamadı', $emptyOut);
    }

    public function testSchedulerCliHelpAndJson(): void
    {
        $php = PHP_BINARY;
        $bin = $this->root . '/bin/scheduler.php';
        $env = [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->tempDbFile,
        ];

        // 1. --help
        $helpOut = $this->runCommand([$php, $bin, '--help'], $env);
        self::assertStringContainsString('Kullanım: php bin/scheduler.php', $helpOut);

        // 2. --watchdog-only --json
        $jsonOut = $this->runCommand([$php, $bin, '--watchdog-only', '--json'], $env);
        /** @var array<string, mixed>|null $data */
        $data = json_decode($jsonOut, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('recovered_stale', $data);
        self::assertArrayHasKey('jobs_processed', $data);
        self::assertArrayHasKey('duration_seconds', $data);
        self::assertArrayHasKey('status_counts', $data);

        // 3. Normal text output
        $textOut = $this->runCommand([$php, $bin, '--max-jobs=1'], $env);
        self::assertStringContainsString('SitePortal Zamanlayıcı Raporu', $textOut);
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $env
     */
    private function runCommand(array $command, array $env): string
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $this->root, array_merge($_ENV, $env));

        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        self::assertSame(0, $exitCode, "Komut basarisiz oldu. Stderr: {$stderr}");

        return (string) $stdout;
    }
}
