<?php

declare(strict_types=1);

namespace SitePortal\Core;

use ErrorException;
use SitePortal\Core\Observability\LoggerInterface;
use Throwable;

final class ErrorHandler
{
    private bool $debug;
    private View $view;
    private I18n $i18n;
    private ?LoggerInterface $logger;
    private ?Request $currentRequest = null;

    public function __construct(
        bool $debug = false,
        ?View $view = null,
        ?I18n $i18n = null,
        ?LoggerInterface $logger = null
    ) {
        $this->debug = $debug;
        $this->view = $view ?? new View();
        $this->i18n = $i18n ?? I18n::getInstance();
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setCurrentRequest(Request $request): void
    {
        $this->currentRequest = $request;
    }

    public function register(): void
    {
        error_reporting(E_ALL);

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleException(Throwable $throwable): void
    {
        $request = $this->currentRequest ?? Request::fromGlobals();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $response = $this->renderExceptionResponse($throwable, $request);
        $response->send();
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $exception = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            $this->handleException($exception);
        }
    }

    public function renderExceptionResponse(Throwable $throwable, Request $request): Response
    {
        $requestId = $request->getRequestId();
        $this->logException($throwable, $requestId, $request);

        $locale = $request->getLocale();
        $this->i18n->setLocale($locale);

        $viewData = [
            'requestId' => $requestId,
            'locale' => $locale,
            'debug' => $this->debug,
            'title' => $this->i18n->t('errors.server_error_title', [], $locale),
            'description' => $this->i18n->t('errors.server_error_desc', [], $locale),
            'requestIdLabel' => $this->i18n->t('errors.request_id_label', [], $locale),
            'backToHomeLabel' => $this->i18n->t('errors.back_to_home', [], $locale),
            'debugDetailsLabel' => $this->i18n->t('errors.debug_details', [], $locale),
            'exceptionMessage' => $this->debug ? $throwable->getMessage() : null,
            'exceptionClass' => $this->debug ? get_class($throwable) : null,
            'exceptionFile' => $this->debug ? $throwable->getFile() : null,
            'exceptionLine' => $this->debug ? $throwable->getLine() : null,
            'exceptionTrace' => $this->debug ? $throwable->getTraceAsString() : null,
        ];

        try {
            $html = $this->view->renderWithLayout('errors/500', $viewData);
        } catch (Throwable) {
            // Fallback safe plain HTML if view rendering fails
            $safeReqId = View::escape($requestId);
            $safeTitle = View::escape($viewData['title']);
            $safeDesc = View::escape($viewData['description']);
            $html = "<!DOCTYPE html><html lang=\"{$locale}\"><head><meta charset=\"UTF-8\">" .
                "<title>{$safeTitle}</title></head><body><h1>{$safeTitle}</h1>" .
                "<p>{$safeDesc}</p><p>Request ID: {$safeReqId}</p></body></html>";
        }

        $response = Response::html($html, 500);
        return $response
            ->withRequestId($requestId)
            ->withCorrelationId($request->getCorrelationId());
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    private function logException(Throwable $throwable, string $requestId, ?Request $request = null): void
    {
        $current = $request ?? $this->currentRequest;
        $correlationId = $current?->getCorrelationId();

        if ($this->logger !== null) {
            $this->logger->error($throwable->getMessage(), [
                'exception' => get_class($throwable),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'request_id' => $requestId,
                'correlation_id' => $correlationId,
                'trace' => $this->debug ? $throwable->getTraceAsString() : null,
            ]);
            return;
        }

        $message = sprintf(
            '[%s] Exception: %s in %s:%d (Request-ID: %s)',
            date('Y-m-d H:i:s'),
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            $requestId
        );

        error_log($message);
    }
}
