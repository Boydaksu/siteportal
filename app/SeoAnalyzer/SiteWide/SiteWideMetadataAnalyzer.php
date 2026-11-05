<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\SiteWide;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;

/**
 * Analyzes site-wide metadata patterns such as duplicate titles, duplicate meta descriptions,
 * and near-duplicate H1 tags across crawled pages.
 */
final class SiteWideMetadataAnalyzer
{
    /**
     * @return list<Finding>
     */
    public function analyze(SiteCrawlContext $context): array
    {
        $findings = [];

        $titleFinding = $this->analyzeDuplicateTitles($context);
        if ($titleFinding !== null) {
            $findings[] = $titleFinding;
        }

        $metaDescFinding = $this->analyzeDuplicateMetaDescriptions($context);
        if ($metaDescFinding !== null) {
            $findings[] = $metaDescFinding;
        }

        $h1Finding = $this->analyzeDuplicateHeadings($context);
        if ($h1Finding !== null) {
            $findings[] = $h1Finding;
        }

        return $findings;
    }

    private function analyzeDuplicateTitles(SiteCrawlContext $context): ?Finding
    {
        /** @var array<string, list<string>> $titleGroups [normalizedTitle => [url1, url2]] */
        $titleGroups = [];
        /** @var array<string, string> $originalTitles [normalizedTitle => originalTitle] */
        $originalTitles = [];

        foreach ($context->getSnapshots() as $url => $snapshot) {
            $parsed = $snapshot->getParsedDocument();
            $title = $parsed?->getTitle();
            if ($title === null) {
                continue;
            }

            $normalized = $this->normalizeText($title);
            if ($normalized === '') {
                continue;
            }

            $titleGroups[$normalized][] = $url;
            if (!isset($originalTitles[$normalized])) {
                $originalTitles[$normalized] = $title;
            }
        }

        /** @var list<array<string, mixed>> $evidence */
        $evidence = [];
        /** @var list<string> $affectedUrls */
        $affectedUrls = [];

        foreach ($titleGroups as $normalized => $urls) {
            if (count($urls) > 1) {
                $original = $originalTitles[$normalized] ?? $normalized;
                $evidence[] = [
                    'title' => $original,
                    'occurrences' => count($urls),
                    'urls' => $urls,
                ];
                foreach ($urls as $u) {
                    if (!in_array($u, $affectedUrls, true)) {
                        $affectedUrls[] = $u;
                    }
                }
            }
        }

        if (!empty($evidence)) {
            return new Finding(
                ruleId: 'ONPAGE_TITLE_DUPLICATE',
                ruleVersion: '1.0',
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'html_parsed',
                evidence: $evidence,
                affectedUrls: $affectedUrls,
                fixKey: 'fix_onpage_title_duplicate',
                messageKey: 'msg_onpage_title_duplicate'
            );
        }

        if ($context->getCrawledPageCount() > 0) {
            return new Finding(
                ruleId: 'ONPAGE_TITLE_DUPLICATE',
                ruleVersion: '1.0',
                status: FindingStatus::PASS,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'html_parsed',
                evidence: [['total_pages_checked' => $context->getCrawledPageCount()]],
                messageKey: 'msg_onpage_title_duplicate_pass'
            );
        }

        return null;
    }

    private function analyzeDuplicateMetaDescriptions(SiteCrawlContext $context): ?Finding
    {
        /** @var array<string, list<string>> $descGroups [normalizedDesc => [url1, url2]] */
        $descGroups = [];
        /** @var array<string, string> $originalDescs [normalizedDesc => originalDesc] */
        $originalDescs = [];

        foreach ($context->getSnapshots() as $url => $snapshot) {
            $parsed = $snapshot->getParsedDocument();
            $desc = $parsed?->getMetaDescription();
            if ($desc === null) {
                continue;
            }

            $normalized = $this->normalizeText($desc);
            if ($normalized === '') {
                continue;
            }

            $descGroups[$normalized][] = $url;
            if (!isset($originalDescs[$normalized])) {
                $originalDescs[$normalized] = $desc;
            }
        }

        /** @var list<array<string, mixed>> $evidence */
        $evidence = [];
        /** @var list<string> $affectedUrls */
        $affectedUrls = [];

        foreach ($descGroups as $normalized => $urls) {
            if (count($urls) > 1) {
                $original = $originalDescs[$normalized] ?? $normalized;
                $evidence[] = [
                    'meta_description' => $original,
                    'occurrences' => count($urls),
                    'urls' => $urls,
                ];
                foreach ($urls as $u) {
                    if (!in_array($u, $affectedUrls, true)) {
                        $affectedUrls[] = $u;
                    }
                }
            }
        }

        if (!empty($evidence)) {
            return new Finding(
                ruleId: 'ONPAGE_META_DESC_DUPLICATE',
                ruleVersion: '1.0',
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'html_parsed',
                evidence: $evidence,
                affectedUrls: $affectedUrls,
                fixKey: 'fix_onpage_meta_desc_duplicate',
                messageKey: 'msg_onpage_meta_desc_duplicate'
            );
        }

        if ($context->getCrawledPageCount() > 0) {
            return new Finding(
                ruleId: 'ONPAGE_META_DESC_DUPLICATE',
                ruleVersion: '1.0',
                status: FindingStatus::PASS,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'html_parsed',
                evidence: [['total_pages_checked' => $context->getCrawledPageCount()]],
                messageKey: 'msg_onpage_meta_desc_duplicate_pass'
            );
        }

        return null;
    }

    private function analyzeDuplicateHeadings(SiteCrawlContext $context): ?Finding
    {
        /** @var array<string, list<string>> $h1Groups [normalizedH1 => [url1, url2]] */
        $h1Groups = [];
        /** @var array<string, string> $originalH1s [normalizedH1 => originalH1] */
        $originalH1s = [];

        foreach ($context->getSnapshots() as $url => $snapshot) {
            $parsed = $snapshot->getParsedDocument();
            $h1List = $parsed?->getH1Texts() ?? [];

            foreach ($h1List as $h1) {
                $normalized = $this->normalizeText($h1);
                if ($normalized === '') {
                    continue;
                }

                $h1Groups[$normalized][] = $url;
                if (!isset($originalH1s[$normalized])) {
                    $originalH1s[$normalized] = $h1;
                }
            }
        }

        /** @var list<array<string, mixed>> $evidence */
        $evidence = [];
        /** @var list<string> $affectedUrls */
        $affectedUrls = [];

        foreach ($h1Groups as $normalized => $urls) {
            $uniqueUrls = array_values(array_unique($urls));
            if (count($uniqueUrls) > 1) {
                $original = $originalH1s[$normalized] ?? $normalized;
                $evidence[] = [
                    'h1' => $original,
                    'occurrences' => count($uniqueUrls),
                    'urls' => $uniqueUrls,
                ];
                foreach ($uniqueUrls as $u) {
                    if (!in_array($u, $affectedUrls, true)) {
                        $affectedUrls[] = $u;
                    }
                }
            }
        }

        if (!empty($evidence)) {
            return new Finding(
                ruleId: 'CONTENT_NEAR_DUPLICATE',
                ruleVersion: '1.0',
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::MEDIUM,
                dataSource: 'html_parsed',
                evidence: $evidence,
                affectedUrls: $affectedUrls,
                fixKey: 'fix_content_near_duplicate',
                messageKey: 'msg_content_near_duplicate_h1'
            );
        }

        if ($context->getCrawledPageCount() > 0) {
            return new Finding(
                ruleId: 'CONTENT_NEAR_DUPLICATE',
                ruleVersion: '1.0',
                status: FindingStatus::PASS,
                severity: FindingSeverity::LOW,
                confidence: Confidence::HIGH,
                dataSource: 'html_parsed',
                evidence: [['total_pages_checked' => $context->getCrawledPageCount()]],
                messageKey: 'msg_content_near_duplicate_pass'
            );
        }

        return null;
    }

    private function normalizeText(string $text): string
    {
        $clean = trim($text);
        if ($clean === '') {
            return '';
        }

        $clean = (string) preg_replace('/\s+/u', ' ', $clean);
        // Turkish/multibyte normalization: replace uppercase dotted/dotless I before lowercasing
        $clean = str_replace(['İ', 'I'], ['i', 'ı'], $clean);
        return mb_strtolower($clean, 'UTF-8');
    }
}
