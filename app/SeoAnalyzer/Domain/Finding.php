<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Domain;

/**
 * Immutable value object representing a single SEO audit finding.
 * Complies strictly with examples/finding.schema.json and ADR-006 (Q-001).
 *
 * @phpstan-type EvidenceList list<array<string, mixed>>
 */
final class Finding
{
    private string $ruleId;
    private string $ruleVersion;
    private FindingStatus $status;
    private FindingSeverity $severity;
    private Confidence $confidence;
    private string $dataSource;
    /** @var EvidenceList */
    private array $evidence;
    private string $observedAt;
    /** @var list<string> */
    private array $affectedUrls;
    private ?string $fixKey;
    private ?string $messageKey;

    /**
     * @param EvidenceList $evidence
     * @param list<string> $affectedUrls
     */
    public function __construct(
        string $ruleId,
        string $ruleVersion,
        FindingStatus $status,
        FindingSeverity $severity,
        Confidence $confidence,
        string $dataSource,
        array $evidence = [],
        ?string $observedAt = null,
        array $affectedUrls = [],
        ?string $fixKey = null,
        ?string $messageKey = null
    ) {
        $this->ruleId = $ruleId;
        $this->ruleVersion = $ruleVersion;
        $this->status = $status;
        $this->severity = $severity;
        $this->confidence = $confidence;
        $this->dataSource = $dataSource;
        $this->evidence = $evidence;
        $this->observedAt = $observedAt ?? gmdate('Y-m-d\TH:i:s\Z');
        $this->affectedUrls = $affectedUrls;
        $this->fixKey = $fixKey;
        $this->messageKey = $messageKey;
    }

    public function getRuleId(): string
    {
        return $this->ruleId;
    }

    public function getRuleVersion(): string
    {
        return $this->ruleVersion;
    }

    public function getStatus(): FindingStatus
    {
        return $this->status;
    }

    public function getSeverity(): FindingSeverity
    {
        return $this->severity;
    }

    public function getConfidence(): Confidence
    {
        return $this->confidence;
    }

    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    /**
     * @return EvidenceList
     */
    public function getEvidence(): array
    {
        return $this->evidence;
    }

    public function getObservedAt(): string
    {
        return $this->observedAt;
    }

    /**
     * @return list<string>
     */
    public function getAffectedUrls(): array
    {
        return $this->affectedUrls;
    }

    public function getFixKey(): ?string
    {
        return $this->fixKey;
    }

    public function getMessageKey(): ?string
    {
        return $this->messageKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'rule_id' => $this->ruleId,
            'rule_version' => $this->ruleVersion,
            'status' => $this->status->value,
            'severity' => $this->severity->value,
            'confidence' => $this->confidence->value,
            'data_source' => $this->dataSource,
            'evidence' => $this->evidence,
            'observed_at' => $this->observedAt,
            'affected_urls' => $this->affectedUrls,
        ];

        if ($this->fixKey !== null) {
            $result['fix_key'] = $this->fixKey;
        }

        if ($this->messageKey !== null) {
            $result['message_key'] = $this->messageKey;
        }

        return $result;
    }
}
