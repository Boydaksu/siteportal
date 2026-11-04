<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Observability;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Observability\Logger;

final class LoggerTest extends TestCase
{
    private string $tempLogPath;

    protected function setUp(): void
    {
        $this->tempLogPath = sys_get_temp_dir() . '/siteportal_test_' . bin2hex(random_bytes(6)) . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempLogPath)) {
            @unlink($this->tempLogPath);
        }
    }

    public function testLoggerEnrichesRecordWithCorrelationIdAndRequestId(): void
    {
        $logger = Logger::createInMemory('queue')
            ->withCorrelationId('corr_test_123')
            ->withRequestId('req_test_456');

        $logger->info('Worker processed job', ['job_id' => 42]);

        $records = $logger->getBuffer();
        self::assertCount(1, $records);

        $rec = $records[0];
        self::assertSame('info', $rec['level']);
        self::assertSame('queue', $rec['channel']);
        self::assertSame('Worker processed job', $rec['message']);
        self::assertSame('corr_test_123', $rec['correlation_id']);
        self::assertSame('req_test_456', $rec['request_id']);
        self::assertSame(42, $rec['context']['job_id']);
    }

    public function testLoggerContextOverridesInstanceCorrelationAndRequestId(): void
    {
        $logger = Logger::createInMemory('app')
            ->withCorrelationId('corr_default')
            ->withRequestId('req_default');

        $logger->warning('Job warned', [
            'correlation_id' => 'corr_custom_override',
            'request_id' => 'req_custom_override',
        ]);

        $records = $logger->getBuffer();
        self::assertCount(1, $records);
        self::assertSame('corr_custom_override', $records[0]['correlation_id']);
        self::assertSame('req_custom_override', $records[0]['request_id']);
    }

    public function testLoggerFiltersOutMessagesBelowMinLevel(): void
    {
        $logger = Logger::createInMemory('api', Logger::WARNING);

        $logger->debug('Debug message should be ignored');
        $logger->info('Info message should be ignored');
        $logger->warning('Warning message must be kept');
        $logger->error('Error message must be kept');

        $records = $logger->getBuffer();
        self::assertCount(2, $records);
        self::assertSame('warning', $records[0]['level']);
        self::assertSame('error', $records[1]['level']);
    }

    public function testLoggerRedactsSecretsAutomatically(): void
    {
        $logger = Logger::createInMemory();

        $logger->info('User login attempted with Bearer token_secret_123', [
            'password' => 'mySuperSecretPassword',
            'token' => 'jwt_token_secret',
            'email' => 'admin@siteportal.local',
        ]);

        $records = $logger->getBuffer();
        self::assertCount(1, $records);

        $rec = $records[0];
        self::assertStringNotContainsString('token_secret_123', (string) $rec['message']);
        self::assertSame('User login attempted with Bearer [REDACTED]', $rec['message']);
        self::assertSame('[REDACTED]', $rec['context']['password']);
        self::assertSame('[REDACTED]', $rec['context']['token']);
        self::assertSame('admin@siteportal.local', $rec['context']['email']);
    }

    public function testLoggerWritesToFileFormattedJson(): void
    {
        $logger = new Logger(
            channel: 'audit',
            minLevel: Logger::INFO,
            filePath: $this->tempLogPath,
            format: 'json'
        );

        $logger->info('File write test', ['user' => 'tester']);

        self::assertFileExists($this->tempLogPath);
        $content = (string) file_get_contents($this->tempLogPath);
        self::assertNotEmpty($content);

        $decoded = json_decode(trim($content), true);
        self::assertIsArray($decoded);
        self::assertSame('audit', $decoded['channel']);
        self::assertSame('info', $decoded['level']);
        self::assertSame('File write test', $decoded['message']);
        self::assertSame('tester', $decoded['context']['user']);
    }

    public function testLoggerWritesLineFormatWithCustomWriter(): void
    {
        $logger = new Logger(
            channel: 'crawler',
            minLevel: Logger::DEBUG,
            filePath: null,
            format: 'line'
        );

        $output = '';
        $logger->setCustomWriter(function (string $formatted) use (&$output): void {
            $output = $formatted;
        });

        $logger->withCorrelationId('corr_001')->withRequestId('req_002')->debug('Fetching robots.txt');

        self::assertStringContainsString('[crawler.DEBUG] [corr_001] [req_002]: Fetching robots.txt', $output);
    }
}
