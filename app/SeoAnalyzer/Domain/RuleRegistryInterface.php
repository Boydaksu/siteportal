<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Domain;

interface RuleRegistryInterface
{
    public function register(RuleInterface $rule): void;

    public function get(string $id): ?RuleInterface;

    public function has(string $id): bool;

    /**
     * @return list<RuleInterface>
     */
    public function getByCategory(string $category): array;

    /**
     * @return array<string, RuleInterface>
     */
    public function all(): array;

    public function count(): int;
}
