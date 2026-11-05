<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Presentation;

/**
 * Encapsulates paginated subset of items with pagination metadata.
 *
 * @template T
 */
final class PaginatedResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $totalItems,
        private readonly int $currentPage,
        private readonly int $perPage
    ) {
    }

    /**
     * @return list<T>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalPages(): int
    {
        if ($this->totalItems <= 0 || $this->perPage <= 0) {
            return 1;
        }

        return (int) ceil($this->totalItems / $this->perPage);
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getTotalPages();
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function getNextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    public function getPreviousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    /**
     * @return array{
     *     items: list<T>,
     *     total_items: int,
     *     current_page: int,
     *     per_page: int,
     *     total_pages: int,
     *     has_next: bool,
     *     has_prev: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total_items' => $this->totalItems,
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total_pages' => $this->getTotalPages(),
            'has_next' => $this->hasNextPage(),
            'has_prev' => $this->hasPreviousPage(),
        ];
    }
}
