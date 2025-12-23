<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use JsonSerializable;

/**
 * Value object representing a paginated result set.
 *
 * This is a domain-level abstraction for pagination, independent of Laravel's
 * LengthAwarePaginator, allowing the domain layer to return paginated data
 * without coupling to infrastructure.
 */
readonly class PaginatedResult implements JsonSerializable
{
    /**
     * @param array $items The items in the current page
     * @param int $total Total number of items across all pages
     * @param int $perPage Number of items per page
     * @param int $currentPage Current page number (1-based)
     * @param int $lastPage Last page number
     * @param int $from First item index on current page (1-based)
     * @param int $to Last item index on current page (1-based)
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
        public int $lastPage,
        public int $from = 0,
        public int $to = 0,
    ) {}

    /**
     * Create from pagination values.
     */
    public static function create(
        array $items,
        int $total,
        int $perPage,
        int $currentPage
    ): self {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = min($currentPage * $perPage, $total);

        return new self(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            lastPage: $lastPage,
            from: $from,
            to: $to,
        );
    }

    /**
     * Create an empty result.
     */
    public static function empty(int $perPage = 25): self
    {
        return new self(
            items: [],
            total: 0,
            perPage: $perPage,
            currentPage: 1,
            lastPage: 1,
            from: 0,
            to: 0,
        );
    }

    /**
     * Check if there are more pages.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Check if on first page.
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * Check if on last page.
     */
    public function onLastPage(): bool
    {
        return $this->currentPage === $this->lastPage;
    }

    /**
     * Check if the result set is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    /**
     * Check if the result set is not empty.
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Get the count of items in the current page.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Map items to a new result.
     */
    public function map(callable $callback): self
    {
        return new self(
            items: array_map($callback, $this->items),
            total: $this->total,
            perPage: $this->perPage,
            currentPage: $this->currentPage,
            lastPage: $this->lastPage,
            from: $this->from,
            to: $this->to,
        );
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->items,
            'meta' => [
                'current_page' => $this->currentPage,
                'from' => $this->from,
                'last_page' => $this->lastPage,
                'per_page' => $this->perPage,
                'to' => $this->to,
                'total' => $this->total,
            ],
        ];
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
