<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Analysis\AnalysisLifecycleService;
use SitePortal\Core\Analysis\Exceptions\AnalysisRateLimitedException;
use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\Core\Security\IpValidator;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class AnalysisLifecycleTest extends TestCase
{
    private Connection $db;
    private MigrationRunner $runner;
    private Config $config;
    private MockDnsResolver $dnsResolver;
    private UrlValidator $urlValidator;
    private AnalysisLifecycleService $service;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->runner = new MigrationRunner($this->db);
        $this->runner->migrate();

        $this->dnsResolver = new MockDnsResolver([
            'example.com' => ['93.184.216.34'],
            'idempotent-test.com' => ['93.184.216.34'],
            'progress-test.org' => ['93.184.216.34'],
            'failed-test.org' => ['93.184.216.34'],
            'evil.internal' => ['10.0.0.1'],
            'localhost' => ['127.0.0.1'],
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->dnsResolver->setRecord("example{$i}.org", ['93.184.216.34']);
        }

        $policy = new UrlSecurityPolicy();
        $ipValidator = new IpValidator($policy);
        $this->urlValidator = new UrlValidator($policy, $ipValidator, $this->dnsResolver);

        $this->config = Config::getInstance();
        $this->service = new AnalysisLifecycleService(
            db: $this->db,
            urlValidator: $this->urlValidator,
            config: $this->config
        );
    }

    public function testStartAnalysisWithValidPublicUrlInsertsAnalysisAndDispatchesJob(): void
    {
        $result = $this->service->startAnalysis(
            url: 'https://example.com',
            clientIp: '198.51.100.1',
            mode: 'single_page',
            locale: 'tr-TR'
        );

        self::assertArrayHasKey('public_id', $result);
        self::assertArrayHasKey('target_url', $result);
        self::assertSame('https://example.com/', $result['target_url']);
        self::assertSame('pending', $result['status']);
        self::assertSame(5, $result['progress_percent']);

        $publicId = (string) $result['public_id'];
        self::assertMatchesRegularExpression('/^anl_[a-f0-9]{24}$/', $publicId);

        // Verify analyses table record
        $analysis = $this->db->fetch(
            'SELECT * FROM analyses WHERE public_id = :id',
            ['id' => $publicId]
        );
        self::assertNotNull($analysis);
        self::assertSame('https://example.com/', $analysis['target_url']);
        self::assertSame('pending', $analysis['status']);
        self::assertSame(5, (int) $analysis['progress_percent']);

        // Verify analysis_jobs record exists
        $job = $this->db->fetch(
            'SELECT * FROM analysis_jobs WHERE analysis_id = :id',
            ['id' => (int) $analysis['id']]
        );
        self::assertNotNull($job);
        self::assertSame('single_page_audit', $job['type']);
        self::assertSame('pending', $job['status']);

        // Verify sites record exists
        $site = $this->db->fetch(
            'SELECT * FROM sites WHERE normalized_origin = :origin',
            ['origin' => 'https://example.com']
        );
        self::assertNotNull($site);
        self::assertSame('https://example.com', $site['normalized_origin']);
    }

    public function testStartAnalysisRejectsInvalidUrl(): void
    {
        $this->expectException(InvalidUrlException::class);
        $this->service->startAnalysis('not-a-valid-url-format');
    }

    public function testStartAnalysisBlocksSsrfPrivateUrls(): void
    {
        $blockedUrls = [
            'http://127.0.0.1/admin',
            'http://localhost:8080',
            'http://169.254.169.254/latest/meta-data',
            'http://10.0.0.1/internal',
            'http://192.168.1.1/router',
            'http://evil.internal/secret',
        ];

        foreach ($blockedUrls as $url) {
            $blocked = false;
            try {
                $this->service->startAnalysis($url, clientIp: '198.51.100.2');
            } catch (BlockedUrlException | InvalidUrlException $e) {
                $blocked = true;
                self::assertNotEmpty($e->getMessage());
            }

            self::assertTrue($blocked, "SSRF URL '{$url}' should have been blocked.");
        }
    }

    public function testStartAnalysisEnforcesAnonymousRateLimit(): void
    {
        $clientIp = '203.0.113.88';

        // Limit is 5 attempts per 600 seconds
        for ($i = 0; $i < 5; $i++) {
            $result = $this->service->startAnalysis(
                url: "https://example{$i}.org",
                clientIp: $clientIp
            );
            self::assertSame('pending', $result['status']);
        }

        // 6th attempt must throw AnalysisRateLimitedException
        $this->expectException(AnalysisRateLimitedException::class);
        $this->service->startAnalysis(
            url: 'https://example-blocked.org',
            clientIp: $clientIp
        );
    }

    public function testStartAnalysisIdempotency(): void
    {
        $key = 'idem-unique-key-12345';
        $res1 = $this->service->startAnalysis(
            url: 'https://idempotent-test.com',
            clientIp: '198.51.100.99',
            idempotencyKey: $key
        );

        $res2 = $this->service->startAnalysis(
            url: 'https://idempotent-test.com',
            clientIp: '198.51.100.99',
            idempotencyKey: $key
        );

        self::assertSame($res1['public_id'], $res2['public_id']);
        self::assertSame($res1['target_url'], $res2['target_url']);

        // Only 1 record in analyses
        $count = $this->db->fetch(
            'SELECT COUNT(*) as cnt FROM analyses WHERE target_url = :url',
            ['url' => 'https://idempotent-test.com/']
        );
        self::assertSame(1, (int) ($count['cnt'] ?? 0));
    }

    public function testGetProgressDerivesStagesStrictlyFromDatabaseState(): void
    {
        $created = $this->service->startAnalysis(
            url: 'https://progress-test.org',
            clientIp: '198.51.100.5'
        );
        $publicId = (string) $created['public_id'];

        // 1. Pending State
        $progressPending = $this->service->getProgress($publicId);
        self::assertNotNull($progressPending);
        self::assertSame('pending', $progressPending['status']);
        self::assertSame(5, $progressPending['progress_percent']);
        self::assertCount(4, $progressPending['stages']);
        self::assertSame('completed', $progressPending['stages'][0]['state']); // queued
        self::assertSame('pending', $progressPending['stages'][1]['state']); // fetching
        self::assertSame('pending', $progressPending['stages'][2]['state']); // rules
        self::assertSame('pending', $progressPending['stages'][3]['state']); // reporting

        // 2. Crawling State
        $this->service->updateStatus($publicId, 'crawling');
        $progressCrawling = $this->service->getProgress($publicId);
        self::assertNotNull($progressCrawling);
        self::assertSame('crawling', $progressCrawling['status']);
        self::assertSame(35, $progressCrawling['progress_percent']);
        self::assertSame('completed', $progressCrawling['stages'][0]['state']);
        self::assertSame('in_progress', $progressCrawling['stages'][1]['state']);

        // 3. Analyzing State
        $this->service->updateStatus($publicId, 'analyzing');
        $progressAnalyzing = $this->service->getProgress($publicId);
        self::assertNotNull($progressAnalyzing);
        self::assertSame('analyzing', $progressAnalyzing['status']);
        self::assertSame(75, $progressAnalyzing['progress_percent']);
        self::assertSame('completed', $progressAnalyzing['stages'][0]['state']);
        self::assertSame('completed', $progressAnalyzing['stages'][1]['state']);
        self::assertSame('in_progress', $progressAnalyzing['stages'][2]['state']);

        // 4. Completed State
        $this->service->updateStatus($publicId, 'completed');
        $progressCompleted = $this->service->getProgress($publicId);
        self::assertNotNull($progressCompleted);
        self::assertSame('completed', $progressCompleted['status']);
        self::assertSame(100, $progressCompleted['progress_percent']);
        foreach ($progressCompleted['stages'] as $stage) {
            self::assertSame('completed', $stage['state']);
        }

        // 5. Failed State
        $failedCreated = $this->service->startAnalysis(
            url: 'https://failed-test.org',
            clientIp: '198.51.100.6'
        );
        $failedId = (string) $failedCreated['public_id'];
        $this->service->updateStatus(
            $failedId,
            'failed',
            errorCode: 'HTTP_TIMEOUT',
            errorMessage: 'Hedef sunucu zaman aşımına uğradı.'
        );

        $progressFailed = $this->service->getProgress($failedId);
        self::assertNotNull($progressFailed);
        self::assertSame('failed', $progressFailed['status']);
        self::assertSame(100, $progressFailed['progress_percent']);
        self::assertSame('HTTP_TIMEOUT', $progressFailed['error_code']);
        self::assertSame('Hedef sunucu zaman aşımına uğradı.', $progressFailed['error_message']);
    }

    public function testGetAnalysisReturnsNullForNonExistentId(): void
    {
        $nonExistent = $this->service->getAnalysis('00000000000000000000000000000000');
        self::assertNull($nonExistent);

        $progressNull = $this->service->getProgress('00000000000000000000000000000000');
        self::assertNull($progressNull);
    }
}
