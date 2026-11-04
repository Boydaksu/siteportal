<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Domain;

interface RuleInterface
{
    /**
     * Unique rule identifier matching schema registry (e.g., 'TECH_HTTP_STATUS').
     */
    public function getId(): string;

    /**
     * Semantic version of this rule definition (e.g., '1.0').
     */
    public function getVersion(): string;

    /**
     * Rule category (e.g., 'technical', 'on_page', 'mobile').
     */
    public function getCategory(): string;

    /**
     * Default finding severity when the rule condition fails.
     */
    public function getDefaultSeverity(): FindingSeverity;

    /**
     * Evaluates the snapshot and returns a deterministic Finding.
     * MUST NOT perform any I/O, network requests, or database queries.
     */
    public function evaluate(PageSnapshot $snapshot): Finding;
}
