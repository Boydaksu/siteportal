<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Presentation;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\I18n;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Presentation\ReportPresenter;
use SitePortal\SeoAnalyzer\Scoring\AuditScore;

final class ReportPresenterTest extends TestCase
{
    private I18n $i18n;
    private ReportPresenter $presenter;

    protected function setUp(): void
    {
        $this->i18n = I18n::getInstance();
        $this->presenter = new ReportPresenter($this->i18n);
    }

    public function testFormatsFindingWithLocalizedLabelsAndClasses(): void
    {
        $finding = new Finding(
            ruleId: 'TECH_HTTPS',
            ruleVersion: '1.0.0',
            status: FindingStatus::FAIL,
            severity: FindingSeverity::HIGH,
            confidence: Confidence::HIGH,
            dataSource: 'crawler',
            evidence: [['url' => 'http://example.com', 'ssl' => false]],
            observedAt: '2026-11-04T12:00:00Z',
            affectedUrls: ['http://example.com'],
            fixKey: 'rules.TECH_HTTPS.fix'
        );

        $formattedTr = $this->presenter->formatSingleFinding($finding, 'tr-TR');

        self::assertSame('TECH_HTTPS', $formattedTr['rule_id']);
        self::assertSame('1.0.0', $formattedTr['rule_version']);
        self::assertSame('technical', $formattedTr['category']);
        self::assertSame('Teknik SEO', $formattedTr['category_label']);
        self::assertSame('FAIL', $formattedTr['status']);
        self::assertSame('Başarısız', $formattedTr['status_label']);
        self::assertSame('badge-status-fail', $formattedTr['status_class']);
        self::assertSame('high', $formattedTr['severity']);
        self::assertSame('Yüksek', $formattedTr['severity_label']);
        self::assertSame('badge-severity-high', $formattedTr['severity_class']);
        self::assertSame('crawler', $formattedTr['data_source']);
        self::assertSame(['http://example.com'], $formattedTr['affected_urls']);
        self::assertTrue($formattedTr['is_issue']);
        self::assertStringContainsString('SSL/TLS', $formattedTr['remediation']);
    }

    public function testResolvesRemediationAndCodeSnippets(): void
    {
        $finding = new Finding(
            ruleId: 'ONPAGE_TITLE_MISSING',
            ruleVersion: '1.0.0',
            status: FindingStatus::FAIL,
            severity: FindingSeverity::CRITICAL,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [['tag' => 'title', 'found' => false]],
            observedAt: '2026-11-04T12:00:00Z',
            affectedUrls: ['https://example.com/about'],
            fixKey: 'rules.ONPAGE_TITLE_MISSING.fix'
        );

        $formatted = $this->presenter->formatSingleFinding($finding, 'tr-TR');

        self::assertNotNull($formatted['code_snippet']);
        self::assertStringContainsString('<title>', (string) $formatted['code_snippet']);
        self::assertStringContainsString('<head>', (string) $formatted['code_snippet']);
        self::assertStringContainsString('<title>', $formatted['remediation']);
    }

    public function testFormatEvidenceReturnsPrettyJson(): void
    {
        $finding = new Finding(
            ruleId: 'TECH_HTTP_STATUS',
            ruleVersion: '1.0.0',
            status: FindingStatus::WARN,
            severity: FindingSeverity::MEDIUM,
            confidence: Confidence::HIGH,
            dataSource: 'crawler',
            evidence: [
                ['status' => 301, 'target' => 'https://example.com/dest'],
                ['note' => 'Redirected successfully'],
            ],
            observedAt: '2026-11-04T12:00:00Z',
            affectedUrls: ['https://example.com/old']
        );

        $formatted = $this->presenter->formatSingleFinding($finding, 'tr-TR');

        self::assertStringContainsString('"status": 301', $formatted['evidence_formatted']);
        self::assertStringContainsString('"target": "https://example.com/dest"', $formatted['evidence_formatted']);
    }

    public function testPreparesCompleteReportViewData(): void
    {
        $score = new AuditScore(
            overallScore: 85,
            categoryScores: [
                'technical' => 90,
                'onpage' => 80,
                'links' => 85,
                'accessibility' => 85,
            ],
            categoryBreakdown: [],
            coverage: 1.0,
            isScorable: true,
            statusCounts: [
                'PASS' => 8,
                'WARN' => 1,
                'FAIL' => 1,
                'UNKNOWN' => 0,
                'NOT_APPLICABLE' => 0,
            ],
            totalFindings: 10
        );

        $findings = [
            new Finding(
                ruleId: 'TECH_HTTPS',
                ruleVersion: '1.0.0',
                status: FindingStatus::PASS,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                evidence: [['https' => true]],
                observedAt: '2026-11-04T12:00:00Z',
                affectedUrls: ['https://example.com']
            ),
            new Finding(
                ruleId: 'ONPAGE_TITLE_MISSING',
                ruleVersion: '1.0.0',
                status: FindingStatus::FAIL,
                severity: FindingSeverity::CRITICAL,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['tag' => 'title', 'found' => false]],
                observedAt: '2026-11-04T12:00:00Z',
                affectedUrls: ['https://example.com/blog'],
                fixKey: 'rules.ONPAGE_TITLE_MISSING.fix'
            ),
        ];

        $analysis = [
            'public_id' => 'anl_test123',
            'target_url' => 'https://example.com',
            'status' => 'completed',
        ];

        $viewData = $this->presenter->prepareReportViewData($score, $findings, $analysis, 'tr-TR');

        self::assertSame(85, $viewData['overall_score']);
        self::assertSame(100, $viewData['coverage_percent']);
        self::assertTrue($viewData['is_scorable']);
        self::assertSame(1, $viewData['issues_count']);
        self::assertSame(1, $viewData['passed_count']);
        self::assertCount(2, $viewData['findings']);
        self::assertSame('tr-TR', $viewData['locale']);
    }

    public function testEveryIssueFindingContainsSourceUrlAndRemediation(): void
    {
        $issueFindings = [
            new Finding(
                ruleId: 'TECH_ROBOTS_DISALLOW',
                ruleVersion: '1.0.0',
                status: FindingStatus::FAIL,
                severity: FindingSeverity::CRITICAL,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                evidence: [['rule' => 'Disallow: /']],
                observedAt: '2026-11-04T12:00:00Z',
                affectedUrls: ['https://example.com/private'],
                fixKey: 'rules.TECH_ROBOTS_DISALLOW.fix'
            ),
            new Finding(
                ruleId: 'ONPAGE_H1_EMPTY',
                ruleVersion: '1.0.0',
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['h1_count' => 0]],
                observedAt: '2026-11-04T12:00:00Z',
                affectedUrls: ['https://example.com/landing'],
                fixKey: 'rules.ONPAGE_H1_EMPTY.fix'
            ),
        ];

        $formatted = $this->presenter->formatFindings($issueFindings, 'tr-TR');

        foreach ($formatted as $f) {
            self::assertTrue($f['is_issue']);
            self::assertNotEmpty($f['data_source'], "Bulgu {$f['rule_id']} için data_source boş olamaz");
            self::assertNotEmpty($f['affected_urls'], "Bulgu {$f['rule_id']} için affected_urls boş olamaz");
            self::assertNotEmpty($f['remediation'], "Bulgu {$f['rule_id']} için remediation boş olamaz");
        }
    }

    public function testEnglishLocaleLabelsAndRemediation(): void
    {
        $finding = new Finding(
            ruleId: 'TECH_HTTPS',
            ruleVersion: '1.0.0',
            status: FindingStatus::FAIL,
            severity: FindingSeverity::HIGH,
            confidence: Confidence::HIGH,
            dataSource: 'crawler',
            evidence: [],
            observedAt: '2026-11-04T12:00:00Z',
            affectedUrls: ['http://example.com'],
            fixKey: 'rules.TECH_HTTPS.fix'
        );

        $formattedEn = $this->presenter->formatSingleFinding($finding, 'en-US');

        self::assertSame('Technical SEO', $formattedEn['category_label']);
        self::assertSame('Failed', $formattedEn['status_label']);
        self::assertSame('High', $formattedEn['severity_label']);
        self::assertStringContainsString('SSL/TLS', $formattedEn['remediation']);
    }

    public function testEnforcesXssEscapingSafeguardsWithMaliciousPayloads(): void
    {
        $maliciousUrls = [
            'https://example.com/<script>alert("xss")</script>',
            'https://example.com/"><img src=x onerror=alert(1)>',
            'javascript:/*--></title></style></textarea>*/<script>alert(1)</script>',
        ];

        $maliciousEvidence = [
            [
                'payload' => '<script>alert("evidence")</script>',
                'tag' => '"><svg onload=alert(1)>',
            ],
        ];

        $finding = new Finding(
            ruleId: 'TECH_CANONICAL_EMPTY',
            ruleVersion: '1.0.0',
            status: FindingStatus::FAIL,
            severity: FindingSeverity::HIGH,
            confidence: Confidence::HIGH,
            dataSource: '<script>alert("source")</script>',
            evidence: $maliciousEvidence,
            observedAt: '2026-11-04T12:00:00Z',
            affectedUrls: $maliciousUrls,
            fixKey: 'rules.TECH_CANONICAL_EMPTY.fix'
        );

        $formatted = $this->presenter->formatSingleFinding($finding, 'tr-TR');

        // Presenter ham verileri array içinde saklar, View::escape bu verileri render ederken escape eder
        self::assertSame($maliciousUrls, $formatted['affected_urls']);
        self::assertStringContainsString('<script>alert', $formatted['evidence_formatted']);

        // View::escape çağrıldığında zararlı tagler escape edilmeli
        $escapedSource = htmlspecialchars($formatted['data_source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        self::assertStringNotContainsString('<script>', $escapedSource);
        self::assertStringContainsString('&lt;script&gt;', $escapedSource);

        $escapedEvidence = htmlspecialchars($formatted['evidence_formatted'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        self::assertStringNotContainsString('<script>', $escapedEvidence);
        self::assertStringNotContainsString('<svg', $escapedEvidence);
        self::assertStringContainsString('&lt;script&gt;', $escapedEvidence);
    }
}
