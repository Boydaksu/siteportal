<?php

declare(strict_types=1);

namespace SitePortal\Core\Analysis;

use SitePortal\Core\Config;
use SitePortal\Core\Http\Exceptions\HttpException;
use SitePortal\Core\Http\Exceptions\HttpServerException;
use SitePortal\Core\Http\Exceptions\HttpTimeoutException;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\SafeHttpClient;
use SitePortal\Core\Observability\LoggerInterface;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Parser\HtmlParserInterface;
use SitePortal\Core\Queue\Job;
use SitePortal\Core\Queue\JobHandlerInterface;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\DnsResolutionException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Engine\AuditEngine;
use SitePortal\SeoAnalyzer\Engine\AuditEngineInterface;
use SitePortal\SeoAnalyzer\Engine\V1RuleRegistryFactory;
use SitePortal\SeoAnalyzer\Scoring\ScoringEngine;
use SitePortal\SeoAnalyzer\Scoring\ScoringEngineInterface;
use Throwable;

/**
 * SinglePageAuditJobHandler — End-to-end execution pipeline for V1.0 single-page SEO audits.
 *
 * Responsibilities:
 * 1. Progress updates (crawling 25% -> analyzing 60% -> completed 100%).
 * 2. Safe crawling with SSRF protection, DNS pinning, and timeout controls.
 * 3. Graceful handling of unreachable targets and external outages.
 * 4. HTML parsing and PageSnapshot construction.
 * 5. Deterministic rule evaluation via AuditEngine (32 V1 rules).
 * 6. AuditScore calculation via ScoringEngine.
 * 7. Persisting findings and score breakdown in the database.
 */
final class SinglePageAuditJobHandler implements JobHandlerInterface
{
    private AnalysisLifecycleService $lifecycleService;
    private HttpClientInterface $httpClient;
    private HtmlParserInterface $htmlParser;
    private AuditEngineInterface $auditEngine;
    private ScoringEngineInterface $scoringEngine;
    private Config $config;
    private ?LoggerInterface $logger;

    public function __construct(
        AnalysisLifecycleService $lifecycleService,
        ?HttpClientInterface $httpClient = null,
        ?HtmlParserInterface $htmlParser = null,
        ?AuditEngineInterface $auditEngine = null,
        ?ScoringEngineInterface $scoringEngine = null,
        ?Config $config = null,
        ?LoggerInterface $logger = null
    ) {
        $this->lifecycleService = $lifecycleService;
        $this->config = $config ?? Config::getInstance();
        $this->logger = $logger;
        $this->httpClient = $httpClient ?? new SafeHttpClient();
        $this->htmlParser = $htmlParser ?? new HtmlParser();
        $registry = V1RuleRegistryFactory::createRegistry();
        $this->auditEngine = $auditEngine ?? new AuditEngine($registry);
        $this->scoringEngine = $scoringEngine ?? new ScoringEngine($registry);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Executes the single-page audit job.
     */
    public function handle(Job $job): void
    {
        $payload = $job->getPayload();
        $analysisId = isset($payload['analysis_id']) ? (int) $payload['analysis_id'] : null;
        $publicId = isset($payload['public_id']) ? (string) $payload['public_id'] : null;
        $targetUrl = isset($payload['target_url']) ? (string) $payload['target_url'] : null;

        if ($analysisId === null || $targetUrl === null) {
            throw new \InvalidArgumentException('Geçersiz audit job payload: analysis_id veya target_url eksik.');
        }

        // 1. Durumu 'crawling' (tarama) aşamasına al
        $this->lifecycleService->updateStatus(
            analysisIdOrPublicId: $analysisId,
            status: AnalysisLifecycleService::STATUS_CRAWLING,
            progressPercent: 25
        );

        if ($this->logger !== null) {
            $this->logger->info('Analiz tarama aşaması başlatıldı', [
                'analysis_id' => $analysisId,
                'public_id' => $publicId,
                'url' => $targetUrl,
            ]);
        }

        // 2. Hedef URL'yi güvenli HTTP istemcisiyle çek
        try {
            $httpResponse = $this->httpClient->get($targetUrl);
        } catch (BlockedUrlException $e) {
            $this->failAudit(
                $analysisId,
                'SSRF_BLOCKED',
                'Hedef adres güvenlik politikası gereği engellendi (özel IP / SSRF bariyeri).'
            );
            return;
        } catch (DnsResolutionException $e) {
            $this->failAudit(
                $analysisId,
                'DNS_ERROR',
                'Hedef alan adı DNS sunucuları tarafından çözümlenemedi.'
            );
            return;
        } catch (HttpTimeoutException $e) {
            $this->failAudit(
                $analysisId,
                'TIMEOUT',
                'Hedef sunucuya bağlanırken veya yanıt beklenirken zaman aşımı oluştu.'
            );
            return;
        } catch (HttpServerException $e) {
            $this->failAudit(
                $analysisId,
                'SERVER_ERROR',
                'Hedef sunucu HTTP 5xx sunucu hatası döndürdü.'
            );
            return;
        } catch (InvalidUrlException $e) {
            $this->failAudit(
                $analysisId,
                'INVALID_URL',
                'Girilen hedef URL formatı veya şeması geçersiz.'
            );
            return;
        } catch (HttpException $e) {
            $this->failAudit(
                $analysisId,
                'HTTP_ERROR',
                'Hedefe erişilirken HTTP hatası meydana geldi: ' . $e->getMessage()
            );
            return;
        } catch (Throwable $e) {
            $this->failAudit(
                $analysisId,
                'OUTAGE_OR_NETWORK_ERROR',
                'Dış ağ kesintisi veya hedef erişim hatası: ' . $e->getMessage()
            );
            return;
        }

        // 3. Durumu 'analyzing' (kural denetimi) aşamasına al
        $this->lifecycleService->updateStatus(
            analysisIdOrPublicId: $analysisId,
            status: AnalysisLifecycleService::STATUS_ANALYZING,
            progressPercent: 60
        );

        // 4. HTML ayrıştırma ve PageSnapshot oluşturma
        $parsedDoc = null;
        try {
            $parsedDoc = $this->htmlParser->parse(
                html: $httpResponse->getBody(),
                baseUrl: $httpResponse->getEffectiveUrl()
            );
        } catch (Throwable $e) {
            if ($this->logger !== null) {
                $this->logger->warning('HTML ayrıştırma uyarısı (kısmi snapshot ile devam edilecek)', [
                    'analysis_id' => $analysisId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $snapshot = new PageSnapshot(
            requestedUrl: $targetUrl,
            finalUrl: $httpResponse->getEffectiveUrl(),
            httpResponse: $httpResponse,
            parsedDocument: $parsedDoc,
            robotsPolicy: ['allowed' => true],
            redirectHistory: $httpResponse->getRedirectChain()
        );

        // 5. Kural motorunu çalıştır
        $findings = $this->auditEngine->audit($snapshot);

        // 6. Skorlama motorunu çalıştır
        $score = $this->scoringEngine->calculate($findings);

        // 7. Bulguları ve skoru kaydet
        $this->lifecycleService->saveFindings($analysisId, $findings);
        $this->lifecycleService->saveScore($analysisId, $score);

        // 8. Durumu 'completed' (tamamlandı) olarak işaretle
        $this->lifecycleService->updateStatus(
            analysisIdOrPublicId: $analysisId,
            status: AnalysisLifecycleService::STATUS_COMPLETED,
            progressPercent: 100
        );

        if ($this->logger !== null) {
            $this->logger->info('Analiz başarıyla tamamlandı', [
                'analysis_id' => $analysisId,
                'public_id' => $publicId,
                'score_overall' => $score->getOverallScore(),
                'findings_count' => count($findings),
            ]);
        }
    }

    /**
     * Helper to mark the analysis as failed with error code and message.
     */
    private function failAudit(int $analysisId, string $errorCode, string $errorMessage): void
    {
        $this->lifecycleService->updateStatus(
            analysisIdOrPublicId: $analysisId,
            status: AnalysisLifecycleService::STATUS_FAILED,
            progressPercent: 100,
            errorCode: $errorCode,
            errorMessage: $errorMessage
        );

        if ($this->logger !== null) {
            $this->logger->warning('Analiz başarısız oldu', [
                'analysis_id' => $analysisId,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);
        }
    }
}
