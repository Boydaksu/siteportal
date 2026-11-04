<?php

declare(strict_types=1);

namespace SitePortal\Core;

use SitePortal\Controllers\AnalysisController;
use SitePortal\Controllers\HealthController;
use SitePortal\Controllers\HomeController;
use SitePortal\Controllers\StaticFileController;
use Throwable;

final class App
{
    private Config $config;
    private I18n $i18n;
    private Router $router;
    private View $view;
    private ErrorHandler $errorHandler;

    public function __construct(
        ?Config $config = null,
        ?I18n $i18n = null,
        ?Router $router = null,
        ?View $view = null,
        ?ErrorHandler $errorHandler = null
    ) {
        $this->config = $config ?? Config::getInstance();
        $this->i18n = $i18n ?? I18n::getInstance();
        $this->view = $view ?? new View();

        /** @var bool $debug */
        $debug = (bool) $this->config->get('app.debug', false);
        $this->errorHandler = $errorHandler ?? new ErrorHandler($debug, $this->view, $this->i18n);

        $this->router = $router ?? new Router();
        $this->registerRoutes();
        $this->registerNotFoundHandler();
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getI18n(): I18n
    {
        return $this->i18n;
    }

    public function getView(): View
    {
        return $this->view;
    }

    public function getErrorHandler(): ErrorHandler
    {
        return $this->errorHandler;
    }

    public function bootstrap(): void
    {
        $this->errorHandler->register();
    }

    public function handle(Request $request): Response
    {
        $this->errorHandler->setCurrentRequest($request);
        $this->i18n->setLocale($request->getLocale());

        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $e) {
            $response = $this->errorHandler->renderExceptionResponse($e, $request);
        }

        $response->withRequestId($request->getRequestId());
        $response->withCorrelationId($request->getCorrelationId());

        return $response;
    }

    public function run(): void
    {
        $this->bootstrap();
        $request = Request::fromGlobals();
        $response = $this->handle($request);
        $response->send();
    }

    private function registerRoutes(): void
    {
        $this->router->get('/', [HomeController::class, 'index']);
        $this->router->post('/analyze', [AnalysisController::class, 'createWeb']);
        $this->router->get('/analysis/{id}', [AnalysisController::class, 'viewProgress']);
        $this->router->get('/analysis/{id}/report', [AnalysisController::class, 'viewReport']);
        $this->router->post('/api/v1/analyses', [AnalysisController::class, 'createApi']);
        $this->router->get('/api/v1/analyses/{id}', [AnalysisController::class, 'statusApi']);
        $this->router->get('/api/v1/analyses/{id}/progress', [AnalysisController::class, 'progressApi']);
        $this->router->get('/api/v1/analyses/{id}/report', [AnalysisController::class, 'reportApi']);
        $this->router->post('/api/v1/analyses/{id}/share', [AnalysisController::class, 'createShareTokenApi']);
        $this->router->delete('/api/v1/analyses/{id}/share', [AnalysisController::class, 'revokeShareTokenApi']);
        $this->router->post('/api/v1/analyses/{id}/visibility', [AnalysisController::class, 'setVisibilityApi']);
        $this->router->get('/health', [HealthController::class, 'health']);
        $this->router->get('/robots.txt', [StaticFileController::class, 'robots']);
        $this->router->get('/sitemap.xml', [StaticFileController::class, 'sitemap']);
    }

    private function registerNotFoundHandler(): void
    {
        $this->router->setNotFoundHandler(function (Request $request): Response {
            $locale = $request->getLocale();
            $this->i18n->setLocale($locale);

            $viewData = [
                'requestId' => $request->getRequestId(),
                'locale' => $locale,
                'title' => $this->i18n->t('errors.not_found_title', [], $locale),
                'description' => $this->i18n->t('errors.not_found_desc', [], $locale),
                'requestIdLabel' => $this->i18n->t('errors.request_id_label', [], $locale),
                'backToHomeLabel' => $this->i18n->t('errors.back_to_home', [], $locale),
            ];

            $html = $this->view->renderWithLayout('errors/404', $viewData);
            return Response::html($html, 404);
        });
    }
}
