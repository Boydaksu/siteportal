<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Domain;

use PHPUnit\Framework\TestCase;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;

final class FindingTest extends TestCase
{
    public function testFindingCreationAndGetters(): void
    {
        $evidence = [['status_code' => 200, 'url' => 'https://example.com']];
        $affected = ['https://example.com'];
        $now = '2026-11-04T12:00:00Z';

        $finding = new Finding(
            ruleId: 'TECH_HTTP_STATUS',
            ruleVersion: '1.0',
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'http',
            evidence: $evidence,
            observedAt: $now,
            affectedUrls: $affected,
            fixKey: 'rules.TECH_HTTP_STATUS.fix',
            messageKey: 'rules.TECH_HTTP_STATUS.message'
        );

        $this->assertSame('TECH_HTTP_STATUS', $finding->getRuleId());
        $this->assertSame('1.0', $finding->getRuleVersion());
        $this->assertSame(FindingStatus::PASS, $finding->getStatus());
        $this->assertSame(FindingSeverity::INFO, $finding->getSeverity());
        $this->assertSame(Confidence::HIGH, $finding->getConfidence());
        $this->assertSame('http', $finding->getDataSource());
        $this->assertSame($evidence, $finding->getEvidence());
        $this->assertSame($now, $finding->getObservedAt());
        $this->assertSame($affected, $finding->getAffectedUrls());
        $this->assertSame('rules.TECH_HTTP_STATUS.fix', $finding->getFixKey());
        $this->assertSame('rules.TECH_HTTP_STATUS.message', $finding->getMessageKey());

        $array = $finding->toArray();
        $this->assertSame('TECH_HTTP_STATUS', $array['rule_id']);
        $this->assertSame('1.0', $array['rule_version']);
        $this->assertSame('PASS', $array['status']);
        $this->assertSame('info', $array['severity']);
        $this->assertSame('high', $array['confidence']);
        $this->assertSame('http', $array['data_source']);
        $this->assertSame($evidence, $array['evidence']);
        $this->assertSame($now, $array['observed_at']);
        $this->assertSame($affected, $array['affected_urls']);
        $this->assertSame('rules.TECH_HTTP_STATUS.fix', $array['fix_key']);
        $this->assertSame('rules.TECH_HTTP_STATUS.message', $array['message_key']);
    }

    public function testFindingSchemaContractCompliance(): void
    {
        $finding = new Finding(
            ruleId: 'TECH_TEST',
            ruleVersion: '1.0',
            status: FindingStatus::FAIL,
            severity: FindingSeverity::HIGH,
            confidence: Confidence::MEDIUM,
            dataSource: 'crawler',
            evidence: [['item' => 'val']],
            affectedUrls: ['https://example.com/test']
        );

        $data = $finding->toArray();

        // Check required fields from examples/finding.schema.json
        $requiredKeys = [
            'rule_id',
            'rule_version',
            'status',
            'severity',
            'confidence',
            'data_source',
            'evidence',
            'observed_at',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Finding missing required field: {$key}");
        }

        // Q-001 assertion: data_source is string, evidence is array of objects
        $this->assertIsString($data['data_source']);
        $this->assertIsArray($data['evidence']);
        foreach ($data['evidence'] as $ev) {
            $this->assertIsArray($ev);
        }

        $this->assertContains($data['status'], ['PASS', 'WARN', 'FAIL', 'UNKNOWN', 'NOT_APPLICABLE']);
        $this->assertContains($data['severity'], ['critical', 'high', 'medium', 'low', 'info']);
        $this->assertContains($data['confidence'], ['high', 'medium', 'low', 'unknown']);
    }

    public function testDefaultObservedAtIsIso8601(): void
    {
        $finding = new Finding(
            ruleId: 'TECH_TEST',
            ruleVersion: '1.0',
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'test'
        );

        $date = $finding->getObservedAt();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $date);
    }
}
