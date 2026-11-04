<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Domain;

final class RuleRegistry implements RuleRegistryInterface
{
    /** @var array<string, RuleInterface> */
    private array $rules = [];

    /**
     * @param list<RuleInterface> $rules
     */
    public function __construct(array $rules = [])
    {
        foreach ($rules as $rule) {
            $this->register($rule);
        }
    }

    public function register(RuleInterface $rule): void
    {
        $this->rules[$rule->getId()] = $rule;
    }

    public function get(string $id): ?RuleInterface
    {
        return $this->rules[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->rules[$id]);
    }

    /**
     * @return list<RuleInterface>
     */
    public function getByCategory(string $category): array
    {
        $filtered = [];
        foreach ($this->rules as $rule) {
            if ($rule->getCategory() === $category) {
                $filtered[] = $rule;
            }
        }

        return $filtered;
    }

    /**
     * @return array<string, RuleInterface>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function count(): int
    {
        return count($this->rules);
    }
}
