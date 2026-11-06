<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Presentation;

use SitePortal\Core\I18n;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Scoring\AuditScore;

/**
 * ReportPresenter — Prepares audit findings and score data for secure HTML rendering.
 * Enforces XSS mitigation, internationalized remediation guidance, and category filtering.
 *
 * @phpstan-type FormattedFinding array{
 *     rule_id: string,
 *     rule_version: string,
 *     title: string,
 *     category: string,
 *     category_label: string,
 *     status: string,
 *     status_label: string,
 *     status_class: string,
 *     severity: string,
 *     severity_label: string,
 *     severity_class: string,
 *     confidence: string,
 *     data_source: string,
 *     observed_at: string,
 *     affected_urls: list<string>,
 *     evidence_raw: list<array<string, mixed>>,
 *     evidence_formatted: string,
 *     remediation: string,
 *     code_snippet: ?string,
 *     is_issue: bool
 * }
 */
final class ReportPresenter
{
    public function __construct(
        private readonly I18n $i18n
    ) {
    }

    /**
     * @param list<Finding> $findings
     * @return list<FormattedFinding>
     */
    public function formatFindings(array $findings, string $locale): array
    {
        $formatted = [];
        foreach ($findings as $f) {
            $formatted[] = $this->formatSingleFinding($f, $locale);
        }

        return $formatted;
    }

    /**
     * Formats a single finding with localized labels, safe CSS classes, and remediation advice.
     *
     * @return FormattedFinding
     */
    public function formatSingleFinding(Finding $finding, string $locale): array
    {
        $status = $finding->getStatus();
        $severity = $finding->getSeverity();
        $ruleId = $finding->getRuleId();

        $category = $this->resolveCategory($ruleId);
        $remediation = $this->resolveRemediation($finding, $locale);
        $codeSnippet = $this->resolveCodeSnippet($ruleId);

        $isIssue = in_array($status, [FindingStatus::FAIL, FindingStatus::WARN], true);

        return [
            'rule_id' => $ruleId,
            'rule_version' => $finding->getRuleVersion(),
            'title' => $this->resolveRuleTitle($ruleId, $locale),
            'category' => $category,
            'category_label' => $this->resolveCategoryLabel($category, $locale),
            'status' => $status->value,
            'status_label' => $this->resolveStatusLabel($status, $locale),
            'status_class' => $this->resolveStatusClass($status),
            'severity' => $severity->value,
            'severity_label' => $this->resolveSeverityLabel($severity, $locale),
            'severity_class' => $this->resolveSeverityClass($severity),
            'confidence' => $finding->getConfidence()->value,
            'data_source' => $finding->getDataSource(),
            'observed_at' => $finding->getObservedAt(),
            'affected_urls' => $finding->getAffectedUrls(),
            'evidence_raw' => $finding->getEvidence(),
            'evidence_formatted' => $this->formatEvidence($finding->getEvidence()),
            'remediation' => $remediation,
            'code_snippet' => $codeSnippet,
            'is_issue' => $isIssue,
        ];
    }

    /**
     * @param list<Finding> $findings
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    public function prepareReportViewData(
        AuditScore $score,
        array $findings,
        array $analysis,
        string $locale
    ): array {
        $formattedFindings = $this->formatFindings($findings, $locale);

        $issuesCount = 0;
        $passedCount = 0;
        foreach ($formattedFindings as $f) {
            if ($f['is_issue']) {
                $issuesCount++;
            }
            if ($f['status'] === FindingStatus::PASS->value) {
                $passedCount++;
            }
        }

        return [
            'analysis' => $analysis,
            'score' => $score,
            'overall_score' => $score->getOverallScore(),
            'category_scores' => $score->getCategoryScores(),
            'category_breakdown' => $score->getCategoryBreakdown(),
            'coverage' => $score->getCoverage(),
            'coverage_percent' => (int) round($score->getCoverage() * 100),
            'is_scorable' => $score->isScorable(),
            'status_counts' => $score->getStatusCounts(),
            'total_findings' => $score->getTotalFindings(),
            'issues_count' => $issuesCount,
            'passed_count' => $passedCount,
            'findings' => $formattedFindings,
            'disclaimer' => $score->getDisclaimer(),
            'locale' => $locale,
        ];
    }

    private function resolveCategory(string $ruleId): string
    {
        if (str_starts_with($ruleId, 'TECH_')) {
            return 'technical';
        }
        if (str_starts_with($ruleId, 'ONPAGE_')) {
            return 'onpage';
        }
        if (str_starts_with($ruleId, 'LINK_')) {
            return 'links';
        }
        if (str_starts_with($ruleId, 'A11Y_')) {
            return 'accessibility';
        }
        if (str_starts_with($ruleId, 'MOBILE_')) {
            return 'mobile';
        }
        if (str_starts_with($ruleId, 'SCHEMA_')) {
            return 'schema';
        }

        return 'technical';
    }

    private function resolveCategoryLabel(string $category, string $locale): string
    {
        return match ($category) {
            'technical' => $locale === 'tr-TR' ? 'Teknik SEO' : 'Technical SEO',
            'onpage' => $locale === 'tr-TR' ? 'On-Page SEO' : 'On-Page SEO',
            'links' => $locale === 'tr-TR' ? 'Bağlantılar' : 'Links',
            'accessibility' => $locale === 'tr-TR' ? 'Erişilebilirlik' : 'Accessibility',
            'mobile' => $locale === 'tr-TR' ? 'Mobil Uyumluluk' : 'Mobile Friendliness',
            'schema' => $locale === 'tr-TR' ? 'Yapılandırılmış Veri' : 'Structured Data',
            default => ucfirst($category),
        };
    }

    private function resolveStatusLabel(FindingStatus $status, string $locale): string
    {
        return match ($status) {
            FindingStatus::PASS => $locale === 'tr-TR' ? 'Başarılı' : 'Passed',
            FindingStatus::WARN => $locale === 'tr-TR' ? 'Uyarı' : 'Warning',
            FindingStatus::FAIL => $locale === 'tr-TR' ? 'Başarısız' : 'Failed',
            FindingStatus::UNKNOWN => $locale === 'tr-TR' ? 'Bilinmiyor' : 'Unknown',
            FindingStatus::NOT_APPLICABLE => $locale === 'tr-TR' ? 'Uygulanamaz' : 'Not Applicable',
        };
    }

    private function resolveStatusClass(FindingStatus $status): string
    {
        return match ($status) {
            FindingStatus::PASS => 'badge-status-pass',
            FindingStatus::WARN => 'badge-status-warn',
            FindingStatus::FAIL => 'badge-status-fail',
            FindingStatus::UNKNOWN => 'badge-status-unknown',
            FindingStatus::NOT_APPLICABLE => 'badge-status-na',
        };
    }

    private function resolveSeverityLabel(FindingSeverity $severity, string $locale): string
    {
        return match ($severity) {
            FindingSeverity::CRITICAL => $locale === 'tr-TR' ? 'Kritik' : 'Critical',
            FindingSeverity::HIGH => $locale === 'tr-TR' ? 'Yüksek' : 'High',
            FindingSeverity::MEDIUM => $locale === 'tr-TR' ? 'Orta' : 'Medium',
            FindingSeverity::LOW => $locale === 'tr-TR' ? 'Düşük' : 'Low',
            FindingSeverity::INFO => $locale === 'tr-TR' ? 'Bilgi' : 'Info',
        };
    }

    private function resolveSeverityClass(FindingSeverity $severity): string
    {
        return match ($severity) {
            FindingSeverity::CRITICAL => 'badge-severity-critical',
            FindingSeverity::HIGH => 'badge-severity-high',
            FindingSeverity::MEDIUM => 'badge-severity-medium',
            FindingSeverity::LOW => 'badge-severity-low',
            FindingSeverity::INFO => 'badge-severity-info',
        };
    }

    private function resolveRuleTitle(string $ruleId, string $locale): string
    {
        $key = "rules.{$ruleId}.title";
        $translated = $this->i18n->t($key, [], $locale);
        if ($translated !== $key) {
            return $translated;
        }

        // Clean fallback from ruleId, e.g. TECH_HTTP_STATUS -> Http Status
        $words = explode('_', strtolower($ruleId));
        if (in_array($words[0], ['tech', 'onpage', 'link', 'a11y'], true)) {
            array_shift($words);
        }

        return ucwords(implode(' ', $words));
    }

    private function resolveRemediation(Finding $finding, string $locale): string
    {
        $fixKey = $finding->getFixKey();
        if ($fixKey !== null) {
            $translated = $this->i18n->t($fixKey, [], $locale);
            if ($translated !== $fixKey) {
                return $translated;
            }
        }

        $ruleFixKey = "rules.{$finding->getRuleId()}.fix";
        $translated = $this->i18n->t($ruleFixKey, [], $locale);
        if ($translated !== $ruleFixKey) {
            return $translated;
        }

        return $locale === 'tr-TR'
            ? 'Bu kural için önerilen teknik standartları ve yönergeleri inceleyiniz.'
            : 'Review technical guidelines and best practices for this rule.';
    }

    private function resolveCodeSnippet(string $ruleId): ?string
    {
        return match ($ruleId) {
            'TECH_CANONICAL_MISSING',
            'TECH_CANONICAL_EMPTY' => '<link rel="canonical" href="https://example.com/page">',
            'ONPAGE_TITLE_MISSING' => "<head>\n  <title>Açıklayıcı Başlık - Marka Adı</title>\n</head>",
            'ONPAGE_META_DESC_MISSING' => '<meta name="description" content="Sayfa içeriğini özetleyen açıklama.">',
            'ONPAGE_CHARSET' => '<meta charset="utf-8">',
            'ONPAGE_HTML_LANG' => '<html lang="tr">',
            'ONPAGE_H1_EMPTY' => '<h1>Sayfanın Ana Başlığı</h1>',
            'ONPAGE_OG_METADATA' => "<meta property=\"og:title\" content=\"Sayfa Başlığı\">\n"
                . "<meta property=\"og:image\" content=\"https://example.com/image.jpg\">\n"
                . '<meta property=\"og:description\" content=\"Sayfa Açıklaması\">',
            'LINK_REL_NOFOLLOW' => "<!-- İç linklerde nofollow kaldırılmalıdır -->\n"
                . '<a href="/hakkimizda">Hakkımızda</a>',
            'A11Y_IMAGE_ALT' => '<!-- Anlamlı görsel için -->\n<img src="/logo.png" alt="Şirket Logosu">\n'
                . '<!-- Süsleme amaçlı görsel için -->\n<img src="/decor.png" alt="">',
            'MOBILE_VIEWPORT' => '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
            'A11Y_FORM_LABEL' => "<!-- label for ile -->\n"
                . "<label for=\"email\">E-posta Adresi</label>\n"
                . "<input type=\"email\" id=\"email\" name=\"email\">\n\n"
                . "<!-- aria-label ile -->\n"
                . '<input type="search" name="q" aria-label="Sitede Ara">',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $evidence
     */
    private function formatEvidence(array $evidence): string
    {
        if (empty($evidence)) {
            return '';
        }

        $encoded = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : '';
    }
}
