<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

interface PageSpeedClientInterface
{
    /**
     * Runs or fetches cached PageSpeed Insights lab audit for the given target URL.
     * Guaranteed never to throw uncaught network or quota exceptions; returns
     * unavailable result on API failure.
     */
    public function analyze(string $url, PageSpeedStrategy $strategy = PageSpeedStrategy::MOBILE): PageSpeedResult;
}
