<?php

declare(strict_types=1);

namespace SitePortal\Core\Rendering;

use InvalidArgumentException;

/**
 * Immutable value object representing a browser rendering request.
 */
final class RenderRequest
{
    public const int DEFAULT_TIMEOUT_SECONDS = 15;
    public const int MAX_TIMEOUT_SECONDS = 30;
    public const int DEFAULT_VIEWPORT_WIDTH = 1280;
    public const int DEFAULT_VIEWPORT_HEIGHT = 800;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $url,
        private readonly int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        private readonly int $viewportWidth = self::DEFAULT_VIEWPORT_WIDTH,
        private readonly int $viewportHeight = self::DEFAULT_VIEWPORT_HEIGHT,
        private readonly bool $blockTrackers = true,
        private readonly ?string $userAgent = null,
        private readonly array $headers = []
    ) {
        if (trim($url) === '') {
            throw new InvalidArgumentException('Render request URL cannot be empty.');
        }

        if ($timeoutSeconds <= 0 || $timeoutSeconds > self::MAX_TIMEOUT_SECONDS) {
            throw new InvalidArgumentException(
                sprintf('Timeout must be between 1 and %d seconds.', self::MAX_TIMEOUT_SECONDS)
            );
        }

        if ($viewportWidth <= 0 || $viewportHeight <= 0) {
            throw new InvalidArgumentException('Viewport dimensions must be positive integers.');
        }
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function getViewportWidth(): int
    {
        return $this->viewportWidth;
    }

    public function getViewportHeight(): int
    {
        return $this->viewportHeight;
    }

    public function isBlockTrackers(): bool
    {
        return $this->blockTrackers;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
