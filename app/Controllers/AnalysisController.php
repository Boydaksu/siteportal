<?php

declare(strict_types=1);

namespace SitePortal\Controllers;

use SitePortal\Core\Analysis\AnalysisLifecycleService;
use SitePortal\Core\Analysis\Exceptions\AnalysisRateLimitedException;
use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\I18n;
use SitePortal\Core\Request;
use SitePortal\Core\Response;
use SitePortal\Core\Security\Csrf;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\Core\Security\Session;
use SitePortal\Core\View;
use SitePortal\SeoAnalyzer\Presentation\ReportPresenter;
use SitePortal\SeoAnalyzer\Scoring\ScoringEngine;
use Throwable;

final class AnalysisController
{
    private AnalysisLifecycleService $lifecycleService;
    private Config $config;
    private I18n $i18n;
    private View $view;
    private Csrf $csrf;
    private Session $session;

    public function __construct(
        ?AnalysisLifecycleService $lifecycleService = null,
        ?Config $config = null,
        ?I18n $i18n = null,
        ?View $view = null,
        ?Csrf $csrf = null,
        ?Session $session = null
    ) {
        $this->config = $config ?? Config::getInstance();
        $this->i18n = $i18n ?? I18n::getInstance();
        $this->view = $view ?? new View();
        $this->session = $session ?? new Session();
        $this->csrf = $csrf ?? new Csrf($this->session);

        if ($lifecycleService === null) {
            /** @var string $driver */
            $driver = (string) $this->config->get('database.default', 'mysql');
            /** @var array{
             *     driver?: string,
             *     host?: string,
             *     port?: int,
             *     database?: string,
             *     username?: string,
             *     password?: string,
             *     charset?: string,
             *     options?: array<int, mixed>
             * } $connConfig */
            $connConfig = (array) $this->config->get("database.connections.{$driver}", []);
            $db = Connection::create($connConfig);
            $this->lifecycleService = new AnalysisLifecycleService($db, config: $this->config);
        } else {
            $this->lifecycleService = $lifecycleService;
        }
    }

    /**
     * POST /api/v1/analyses
     * Asenkron tek sayfa SEO analizi başlatır (HTTP 202 Accepted).
     */
    public function createApi(Request $request): Response
    {
        $input = $this->extractInput($request);
        $url = trim((string) ($input['url'] ?? ''));
        $mode = (string) ($input['mode'] ?? 'single_page');
        $language = (string) ($input['language'] ?? $request->getLocale());
        $idempotencyKey = $request->getHeader('idempotency-key');

        if ($url === '') {
            return $this->problemJson(
                status: 422,
                title: 'Eksik veya geçersiz URL',
                code: 'URL_REQUIRED',
                detail: 'Analiz başlatmak için geçerli bir web sitesi URL adresi girmelisiniz.',
                requestId: $request->getRequestId()
            );
        }

        $clientIp = $this->resolveClientIp($request);

        try {
            $result = $this->lifecycleService->startAnalysis(
                url: $url,
                clientIp: $clientIp,
                mode: $mode,
                locale: $language,
                idempotencyKey: $idempotencyKey
            );

            $publicId = (string) $result['public_id'];
            $data = [
                'analysis_id' => $publicId,
                'status' => $result['status'],
                'target_url' => $result['target_url'],
                'status_url' => "/api/v1/analyses/{$publicId}",
                'progress_url' => "/api/v1/analyses/{$publicId}/progress",
                'view_url' => "/analysis/{$publicId}",
                'limits' => [
                    'max_pages' => 1,
                ],
            ];

            return Response::json($data, 202)
                ->withHeader('Location', "/api/v1/analyses/{$publicId}");
        } catch (InvalidUrlException $e) {
            return $this->problemJson(
                status: 422,
                title: 'Geçersiz URL biçimi',
                code: $e->getErrorCode() ?: 'INVALID_URL',
                detail: $e->getMessage(),
                requestId: $request->getRequestId()
            );
        } catch (BlockedUrlException $e) {
            return $this->problemJson(
                status: 422,
                title: 'URL güvenlik politikası tarafından reddedildi',
                code: $e->getErrorCode() ?: 'URL_BLOCKED',
                detail: $e->getMessage(),
                requestId: $request->getRequestId()
            );
        } catch (AnalysisRateLimitedException $e) {
            return $this->problemJson(
                status: 429,
                title: 'Analiz istek sınırı aşıldı',
                code: 'RATE_LIMITED',
                detail: $e->getMessage(),
                requestId: $request->getRequestId(),
                additional: ['retry_after' => $e->getRetryAfterSeconds()]
            )->withHeader('Retry-After', (string) $e->getRetryAfterSeconds());
        } catch (Throwable $e) {
            return $this->problemJson(
                status: 500,
                title: 'Analiz başlatılamadı',
                code: 'INTERNAL_ERROR',
                detail: 'Sunucu tarafında beklenmeyen bir hata oluştu. Lütfen tekrar deneyiniz.',
                requestId: $request->getRequestId()
            );
        }
    }

    /**
     * GET /api/v1/analyses/{id}
     * Analiz özet durumunu döndürür.
     */
    public function statusApi(Request $request): Response
    {
        $publicId = (string) $request->getRouteParam('id');
        $analysis = $this->lifecycleService->getAnalysis($publicId);

        if ($analysis === null) {
            return $this->problemJson(
                status: 404,
                title: 'Analiz bulunamadı',
                code: 'ANALYSIS_NOT_FOUND',
                detail: "'{$publicId}' kimlikli analiz kaydı bulunamadı.",
                requestId: $request->getRequestId()
            );
        }

        $data = [
            'analysis_id' => $analysis['public_id'],
            'target_url' => $analysis['target_url'],
            'status' => $analysis['status'],
            'progress_percent' => (int) $analysis['progress_percent'],
            'mode' => $analysis['mode'],
            'scope_count' => (int) $analysis['scope_count'],
            'ruleset_version' => $analysis['ruleset_version'],
            'error_code' => $analysis['error_code'],
            'error_message' => $analysis['error_message'],
            'started_at' => $analysis['started_at'],
            'completed_at' => $analysis['completed_at'],
            'created_at' => $analysis['created_at'],
        ];

        return Response::json($data, 200);
    }

    /**
     * GET /api/v1/analyses/{id}/progress
     * Gerçek aşama sayıları ve ilerleme yüzdesini döndürür (sahte yüzdelik YOK).
     */
    public function progressApi(Request $request): Response
    {
        $publicId = (string) $request->getRouteParam('id');
        $progress = $this->lifecycleService->getProgress($publicId);

        if ($progress === null) {
            return $this->problemJson(
                status: 404,
                title: 'Analiz bulunamadı',
                code: 'ANALYSIS_NOT_FOUND',
                detail: "'{$publicId}' kimlikli analiz kaydı bulunamadı.",
                requestId: $request->getRequestId()
            );
        }

        return Response::json($progress, 200);
    }

    /**
     * POST /analyze
     * Web arayüzü form gönderimi (AJAX veya standart HTML fallback).
     */
    public function createWeb(Request $request): Response
    {
        $isAjax = $this->isAjaxRequest($request);

        // CSRF Doğrulaması
        $token = (string) ($request->getPost('_csrf') ?? $request->getHeader('x-csrf-token') ?? '');
        if (!$this->csrf->validateToken($token)) {
            if ($isAjax) {
                return $this->problemJson(
                    status: 403,
                    title: 'Güvenlik belirteci geçersiz',
                    code: 'CSRF_INVALID',
                    detail: 'Form oturumu sona ermiş olabilir. Lütfen sayfayı yenileyiniz.',
                    requestId: $request->getRequestId()
                );
            }

            return $this->renderHomeWithError(
                $request,
                'Güvenlik belirteci geçersiz veya süresi dolmuş. Lütfen formu yenileyip tekrar deneyiniz.',
                403
            );
        }

        if ($isAjax) {
            return $this->createApi($request);
        }

        // Standart HTML Form gönderimi
        $url = trim((string) $request->getPost('url', ''));
        if ($url === '') {
            return $this->renderHomeWithError($request, 'Lütfen analiz edilecek bir URL giriniz.', 422);
        }

        $clientIp = $this->resolveClientIp($request);

        try {
            $result = $this->lifecycleService->startAnalysis(
                url: $url,
                clientIp: $clientIp,
                locale: $request->getLocale()
            );

            $publicId = (string) $result['public_id'];
            return Response::text('', 302, ['Location' => "/analysis/{$publicId}"]);
        } catch (InvalidUrlException | BlockedUrlException $e) {
            return $this->renderHomeWithError($request, $e->getMessage(), 422);
        } catch (AnalysisRateLimitedException $e) {
            return $this->renderHomeWithError($request, $e->getMessage(), 429);
        } catch (Throwable) {
            return $this->renderHomeWithError(
                $request,
                'Analiz başlatılırken beklenmeyen bir hata oluştu. Lütfen tekrar deneyiniz.',
                500
            );
        }
    }

    /**
     * GET /analysis/{id}
     * Gerçek polling ve canlı aşama ilerleme görünümü.
     */
    public function viewProgress(Request $request): Response
    {
        $publicId = (string) $request->getRouteParam('id');
        $analysis = $this->lifecycleService->getAnalysis($publicId);

        if ($analysis === null) {
            $locale = $request->getLocale();
            $this->i18n->setLocale($locale);
            $html = $this->view->renderWithLayout('errors/404', [
                'requestId' => $request->getRequestId(),
                'locale' => $locale,
                'i18n' => $this->i18n,
                'title' => $this->i18n->t('errors.not_found_title', [], $locale),
                'description' => $this->i18n->t('errors.not_found_desc', [], $locale),
                'requestIdLabel' => $this->i18n->t('errors.request_id_label', [], $locale),
                'backToHomeLabel' => $this->i18n->t('errors.back_to_home', [], $locale),
            ]);
            return Response::html($html, 404);
        }

        $progress = $this->lifecycleService->getProgress($publicId);
        $locale = $request->getLocale();
        $this->i18n->setLocale($locale);

        $html = $this->view->renderWithLayout('analysis/progress', [
            'app' => (string) $this->config->get('app.name', 'SitePortal'),
            'version' => (string) $this->config->get('app.version', '0.1.0'),
            'requestId' => $request->getRequestId(),
            'locale' => $locale,
            'i18n' => $this->i18n,
            'analysis' => $analysis,
            'progress' => $progress,
            'csrfToken' => $this->csrf->getToken(),
        ]);

        return Response::html($html, 200);
    }

    /**
     * GET /analysis/{id}/report
     * Tamamlanmış SEO denetim raporu HTML sayfası.
     */
    public function viewReport(Request $request): Response
    {
        $publicId = (string) $request->getRouteParam('id');
        $analysis = $this->lifecycleService->getAnalysis($publicId);

        if ($analysis === null) {
            $locale = $request->getLocale();
            $this->i18n->setLocale($locale);
            $html = $this->view->renderWithLayout('errors/404', [
                'requestId' => $request->getRequestId(),
                'locale' => $locale,
                'i18n' => $this->i18n,
                'title' => $this->i18n->t('errors.not_found_title', [], $locale),
                'description' => $this->i18n->t('errors.not_found_desc', [], $locale),
                'requestIdLabel' => $this->i18n->t('errors.request_id_label', [], $locale),
                'backToHomeLabel' => $this->i18n->t('errors.back_to_home', [], $locale),
            ]);
            return Response::html($html, 404);
        }

        // Henüz tamamlanmamışsa progress ekranına yönlendir
        if ($analysis['status'] !== AnalysisLifecycleService::STATUS_COMPLETED) {
            return Response::text('', 302, ['Location' => "/analysis/{$publicId}"]);
        }

        $locale = $request->getLocale();
        $this->i18n->setLocale($locale);

        $findings = $this->lifecycleService->getFindings($publicId);
        $score = $this->lifecycleService->getScore($publicId);
        if ($score === null) {
            $scoringEngine = new ScoringEngine();
            $score = $scoringEngine->calculate($findings);
        }

        $presenter = new ReportPresenter($this->i18n);
        $viewData = $presenter->prepareReportViewData($score, $findings, $analysis, $locale);
        $viewData['app'] = (string) $this->config->get('app.name', 'SitePortal');
        $viewData['version'] = (string) $this->config->get('app.version', '1.0.0');
        $viewData['requestId'] = $request->getRequestId();
        $viewData['i18n'] = $this->i18n;

        $html = $this->view->renderWithLayout('analysis/report', $viewData);
        return Response::html($html, 200);
    }

    /**
     * GET /api/v1/analyses/{id}/report
     * Tamamlanmış SEO denetim raporu JSON API çıktısı.
     */
    public function reportApi(Request $request): Response
    {
        $publicId = (string) $request->getRouteParam('id');
        $analysis = $this->lifecycleService->getAnalysis($publicId);

        if ($analysis === null) {
            return $this->problemJson(
                status: 404,
                title: 'Analiz bulunamadı',
                code: 'ANALYSIS_NOT_FOUND',
                detail: "'{$publicId}' kimlikli analiz kaydı bulunamadı.",
                requestId: $request->getRequestId()
            );
        }

        $locale = $request->getLocale();
        $findings = $this->lifecycleService->getFindings($publicId);
        $score = $this->lifecycleService->getScore($publicId);
        if ($score === null) {
            $scoringEngine = new ScoringEngine();
            $score = $scoringEngine->calculate($findings);
        }

        $presenter = new ReportPresenter($this->i18n);
        $data = [
            'analysis_id' => $analysis['public_id'],
            'target_url' => $analysis['target_url'],
            'status' => $analysis['status'],
            'completed_at' => $analysis['completed_at'],
            'score' => $score->toArray(),
            'findings' => $presenter->formatFindings($findings, $locale),
        ];

        return Response::json($data, 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractInput(Request $request): array
    {
        $contentType = (string) $request->getHeader('content-type');
        if (str_contains(strtolower($contentType), 'application/json')) {
            $decoded = json_decode($request->getBody(), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->getPostData();
    }

    private function isAjaxRequest(Request $request): bool
    {
        $accept = (string) $request->getHeader('accept');
        $requestedWith = (string) $request->getHeader('x-requested-with');

        return str_contains(strtolower($accept), 'application/json')
            || strtolower($requestedWith) === 'xmlhttprequest';
    }

    private function resolveClientIp(Request $request): string
    {
        $forwarded = $request->getHeader('x-forwarded-for');
        if ($forwarded !== null && $forwarded !== '') {
            $parts = explode(',', $forwarded);
            $first = trim($parts[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        return '127.0.0.1';
    }

    /**
     * @param array<string, mixed> $additional
     */
    private function problemJson(
        int $status,
        string $title,
        string $code,
        string $detail,
        string $requestId,
        array $additional = []
    ): Response {
        $data = array_merge([
            'type' => "https://siteportal.net/problems/{$code}",
            'title' => $title,
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
            'request_id' => $requestId,
        ], $additional);

        return Response::json($data, $status, [
            'Content-Type' => 'application/problem+json; charset=UTF-8',
        ]);
    }

    private function renderHomeWithError(Request $request, string $errorMessage, int $statusCode): Response
    {
        $locale = $request->getLocale();
        $this->i18n->setLocale($locale);

        $html = $this->view->renderWithLayout('home', [
            'app' => (string) $this->config->get('app.name', 'SitePortal'),
            'version' => (string) $this->config->get('app.version', '0.1.0'),
            'env' => (string) $this->config->get('app.env', 'development'),
            'requestId' => $request->getRequestId(),
            'locale' => $locale,
            'supportedLocales' => $this->i18n->getSupportedLocales(),
            'i18n' => $this->i18n,
            'title' => $this->i18n->t('home.title', [], $locale),
            'lead' => $this->i18n->t('home.lead', [], $locale),
            'errorMessage' => $errorMessage,
            'csrfToken' => $this->csrf->getToken(),
        ]);

        return Response::html($html, $statusCode);
    }
}
