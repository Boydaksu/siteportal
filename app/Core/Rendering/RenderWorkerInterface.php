<?php

declare(strict_types=1);

namespace SitePortal\Core\Rendering;

/**
 * Interface defining the contract for an isolated headless browser worker.
 * Supports isolated execution, timeout enforcement, and network restriction policies.
 */
interface RenderWorkerInterface
{
    /**
     * Checks if the rendering worker is enabled, reachable, and ready.
     */
    public function isAvailable(): bool;

    /**
     * Executes rendering of the requested URL and returns the computed DOM response.
     */
    public function render(RenderRequest $request): RenderResponse;
}
