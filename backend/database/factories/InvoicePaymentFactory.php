<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Entities\InvoicePayment;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Entities\InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'payment_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'payment_method' => $this->faker->randomElement(array_keys(InvoicePayment::METHODS)),
            'reference' => $this->faker->optional(0.7)->regexify('[A-Z0-9]{8,12}'),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Credit card payment.
     */
    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => InvoicePayment::METHOD_CREDIT_CARD,
            'reference' => 'CC-' . $this->faker->regexify('[0-9]{8}'),
        ]);
    }

    /**
     * Bank transfer payment.
     */
    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => InvoicePayment::METHOD_BANK_TRANSFER,
            'reference' => 'ACH-' . $this->faker->regexify('[0-9]{12}'),
        ]);
    }

    /**
     * Check payment.
     */
    public function check(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => InvoicePayment::METHOD_CHECK,
            'reference' => 'CHK-' . $this->faker->regexify('[0-9]{6}'),
        ]);
    }

    /**
     * Cash payment.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => InvoicePayment::METHOD_CASH,
            'reference' => null,
        ]);
    }

    /**
     * Recent payment.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_date' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Full payment (for a specific amount).
     */
    public function fullPayment(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    /**
     * Partial payment.
     */
    public function partialPayment(float $totalAmount = null): static
    {
        $total = $totalAmount ?? 10000;
        return $this->state(fn (array $attributes) => [
            'amount' => $this->faker->randomFloat(2, $total * 0.1, $total * 0.5),
        ]);
    }
}
