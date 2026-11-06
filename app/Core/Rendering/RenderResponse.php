<?php

declare(strict_types=1);

namespace SitePortal\Core\Rendering;

/**
 * Immutable value object representing a browser rendering response.
 */
final class RenderResponse
{
    /**
     * @param list<BlockedResource> $blockedResources
     * @param list<string> $consoleErrors
     */
    public function __construct(
        private readonly string $url,
        private readonly int $statusCode,
        private readonly string $renderedHtml,
        private readonly float $loadTimeMs,
        private readonly array $blockedResources = [],
        private readonly array $consoleErrors = [],
        private readonly bool $isSuccess = true,
        private readonly ?string $errorMessage = null
    ) {
    }

    public static function failure(string $url, string $errorMessage, float $loadTimeMs = 0.0): self
    {
        return new self(
            url: $url,
            statusCode: 0,
            renderedHtml: '',
            loadTimeMs: $loadTimeMs,
            blockedResources: [],
            consoleErrors: [],
            isSuccess: false,
            errorMessage: $errorMessage
        );
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getRenderedHtml(): string
    {
        return $this->renderedHtml;
    }

    public function getLoadTimeMs(): float
    {
        return $this->loadTimeMs;
    }

    /**
     * @return list<BlockedResource>
     */
    public function getBlockedResources(): array
    {
        return $this->blockedResources;
    }

    /**
     * @return list<BlockedResource>
     */
    public function getCriticalBlockedResources(): array
    {
        return array_values(array_filter(
            $this->blockedResources,
            static fn (BlockedResource $r): bool => $r->isCritical()
        ));
    }

    /**
     * @return list<string>
     */
    public function getConsoleErrors(): array
    {
        return $this->consoleErrors;
    }

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'status_code' => $this->statusCode,
            'rendered_bytes' => strlen($this->renderedHtml),
            'load_time_ms' => $this->loadTimeMs,
            'blocked_resources_count' => count($this->blockedResources),
            'critical_blocked_count' => count($this->getCriticalBlockedResources()),
            'console_errors_count' => count($this->consoleErrors),
            'is_success' => $this->isSuccess,
            'error_message' => $this->errorMessage,
        ];
    }
}
