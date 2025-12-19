<?php

declare(strict_types=1);

namespace Database\Factories\Traits;

/**
 * Common status modifiers for document factories (Quote, Invoice, Proposal).
 */
trait HasDocumentStatuses
{
    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->getStatusConstant('DRAFT'),
        ]);
    }

    /**
     * Sent status.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->getStatusConstant('SENT'),
            'sent_at' => now(),
            'sent_to_email' => $this->faker->email(),
        ]);
    }

    /**
     * Viewed status.
     */
    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->getStatusConstant('VIEWED'),
            'sent_at' => now()->subDays(2),
            'viewed_at' => now()->subDay(),
        ]);
    }

    /**
     * Expired status.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->getStatusConstant('EXPIRED'),
            'valid_until' => now()->subDays(7),
        ]);
    }

    /**
     * Get the status constant from the model.
     */
    protected function getStatusConstant(string $status): string
    {
        $constantName = "STATUS_{$status}";

        if (defined("{$this->model}::{$constantName}")) {
            return constant("{$this->model}::{$constantName}");
        }

        return strtolower($status);
    }
}
