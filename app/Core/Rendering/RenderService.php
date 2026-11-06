<?php

declare(strict_types=1);

namespace SitePortal\Core\Rendering;

use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Parser\ParsedDocument;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\Core\Security\UrlValidator;

/**
 * Opt-in Selective JavaScript Rendering Service.
 *
 * Coordinates headless browser workers, enforces SSRF / network isolation policies,
 * and handles graceful degradation when the rendering service is disabled or offline.
 */
final class RenderService
{
    private UrlValidator $urlValidator;
    private HtmlParser $parser;
    private DomDiffCalculator $diffCalculator;

    public function __construct(
        private readonly ?RenderWorkerInterface $worker = null,
        private readonly bool $enabled = false,
        ?UrlValidator $urlValidator = null,
        ?HtmlParser $parser = null,
        ?DomDiffCalculator $diffCalculator = null
    ) {
        $this->urlValidator = $urlValidator ?? new UrlValidator();
        $this->parser = $parser ?? new HtmlParser();
        $this->diffCalculator = $diffCalculator ?? new DomDiffCalculator();
    }

    /**
     * Checks if selective JS rendering is enabled and worker is available.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->worker !== null && $this->worker->isAvailable();
    }

    /**
     * Renders a URL with strict SSRF validation and timeout rules.
     * If the service is disabled, returns null gracefully without throwing errors.
     *
     * @throws InvalidUrlException If URL is malformed
     * @throws BlockedUrlException If target resolves to private/internal/cloud IP
     */
    public function render(RenderRequest $request): ?RenderResponse
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // SSRF ve güvenlik doğrulaması (Localhost, 127.0.0.1, 169.254.169.254 ve private subnetler engellenir)
        $this->urlValidator->validate($request->getUrl());

        assert($this->worker !== null);

        try {
            return $this->worker->render($request);
        } catch (\Throwable $e) {
            return RenderResponse::failure($request->getUrl(), $e->getMessage());
        }
    }

    /**
     * Renders the URL and calculates DOM difference between raw document and rendered document.
     * Returns null if rendering is not enabled or fails.
     */
    public function renderAndDiff(
        string $url,
        ParsedDocument $rawDoc,
        ?RenderRequest $request = null
    ): ?DomDiffResult {
        if (!$this->isEnabled()) {
            return null;
        }

        $req = $request ?? new RenderRequest($url);
        $response = $this->render($req);

        if ($response === null || !$response->isSuccess()) {
            return null;
        }

        try {
            $renderedDoc = $this->parser->parse($response->getRenderedHtml(), $url);
            return $this->diffCalculator->calculate($rawDoc, $renderedDoc);
        } catch (\Throwable) {
            return null;
        }
    }
}
