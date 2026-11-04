<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class Soft404SuspectRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_SOFT_404_SUSPECT';
    public const string RULE_VERSION = '1.0';

    /** @var list<string> */
    private const array ERROR_PHRASES = [
        'page not found',
        'sayfa bulunamadı',
        'sayfa bulunamadi',
        '404 not found',
        '404 error',
        '404 hata',
        'not found',
        'icerik bulunamadi',
        'içerik bulunamadı',
        'aradiginiz sayfa',
        'aradığınız sayfa',
        'does not exist',
    ];

    public function getId(): string
    {
        return self::RULE_ID;
    }

    public function getVersion(): string
    {
        return self::RULE_VERSION;
    }

    public function getCategory(): string
    {
        return 'technical';
    }

    public function getDefaultSeverity(): FindingSeverity
    {
        return FindingSeverity::MEDIUM;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $response = $snapshot->getHttpResponse();
        if ($response === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::UNKNOWN,
                dataSource: 'parser',
                evidence: [['reason' => 'No HTTP response available for soft-404 heuristic']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $code = $response->getStatusCode();
        if ($code >= 400) {
            // Already a real 4xx HTTP error status, not a soft-404
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'status_code' => $code,
                    'note' => 'Real HTTP 4xx error returned; soft-404 check is not applicable',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $doc = $snapshot->getParsedDocument();
        $title = strtolower($doc?->getTitle() ?? '');
        $wordCount = $doc?->getWordCount() ?? 0;
        $h1Texts = $doc?->getH1Texts() ?? [];
        $h1Text = strtolower(implode(' ', $h1Texts));

        $matchedInTitle = null;
        $matchedInH1 = null;

        foreach (self::ERROR_PHRASES as $phrase) {
            if (str_contains($title, $phrase)) {
                $matchedInTitle = $phrase;
                break;
            }
            if (str_contains($h1Text, $phrase)) {
                $matchedInH1 = $phrase;
                break;
            }
        }

        $isThinContent = $wordCount < 15;
        $hasIndicator = $matchedInTitle !== null || $matchedInH1 !== null;
        $isSuspect = $hasIndicator && ($isThinContent || str_contains($title, '404'));

        if ($isSuspect) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::MEDIUM,
                dataSource: 'parser',
                evidence: [[
                    'status_code' => $code,
                    'title' => $doc?->getTitle(),
                    'h1' => $h1Texts,
                    'word_count' => $wordCount,
                    'matched_phrase' => $matchedInTitle ?? $matchedInH1,
                    'suspected_soft_404' => true,
                    'heuristic_warning' => 'HTTP 200 returned but title/H1 indicates missing content or 404 error',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_SOFT_404_SUSPECT.fix'
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [[
                'status_code' => $code,
                'suspected_soft_404' => false,
                'word_count' => $wordCount,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}
