<?php

declare(strict_types=1);

namespace SitePortal\Core\Analysis;

use PDO;
use SitePortal\Core\Analysis\Exceptions\AnalysisRateLimitedException;
use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Observability\LoggerInterface;
use SitePortal\Core\Queue\DatabaseQueue;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\Core\Security\PinnedTarget;
use SitePortal\Core\Security\RateLimiter;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Scoring\AuditScore;

final class AnalysisLifecycleService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CRAWLING = 'crawling';
    public const STATUS_ANALYZING = 'analyzing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    private Connection $db;
    private UrlValidator $urlValidator;
    private DatabaseQueue $queue;
    private RateLimiter $rateLimiter;
    private Config $config;
    private ?LoggerInterface $logger;

    private int $anonymousMaxAttempts;
    private int $anonymousDecaySeconds;

    public function __construct(
        Connection $db,
        ?UrlValidator $urlValidator = null,
        ?DatabaseQueue $queue = null,
        ?RateLimiter $rateLimiter = null,
        ?Config $config = null,
        ?LoggerInterface $logger = null
    ) {
        $this->db = $db;
        $this->urlValidator = $urlValidator ?? new UrlValidator();
        $this->queue = $queue ?? new DatabaseQueue($db);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter($db);
        $this->config = $config ?? Config::getInstance();
        $this->logger = $logger;

        $this->anonymousMaxAttempts = (int) $this->config->get('analysis.anonymous_max_attempts', 5);
        $this->anonymousDecaySeconds = (int) $this->config->get('analysis.anonymous_decay_seconds', 600);
    }

    /**
     * URL doğrulaması, anonim kota kontrolü, site ilişkilendirmesi, analiz kaydı ve kuyruk oluşturma.
     *
     * @param string $url Kullanıcının girdiği hedef URL
     * @param string|null $clientIp İstemci IP adresi
     * @param string $mode Analiz modu (varsayılan: single_page)
     * @param string|null $locale İstemci dil tercihi
     * @param string|null $idempotencyKey İstemci tarafından sağlanan benzersiz istek anahtarı
     * @param int|null $userId Oturum açmış kullanıcı kimliği
     * @return array<string, mixed> Oluşturulan veya mevcut analiz kaydı bilgileri
     *
     * @throws InvalidUrlException Geçersiz format veya şema durumunda
     * @throws BlockedUrlException SSRF, özel/iç IP veya bloklu hedef durumunda
     * @throws AnalysisRateLimitedException Hız sınırı aşıldığında
     */
    public function startAnalysis(
        string $url,
        ?string $clientIp = null,
        string $mode = 'single_page',
        ?string $locale = null,
        ?string $idempotencyKey = null,
        ?int $userId = null
    ): array {
        $clientIp = $clientIp ?? '127.0.0.1';
        $locale = $locale ?? 'tr-TR';

        // 1. Anonim kullanıcı hız sınırı kontrolü (IP bazlı kota)
        if ($userId === null) {
            $throttleKey = 'throttle:analysis:' . $clientIp;
            if ($this->rateLimiter->tooManyAttempts($throttleKey, $this->anonymousMaxAttempts)) {
                $retryAfter = $this->rateLimiter->availableIn($throttleKey);
                if ($this->logger !== null) {
                    $this->logger->warning('Anonim analiz hız sınırı aşıldı', [
                        'ip' => $clientIp,
                        'retry_after' => $retryAfter,
                    ]);
                }
                throw new AnalysisRateLimitedException(
                    "Çok fazla analiz isteği gönderildi. Lütfen {$retryAfter} saniye sonra tekrar deneyiniz.",
                    $retryAfter
                );
            }
        }

        // 2. URL Güvenlik Doğrulaması (SSRF Kalkanı - fail closed)
        $target = $this->urlValidator->validate($url);
        $normalizedUrl = $target->getNormalizedUrl();

        // 3. Idempotency kontrolü: Aynı anahtarla daha önce kayıt oluşturulduysa mükerrer analiz başlatma
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existingJob = $this->db->fetch(
                'SELECT analysis_id FROM analysis_jobs WHERE idempotency_key = :key LIMIT 1',
                ['key' => $idempotencyKey]
            );
            if (
                $existingJob !== null
                && isset($existingJob['analysis_id'])
                && is_numeric($existingJob['analysis_id'])
            ) {
                $existingAnalysis = $this->getAnalysisById((int) $existingJob['analysis_id']);
                if ($existingAnalysis !== null) {
                    return $existingAnalysis;
                }
            }
        }

        // 4. Site kök alan adı (origin) ilişkilendirmesi
        $origin = $target->getScheme() . '://' . $target->getHost();
        if ($target->getPort() !== 80 && $target->getPort() !== 443) {
            $origin .= ':' . $target->getPort();
        }
        $siteId = $this->resolveSiteId($origin, $userId);

        // 5. Analiz kaydını oluştur
        $publicId = 'anl_' . bin2hex(random_bytes(12));
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "INSERT INTO analyses (
                    public_id, site_id, user_id, mode, status, target_url,
                    progress_percent, ruleset_version, scoring_version, scope_count,
                    created_at
                ) VALUES (
                    :public_id, :site_id, :user_id, :mode, :status, :target_url,
                    5, '1.0.0', '1.0.0', 1, :created_at
                )",
                [
                    'public_id' => $publicId,
                    'site_id' => $siteId,
                    'user_id' => $userId,
                    'mode' => $mode,
                    'status' => self::STATUS_PENDING,
                    'target_url' => $normalizedUrl,
                    'created_at' => $now,
                ]
            );

            $analysisId = (int) $this->db->getPdo()->lastInsertId();

            // 6. İşi kuyruğa (`analysis_jobs`) ekle
            $queueKey = $idempotencyKey ?? "audit_{$publicId}";
            $jobId = $this->queue->push(
                type: 'single_page_audit',
                payload: [
                    'analysis_id' => $analysisId,
                    'public_id' => $publicId,
                    'target_url' => $normalizedUrl,
                    'primary_ip' => $target->getPrimaryIp(),
                    'locale' => $locale,
                ],
                idempotencyKey: $queueKey,
                analysisId: $analysisId
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // 7. Başarılı işlemde anonim kotayı tüket
        if ($userId === null) {
            $throttleKey = 'throttle:analysis:' . $clientIp;
            $this->rateLimiter->hit($throttleKey, $this->anonymousDecaySeconds);
        }

        if ($this->logger !== null) {
            $this->logger->info('Yeni analiz başlatıldı ve kuyruğa alındı', [
                'public_id' => $publicId,
                'target_url' => $normalizedUrl,
                'client_ip' => $clientIp,
                'job_id' => $jobId,
            ]);
        }

        return [
            'id' => $analysisId,
            'public_id' => $publicId,
            'target_url' => $normalizedUrl,
            'status' => self::STATUS_PENDING,
            'progress_percent' => 5,
            'mode' => $mode,
            'scope_count' => 1,
            'created_at' => $now,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAnalysis(string $publicId): ?array
    {
        $row = $this->db->fetch(
            "SELECT id, public_id, site_id, user_id, mode, status, target_url,
                    progress_percent, ruleset_version, scoring_version, scope_count,
                    score_overall, score_technical, score_onpage, score_links, score_accessibility,
                    coverage_percent, score_breakdown_json,
                    error_code, error_message, started_at, completed_at, created_at
             FROM analyses WHERE public_id = :public_id LIMIT 1",
            ['public_id' => $publicId]
        );

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAnalysisById(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT id, public_id, site_id, user_id, mode, status, target_url,
                    progress_percent, ruleset_version, scoring_version, scope_count,
                    score_overall, score_technical, score_onpage, score_links, score_accessibility,
                    coverage_percent, score_breakdown_json,
                    error_code, error_message, started_at, completed_at, created_at
             FROM analyses WHERE id = :id LIMIT 1",
            ['id' => $id]
        );

        return $row ?: null;
    }

    /**
     * Gerçek aşama ve ilerleme durumunu hesaplar.
     * Sahte JavaScript timer kesinlikle kullanılmaz; veriler veritabanı durumundan türetilir.
     *
     * @return array<string, mixed>|null
     */
    public function getProgress(string $publicId): ?array
    {
        $analysis = $this->getAnalysis($publicId);
        if ($analysis === null) {
            return null;
        }

        /** @var string $status */
        $status = (string) ($analysis['status'] ?? self::STATUS_PENDING);

        $stageStates = match ($status) {
            self::STATUS_PENDING => [
                'queued' => 'completed',
                'fetching' => 'pending',
                'rules' => 'pending',
                'reporting' => 'pending',
            ],
            self::STATUS_CRAWLING => [
                'queued' => 'completed',
                'fetching' => 'in_progress',
                'rules' => 'pending',
                'reporting' => 'pending',
            ],
            self::STATUS_ANALYZING => [
                'queued' => 'completed',
                'fetching' => 'completed',
                'rules' => 'in_progress',
                'reporting' => 'pending',
            ],
            self::STATUS_COMPLETED => [
                'queued' => 'completed',
                'fetching' => 'completed',
                'rules' => 'completed',
                'reporting' => 'completed',
            ],
            self::STATUS_FAILED => [
                'queued' => 'completed',
                'fetching' => 'failed',
                'rules' => 'failed',
                'reporting' => 'failed',
            ],
            default => [
                'queued' => 'pending',
                'fetching' => 'pending',
                'rules' => 'pending',
                'reporting' => 'pending',
            ],
        };

        $progressPercent = match ($status) {
            self::STATUS_PENDING => 5,
            self::STATUS_CRAWLING => 35,
            self::STATUS_ANALYZING => 75,
            self::STATUS_COMPLETED, self::STATUS_FAILED => 100,
            default => 0,
        };

        $stages = [
            ['key' => 'queued', 'name' => 'Kuyruğa Alındı', 'state' => $stageStates['queued']],
            ['key' => 'fetching', 'name' => 'Sayfa Taranıyor', 'state' => $stageStates['fetching']],
            ['key' => 'rules', 'name' => 'SEO Kuralları Denetleniyor', 'state' => $stageStates['rules']],
            ['key' => 'reporting', 'name' => 'Rapor Hazırlanıyor', 'state' => $stageStates['reporting']],
        ];

        return [
            'analysis_id' => $analysis['public_id'],
            'target_url' => $analysis['target_url'],
            'status' => $status,
            'progress_percent' => $progressPercent,
            'stages' => $stages,
            'error_code' => $analysis['error_code'],
            'error_message' => $analysis['error_message'],
            'started_at' => $analysis['started_at'],
            'completed_at' => $analysis['completed_at'],
            'created_at' => $analysis['created_at'],
        ];
    }

    /**
     * Analiz durumunu ve ilerlemesini atomik olarak günceller.
     */
    public function updateStatus(
        int|string $analysisIdOrPublicId,
        string $status,
        int $progressPercent = 0,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): void {
        $now = date('Y-m-d H:i:s');
        $startedAtUpdate = $status === self::STATUS_CRAWLING ? ', started_at = COALESCE(started_at, :now)' : '';
        $completedAtUpdate = in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)
            ? ', completed_at = :now'
            : '';

        if ($progressPercent <= 0) {
            $progressPercent = match ($status) {
                self::STATUS_PENDING => 5,
                self::STATUS_CRAWLING => 35,
                self::STATUS_ANALYZING => 75,
                self::STATUS_COMPLETED, self::STATUS_FAILED => 100,
                default => 0,
            };
        }

        $isId = is_int($analysisIdOrPublicId) || ctype_digit($analysisIdOrPublicId);
        $where = $isId ? 'id = :id' : 'public_id = :id';
        $idParam = $isId ? (int) $analysisIdOrPublicId : (string) $analysisIdOrPublicId;

        $sql = "UPDATE analyses SET
                    status = :status,
                    progress_percent = :progress_percent,
                    error_code = :error_code,
                    error_message = :error_message
                    {$startedAtUpdate}
                    {$completedAtUpdate}
                WHERE {$where}";

        $params = [
            'id' => $idParam,
            'status' => $status,
            'progress_percent' => $progressPercent,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ];

        if ($startedAtUpdate !== '' || $completedAtUpdate !== '') {
            $params['now'] = $now;
        }

        $this->db->execute($sql, $params);
    }

    /**
     * @param list<Finding> $findings
     */
    public function saveFindings(int $analysisId, array $findings): void
    {
        $this->db->beginTransaction();
        try {
            // Önceki bulguları temizle (idempotent yeniden hesaplama)
            $this->db->execute('DELETE FROM findings WHERE analysis_id = :id', ['id' => $analysisId]);

            $sql = 'INSERT INTO findings (
                analysis_id, rule_id, rule_version, status, severity,
                confidence, data_source, evidence_json, affected_urls_json, observed_at
            ) VALUES (
                :analysis_id, :rule_id, :rule_version, :status, :severity,
                :confidence, :data_source, :evidence_json, :affected_urls_json, :observed_at
            )';

            foreach ($findings as $f) {
                $this->db->execute($sql, [
                    'analysis_id' => $analysisId,
                    'rule_id' => $f->getRuleId(),
                    'rule_version' => $f->getRuleVersion(),
                    'status' => $f->getStatus()->value,
                    'severity' => $f->getSeverity()->value,
                    'confidence' => $f->getConfidence()->value,
                    'data_source' => $f->getDataSource(),
                    'evidence_json' => json_encode(
                        $f->getEvidence(),
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    ),
                    'affected_urls_json' => json_encode(
                        $f->getAffectedUrls(),
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    ),
                    'observed_at' => $f->getObservedAt(),
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @return list<Finding>
     */
    public function getFindings(int|string $analysisIdOrPublicId): array
    {
        $analysisId = $this->resolveAnalysisId($analysisIdOrPublicId);
        if ($analysisId === null) {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT rule_id, rule_version, status, severity, confidence,
                    data_source, evidence_json, affected_urls_json, observed_at
             FROM findings
             WHERE analysis_id = :analysis_id
             ORDER BY id ASC',
            ['analysis_id' => $analysisId]
        );

        $findings = [];
        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $statusVal = (string) ($row['status'] ?? 'UNKNOWN');
            $severityVal = (string) ($row['severity'] ?? 'INFO');
            $confidenceVal = (string) ($row['confidence'] ?? 'HIGH');

            $status = FindingStatus::tryFrom($statusVal) ?? FindingStatus::UNKNOWN;
            $severity = FindingSeverity::tryFrom($severityVal) ?? FindingSeverity::INFO;
            $confidence = Confidence::tryFrom($confidenceVal) ?? Confidence::HIGH;

            /** @var list<array<string, mixed>> $evidence */
            $evidence = [];
            if (!empty($row['evidence_json'])) {
                $decoded = json_decode((string) $row['evidence_json'], true);
                if (is_array($decoded)) {
                    /** @var list<array<string, mixed>> $decoded */
                    $evidence = $decoded;
                }
            }

            /** @var list<string> $affectedUrls */
            $affectedUrls = [];
            if (!empty($row['affected_urls_json'])) {
                $decodedUrls = json_decode((string) $row['affected_urls_json'], true);
                if (is_array($decodedUrls)) {
                    /** @var list<string> $decodedUrls */
                    $affectedUrls = $decodedUrls;
                }
            }

            $ruleId = (string) $row['rule_id'];
            $fixKey = "rules.{$ruleId}.fix";

            $findings[] = new Finding(
                ruleId: $ruleId,
                ruleVersion: (string) ($row['rule_version'] ?? '1.0.0'),
                status: $status,
                severity: $severity,
                confidence: $confidence,
                dataSource: (string) ($row['data_source'] ?? 'crawler'),
                evidence: $evidence,
                observedAt: (string) ($row['observed_at'] ?? gmdate('Y-m-d\TH:i:s\Z')),
                affectedUrls: $affectedUrls,
                fixKey: $fixKey
            );
        }

        return $findings;
    }

    public function saveScore(int|string $analysisIdOrPublicId, AuditScore $score): void
    {
        $analysisId = $this->resolveAnalysisId($analysisIdOrPublicId);
        if ($analysisId === null) {
            return;
        }

        $scores = $score->getCategoryScores();
        $breakdownJson = json_encode([
            'category_breakdown' => $score->getCategoryBreakdown(),
            'status_counts' => $score->getStatusCounts(),
            'coverage' => $score->getCoverage(),
            'is_scorable' => $score->isScorable(),
            'disclaimer' => $score->getDisclaimer(),
            'scoring_version' => $score->getScoringVersion(),
            'rule_set_version' => $score->getRuleSetVersion(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $sql = 'UPDATE analyses SET
            score_overall = :score_overall,
            score_technical = :score_technical,
            score_onpage = :score_onpage,
            score_links = :score_links,
            score_accessibility = :score_accessibility,
            coverage_percent = :coverage_percent,
            score_breakdown_json = :score_breakdown_json
            WHERE id = :id';

        $this->db->execute($sql, [
            'id' => $analysisId,
            'score_overall' => $score->getOverallScore(),
            'score_technical' => $scores['technical'] ?? null,
            'score_onpage' => $scores['onpage'] ?? null,
            'score_links' => $scores['links'] ?? null,
            'score_accessibility' => $scores['accessibility'] ?? null,
            'coverage_percent' => (int) round($score->getCoverage() * 100),
            'score_breakdown_json' => $breakdownJson,
        ]);
    }

    public function getScore(int|string $analysisIdOrPublicId): ?AuditScore
    {
        $analysisId = $this->resolveAnalysisId($analysisIdOrPublicId);
        if ($analysisId === null) {
            return null;
        }

        $row = $this->db->fetch(
            'SELECT score_overall, score_technical, score_onpage, score_links, score_accessibility,
                    coverage_percent, score_breakdown_json
             FROM analyses WHERE id = :id LIMIT 1',
            ['id' => $analysisId]
        );

        if (!$row || empty($row['score_breakdown_json'])) {
            return null;
        }

        $breakdown = json_decode((string) $row['score_breakdown_json'], true);
        if (!is_array($breakdown)) {
            return null;
        }

        /** @var array<string, ?int> $catScores */
        $catScores = [
            'technical' => isset($row['score_technical']) ? (int) $row['score_technical'] : null,
            'onpage' => isset($row['score_onpage']) ? (int) $row['score_onpage'] : null,
            'links' => isset($row['score_links']) ? (int) $row['score_links'] : null,
            'accessibility' => isset($row['score_accessibility']) ? (int) $row['score_accessibility'] : null,
        ];

        /** @var array<string, array{score: ?int, measured_weight: float, total_category_weight: float, pass_count: int, warn_count: int, fail_count: int, unknown_count: int, na_count: int}> $categoryBreakdown */
        $categoryBreakdown = $breakdown['category_breakdown'] ?? [];

        /** @var array{PASS: int, WARN: int, FAIL: int, UNKNOWN: int, NOT_APPLICABLE: int} $statusCounts */
        $statusCounts = $breakdown['status_counts'] ?? [
            'PASS' => 0,
            'WARN' => 0,
            'FAIL' => 0,
            'UNKNOWN' => 0,
            'NOT_APPLICABLE' => 0,
        ];

        $totalFindings = array_sum($statusCounts);
        $overallScore = isset($row['score_overall']) ? (int) $row['score_overall'] : null;
        $coverage = isset($breakdown['coverage'])
            ? (float) $breakdown['coverage']
            : ((int) $row['coverage_percent']) / 100.0;
        $isScorable = (bool) ($breakdown['is_scorable'] ?? ($overallScore !== null));
        $disclaimer = (string) ($breakdown['disclaimer'] ?? AuditScore::DEFAULT_DISCLAIMER);
        $scoringVersion = (string) ($breakdown['scoring_version'] ?? AuditScore::SCORING_VERSION);
        $ruleSetVersion = (string) ($breakdown['rule_set_version'] ?? AuditScore::RULE_SET_VERSION);

        return new AuditScore(
            overallScore: $overallScore,
            categoryScores: $catScores,
            categoryBreakdown: $categoryBreakdown,
            coverage: $coverage,
            isScorable: $isScorable,
            statusCounts: $statusCounts,
            totalFindings: $totalFindings,
            scoringVersion: $scoringVersion,
            ruleSetVersion: $ruleSetVersion,
            disclaimer: $disclaimer
        );
    }

    private function resolveAnalysisId(int|string $analysisIdOrPublicId): ?int
    {
        if (is_int($analysisIdOrPublicId) || ctype_digit((string) $analysisIdOrPublicId)) {
            return (int) $analysisIdOrPublicId;
        }

        $row = $this->db->fetch(
            'SELECT id FROM analyses WHERE public_id = :public_id LIMIT 1',
            ['public_id' => (string) $analysisIdOrPublicId]
        );

        return $row && isset($row['id']) ? (int) $row['id'] : null;
    }

    private function resolveSiteId(string $origin, ?int $userId): int
    {
        $existing = $this->db->fetch(
            'SELECT id FROM sites WHERE normalized_origin = :origin LIMIT 1',
            ['origin' => $origin]
        );

        if ($existing && isset($existing['id'])) {
            return (int) $existing['id'];
        }

        $this->db->execute(
            'INSERT INTO sites (normalized_origin, owner_user_id, created_at)
             VALUES (:origin, :user_id, :created_at)',
            [
                'origin' => $origin,
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return (int) $this->db->getPdo()->lastInsertId();
    }
}
