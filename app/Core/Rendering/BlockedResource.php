<?php

declare(strict_types=1);

namespace SitePortal\Core\Rendering;

/**
 * Immutable value object representing a resource blocked during rendering.
 */
final class BlockedResource
{
    public function __construct(
        private readonly string $url,
        private readonly string $resourceType,
        private readonly string $reason,
        private readonly bool $isCritical = false
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function isCritical(): bool
    {
        return $this->isCritical;
    }

    /**
     * @return array{url: string, resource_type: string, reason: string, is_critical: bool}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'resource_type' => $this->resourceType,
            'reason' => $this->reason,
            'is_critical' => $this->isCritical,
        ];
    }
}
