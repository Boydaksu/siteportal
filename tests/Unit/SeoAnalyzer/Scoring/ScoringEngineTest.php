<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Scoring;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Engine\AuditEngine;
use SitePortal\SeoAnalyzer\Engine\V1RuleRegistryFactory;
use SitePortal\SeoAnalyzer\Scoring\AuditScore;
use SitePortal\SeoAnalyzer\Scoring\ScoringEngine;

final class ScoringEngineTest extends TestCase
{
    private function createFinding(
        string $ruleId,
        FindingStatus $status,
        FindingSeverity $severity = FindingSeverity::MEDIUM
    ): Finding {
        return new Finding(
            ruleId: $ruleId,
            ruleVersion: '1.0',
            status: $status,
            severity: $severity,
            confidence: Confidence::HIGH,
            dataSource: 'test',
            evidence: [['test' => true]],
            affectedUrls: ['https://example.com/']
        );
    }

    private function createSampleSnapshot(): PageSnapshot
    {
        $response = new HttpResponse(
            statusCode: 200,
            headers: ['content-type' => 'text/html; charset=UTF-8'],
            body: '<html><head><title>Test Title</title></head><body><h1>H1</h1></body></html>',
            effectiveUrl: 'https://example.com/'
        );

        $doc = new ParsedDocument(
            title: 'Test Title',
            titleCount: 1,
            allTitles: ['Test Title'],
            metaDescription: 'Meta description text here.',
            metaDescriptionCount: 1,
            allMetaDescriptions: ['Meta description text here.'],
            lang: 'tr',
            canonical: 'https://example.com/',
            canonicalCount: 1,
            allCanonicals: ['https://example.com/'],
            headings: [['tag' => 'h1', 'level' => 1, 'text' => 'H1', 'index' => 0]],
            robotsDirectives: ['index' => true, 'follow' => true],
            openGraph: ['title' => 'Test Title', 'image' => 'https://example.com/img.png'],
            twitterCards: [],
            images: [],
            links: [],
            charset: 'UTF-8',
            rawByteLength: 200,
            textLength: 100,
            wordCount: 20,
            parseTimeMs: 1.0,
            isMalformed: false
        );

        return new PageSnapshot(
            requestedUrl: 'https://example.com/',
            finalUrl: 'https://example.com/',
            httpResponse: $response,
            parsedDocument: $doc
        );
    }

    // -------------------------------------------------------------------------
    // 1. Factory & AuditEngine Integration
    // -------------------------------------------------------------------------

    public function testV1RuleRegistryFactoryRegistersAll32Rules(): void
    {
        $registry = V1RuleRegistryFactory::createRegistry();

        self::assertSame(32, $registry->count());
        self::assertCount(32, $registry->all());

        // Verify representative rules across technical, onpage, links, accessibility
        self::assertTrue($registry->has('TECH_HTTP_STATUS'));
        self::assertTrue($registry->has('TECH_CANONICAL_MISSING'));
        self::assertTrue($registry->has('ONPAGE_TITLE_MISSING'));
        self::assertTrue($registry->has('ONPAGE_H1_EMPTY'));
        self::assertTrue($registry->has('LINK_ANCHOR_EMPTY'));
        self::assertTrue($registry->has('LINK_REL_NOFOLLOW'));
        self::assertTrue($registry->has('A11Y_IMAGE_ALT'));
    }

    public function testAuditEngineExecutesAllRulesDeterministically(): void
    {
        $registry = V1RuleRegistryFactory::createRegistry();
        $engine = new AuditEngine($registry);

        $snapshot = $this->createSampleSnapshot();
        $findings = $engine->audit($snapshot);

        self::assertCount(32, $findings);

        // Every finding must have valid rule ID and status
        foreach ($findings as $f) {
            self::assertNotEmpty($f->getRuleId());
            self::assertInstanceOf(FindingStatus::class, $f->getStatus());
            self::assertSame('1.0', $f->getRuleVersion());
        }
    }

    // -------------------------------------------------------------------------
    // 2. Deterministik ve Tekrarlanabilir Skor
    // -------------------------------------------------------------------------

    public function testDeterministicScoreCalculation(): void
    {
        $engine = new ScoringEngine();

        $findings = [
            $this->createFinding('TECH_HTTP_STATUS', FindingStatus::PASS, FindingSeverity::HIGH),
            $this->createFinding('TECH_HTTPS', FindingStatus::PASS, FindingSeverity::MEDIUM),
            $this->createFinding('ONPAGE_TITLE_MISSING', FindingStatus::WARN, FindingSeverity::MEDIUM),
            $this->createFinding('LINK_ANCHOR_EMPTY', FindingStatus::FAIL, FindingSeverity::LOW),
        ];

        $firstResult = $engine->calculate($findings);

        // Run 5 times with identical input to verify exact deterministic repeatability
        for ($i = 0; $i < 5; $i++) {
            $repeat = $engine->calculate($findings);
            self::assertSame($firstResult->getOverallScore(), $repeat->getOverallScore());
            self::assertSame($firstResult->getCategoryScores(), $repeat->getCategoryScores());
            self::assertSame($firstResult->getCoverage(), $repeat->getCoverage());
            self::assertSame($firstResult->toArray(), $repeat->toArray());
        }
    }

    // -------------------------------------------------------------------------
    // 3. UNKNOWN Zero Sayılmaz Kabul Kriteri
    // -------------------------------------------------------------------------

    public function testUnknownStatusIsNotCountedAsZero(): void
    {
        $engine = new ScoringEngine();

        // Two technical rules of equal weight (HIGH = 5.0):
        // 1 PASS (5.0 points)
        // 1 UNKNOWN (should NOT contribute 0.0 to denominator!)
        $findings = [
            $this->createFinding('TECH_HTTP_STATUS', FindingStatus::PASS, FindingSeverity::HIGH),
            $this->createFinding('TECH_ROBOTS_UNAVAILABLE', FindingStatus::UNKNOWN, FindingSeverity::HIGH),
        ];

        $score = $engine->calculate($findings);

        // If UNKNOWN was penalized as 0, score would be (5*1.0 + 5*0.0) / 10 = 50.
        // Because UNKNOWN is excluded from denominator, score is 5*1.0 / 5 = 100!
        self::assertSame(100, $score->getCategoryScore('technical'));
        self::assertSame(1, $score->getStatusCounts()['UNKNOWN']);
        self::assertSame(1, $score->getStatusCounts()['PASS']);
    }

    public function testNotApplicableStatusIsExcludedFromDenominator(): void
    {
        $engine = new ScoringEngine();

        // 1 PASS (weight 3.0), 1 NOT_APPLICABLE (weight 3.0)
        $findings = [
            $this->createFinding('ONPAGE_TITLE_MISSING', FindingStatus::PASS, FindingSeverity::MEDIUM),
            $this->createFinding('ONPAGE_TITLE_GENERIC', FindingStatus::NOT_APPLICABLE, FindingSeverity::MEDIUM),
        ];

        $score = $engine->calculate($findings);

        // Not applicable does not drag down the score
        self::assertSame(100, $score->getCategoryScore('onpage'));
        self::assertSame(1, $score->getStatusCounts()['NOT_APPLICABLE']);
    }

    // -------------------------------------------------------------------------
    // 4. Quality Coefficients (WARN = 0.6, FAIL = 0.0)
    // -------------------------------------------------------------------------

    public function testWarnStatusAppliesQualityCoefficient(): void
    {
        $engine = new ScoringEngine();

        $findings = [
            $this->createFinding('ONPAGE_TITLE_TRUNCATION_RISK', FindingStatus::WARN, FindingSeverity::LOW),
        ];

        $score = $engine->calculate($findings);

        // WARN has coefficient 0.6 => round(100 * 0.6) = 60
        self::assertSame(60, $score->getCategoryScore('onpage'));
    }

    public function testFailStatusProducesZeroPoints(): void
    {
        $engine = new ScoringEngine();

        $findings = [
            $this->createFinding('TECH_HTTP_STATUS', FindingStatus::FAIL, FindingSeverity::HIGH),
        ];

        $score = $engine->calculate($findings);

        // FAIL has coefficient 0.0 => 0
        self::assertSame(0, $score->getCategoryScore('technical'));
    }

    // -------------------------------------------------------------------------
    // 5. Zero-Score Security Barrier (isScorable)
    // -------------------------------------------------------------------------

    public function testInsufficientEvidenceSecurityBarrier(): void
    {
        $engine = new ScoringEngine();

        // When all rules are UNKNOWN, coverage is 0.0 < MIN_SCORABLE_COVERAGE (0.20)
        $findings = [
            $this->createFinding('TECH_HTTP_STATUS', FindingStatus::UNKNOWN, FindingSeverity::HIGH),
            $this->createFinding('TECH_HTTPS', FindingStatus::UNKNOWN, FindingSeverity::MEDIUM),
        ];

        $score = $engine->calculate($findings);

        // Must NOT output a fake 0/100 score when measurement is impossible
        self::assertFalse($score->isScorable());
        self::assertNull($score->getOverallScore());
        self::assertSame(0.0, $score->getCoverage());
    }

    // -------------------------------------------------------------------------
    // 6. Snapshot Testleri (Profile A, Profile B, Profile C)
    // -------------------------------------------------------------------------

    public function testSnapshotProfileAPerfectPage(): void
    {
        $engine = new ScoringEngine();

        // Profile A: Flawless page across all 4 categories
        $findings = [
            $this->createFinding('TECH_HTTP_STATUS', FindingStatus::PASS, FindingSeverity::HIGH),
            $this->createFinding('TECH_HTTPS', FindingStatus::PASS, FindingSeverity::MEDIUM),
            $this->createFinding('TECH_CANONICAL_MISSING', FindingStatus::PASS, FindingSeverity::MEDIUM),
            $this->createFinding('ONPAGE_TITLE_MISSING', FindingStatus::PASS, FindingSeverity::MEDIUM),
            $this->createFinding('ONPAGE_H1_EMPTY', FindingStatus::PASS, FindingSeverity::LOW),
            $this->createFinding('LINK_ANCHOR_EMPTY', FindingStatus::PASS, FindingSeverity::LOW),
            $this->createFinding('A11Y_IMAGE_ALT', FindingStatus::PASS, FindingSeverity::LOW),
        ];

        $score = $engine->calculate($findings);

        self::assertTrue($score->isScorable());
        self::assertSame(100, $score->getOverallScore());
        self::assertSame(100, $score->getCategoryScore('technical'));
        self::assertSame(100, $score->getCategoryScore('onpage'));
        self::assertSame(100, $score->getCategoryScore('links'));
        self::assertSame(100, $score->getCategoryScore('accessibility'));
        self::assertSame(1.0, $score->getCoverage());
    }

    public function testSnapshotProfileBDefectivePage(): void
    {
        $engine = new ScoringEngine();

        // Profile B: Page with critical failures
        // technical: HTTP FAIL (5.0 weight, 0 points), HTTPS PASS (3.0 weight, 3.0 points) => 3/8 = 38
        // onpage: Title missing FAIL (3.0 weight, 0 pts), H1 empty WARN (1.0 weight, 0.6 pts) => 0.6/4 = 15
        // links: Anchor empty WARN (1.0 weight, 0.6 pts) => 60
        // a11y: Image alt FAIL (1.0 weight, 0 pts) => 0
        $findings = [
            $this->createFinding('TECH_HTTP_STATUS', FindingStatus::FAIL, FindingSeverity::HIGH),
            $this->createFinding('TECH_HTTPS', FindingStatus::PASS, FindingSeverity::MEDIUM),
            $this->createFinding('ONPAGE_TITLE_MISSING', FindingStatus::FAIL, FindingSeverity::MEDIUM),
            $this->createFinding('ONPAGE_H1_EMPTY', FindingStatus::WARN, FindingSeverity::LOW),
            $this->createFinding('LINK_ANCHOR_EMPTY', FindingStatus::WARN, FindingSeverity::LOW),
            $this->createFinding('A11Y_IMAGE_ALT', FindingStatus::FAIL, FindingSeverity::LOW),
        ];

        $score = $engine->calculate($findings);

        self::assertTrue($score->isScorable());
        self::assertNotNull($score->getOverallScore());
        self::assertSame(38, $score->getCategoryScore('technical'));
        self::assertSame(15, $score->getCategoryScore('onpage'));
        self::assertSame(60, $score->getCategoryScore('links'));
        self::assertSame(0, $score->getCategoryScore('accessibility'));

        // Overall weighted average:
        // technical (40*38 = 1520) + onpage (30*15 = 450) + links (20*60 = 1200) + a11y (10*0 = 0)
        // = 3170 / 100 = 32
        self::assertSame(32, $score->getOverallScore());
    }

    public function testSnapshotProfileCPartialAndUnknownPage(): void
    {
        $engine = new ScoringEngine();

        // Profile C: Mixed page with UNKNOWN and NOT_APPLICABLE
        $findings = [
            $this->createFinding('TECH_HTTP_STATUS', FindingStatus::PASS, FindingSeverity::HIGH),
            $this->createFinding('TECH_ROBOTS_UNAVAILABLE', FindingStatus::UNKNOWN, FindingSeverity::HIGH),
            $this->createFinding('ONPAGE_TITLE_MISSING', FindingStatus::PASS, FindingSeverity::MEDIUM),
            $this->createFinding('ONPAGE_TITLE_GENERIC', FindingStatus::NOT_APPLICABLE, FindingSeverity::MEDIUM),
            $this->createFinding('LINK_ANCHOR_EMPTY', FindingStatus::NOT_APPLICABLE, FindingSeverity::LOW),
        ];

        $score = $engine->calculate($findings);

        self::assertTrue($score->isScorable());
        self::assertSame(100, $score->getCategoryScore('technical'));
        self::assertSame(100, $score->getCategoryScore('onpage'));
        self::assertNull($score->getCategoryScore('links')); // All links rules were N/A
        self::assertNull($score->getCategoryScore('accessibility')); // No a11y rules present

        // Overall score re-normalizes between technical (40) and onpage (30):
        // sumWeighted = 100*40 + 100*30 = 7000; sumWeights = 70 => 7000/70 = 100
        self::assertSame(100, $score->getOverallScore());
    }

    // -------------------------------------------------------------------------
    // 7. AuditScore Serialization & Contract
    // -------------------------------------------------------------------------

    public function testAuditScoreSerialization(): void
    {
        $engine = new ScoringEngine();
        $findings = [
            $this->createFinding('TECH_HTTP_STATUS', FindingStatus::PASS, FindingSeverity::HIGH),
        ];

        $score = $engine->calculate($findings);
        $array = $score->toArray();

        self::assertArrayHasKey('overall_score', $array);
        self::assertArrayHasKey('category_scores', $array);
        self::assertArrayHasKey('category_breakdown', $array);
        self::assertArrayHasKey('coverage', $array);
        self::assertArrayHasKey('is_scorable', $array);
        self::assertArrayHasKey('status_counts', $array);
        self::assertArrayHasKey('total_findings', $array);
        self::assertArrayHasKey('scoring_version', $array);
        self::assertArrayHasKey('rule_set_version', $array);
        self::assertArrayHasKey('calculated_at', $array);
        self::assertArrayHasKey('disclaimer', $array);

        self::assertSame('1.0', $score->getScoringVersion());
        self::assertSame('1.0', $score->getRuleSetVersion());
        self::assertStringContainsString('Google ranking metric', $score->getDisclaimer());
    }
}
