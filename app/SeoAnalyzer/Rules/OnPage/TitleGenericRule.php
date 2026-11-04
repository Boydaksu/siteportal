<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class TitleGenericRule implements RuleInterface
{
    public const string RULE_ID = 'ONPAGE_TITLE_GENERIC';
    public const string RULE_VERSION = '1.0';

    /** @var list<string> */
    private const array GENERIC_TITLES = [
        'home',
        'homepage',
        'ana sayfa',
        'anasayfa',
        'untitled',
        'untitled document',
        'adsız',
        'başlıksız',
        'giriş',
        'index',
        'default',
        'default page',
        'varsayılan',
        'varsayılan sayfa',
        'test',
        'page 1',
        'sayfa 1',
        'new page',
        'yeni sayfa',
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
        return 'onpage';
    }

    public function getDefaultSeverity(): FindingSeverity
    {
        return FindingSeverity::LOW;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $doc = $snapshot->getParsedDocument();
        if ($doc === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::UNKNOWN,
                dataSource: 'parser',
                evidence: [['reason' => 'Parsed document not available']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $title = $doc->getTitle();
        if ($title === null || trim($title) === '') {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['note' => 'No title present; handled by ONPAGE_TITLE_MISSING']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $normalized = mb_strtolower(trim($title), 'UTF-8');
        $isGeneric = in_array($normalized, self::GENERIC_TITLES, true) || mb_strlen($normalized, 'UTF-8') <= 3;

        if ($isGeneric) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::MEDIUM,
                dataSource: 'parser',
                evidence: [[
                    'title' => trim($title),
                    'is_generic' => true,
                    'warning' => 'Title appears generic or non-descriptive; '
                        . 'recommended to include specific brand or page keywords',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.ONPAGE_TITLE_GENERIC.fix'
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
                'title' => trim($title),
                'is_generic' => false,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}
