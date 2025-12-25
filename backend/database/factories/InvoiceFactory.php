<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 500, 50000);
        $discountAmount = $this->faker->boolean(30) ? $this->faker->randomFloat(2, 50, $subtotal * 0.1) : 0;
        $taxAmount = ($subtotal - $discountAmount) * 0.08;
        $total = $subtotal - $discountAmount + $taxAmount;
        $status = $this->faker->randomElement(Invoice::STATUSES);
        $amountPaid = $status === Invoice::STATUS_PAID ? $total : ($status === Invoice::STATUS_PARTIAL ? $this->faker->randomFloat(2, 100, $total * 0.8) : 0);
        $balanceDue = $total - $amountPaid;

        $issueDate = $this->faker->dateTimeBetween('-30 days', 'now');
        $paymentTerms = $this->faker->randomElement(array_keys(Invoice::PAYMENT_TERMS));

        return [
            'invoice_number' => 'INV-' . date('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'quote_id' => null,
            'deal_id' => $this->faker->optional(0.5)->numberBetween(1, 100),
            'contact_id' => $this->faker->optional(0.7)->numberBetween(1, 200),
            'company_id' => $this->faker->optional(0.6)->numberBetween(1, 100),
            'status' => $status,
            'title' => $this->faker->randomElement([
                'Professional Services',
                'Software License',
                'Consulting Fee',
                'Monthly Subscription',
                'Project Milestone',
            ]),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
            'currency' => 'USD',
            'issue_date' => $issueDate,
            'due_date' => $this->getDueDateFromTerms($issueDate, $paymentTerms),
            'payment_terms' => $paymentTerms,
            'notes' => $this->faker->optional(0.4)->sentence(),
            'internal_notes' => $this->faker->optional(0.2)->sentence(),
            'template_id' => null,
            'view_token' => Str::random(32),
            'sent_at' => $status !== Invoice::STATUS_DRAFT ? $this->faker->dateTimeBetween($issueDate, 'now') : null,
            'sent_to_email' => $status !== Invoice::STATUS_DRAFT ? $this->faker->safeEmail() : null,
            'viewed_at' => in_array($status, [Invoice::STATUS_VIEWED, Invoice::STATUS_PAID, Invoice::STATUS_PARTIAL]) ? $this->faker->dateTimeBetween($issueDate, 'now') : null,
            'paid_at' => $status === Invoice::STATUS_PAID ? $this->faker->dateTimeBetween($issueDate, 'now') : null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Get due date based on payment terms.
     */
    private function getDueDateFromTerms(\DateTimeInterface $issueDate, string $terms): \DateTime
    {
        $daysMap = [
            'due_on_receipt' => 0,
            'net_7' => 7,
            'net_15' => 15,
            'net_30' => 30,
            'net_45' => 45,
            'net_60' => 60,
            'net_90' => 90,
        ];

        $days = $daysMap[$terms] ?? 30;
        return (clone (new \DateTime($issueDate->format('Y-m-d'))))->modify("+{$days} days");
    }

    /**
     * Draft invoice.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_DRAFT,
            'sent_at' => null,
            'viewed_at' => null,
            'paid_at' => null,
            'amount_paid' => 0,
        ]);
    }

    /**
     * Sent invoice.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now()->subDays($this->faker->numberBetween(1, 7)),
            'sent_to_email' => $this->faker->safeEmail(),
            'amount_paid' => 0,
        ]);
    }

    /**
     * Paid invoice.
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $total = $attributes['total'] ?? 1000;
            return [
                'status' => Invoice::STATUS_PAID,
                'amount_paid' => $total,
                'balance_due' => 0,
                'paid_at' => now()->subDays($this->faker->numberBetween(1, 14)),
            ];
        });
    }

    /**
     * Partial payment invoice.
     */
    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $total = $attributes['total'] ?? 1000;
            $amountPaid = $total * $this->faker->randomFloat(2, 0.2, 0.8);
            return [
                'status' => Invoice::STATUS_PARTIAL,
                'amount_paid' => $amountPaid,
                'balance_due' => $total - $amountPaid,
            ];
        });
    }

    /**
     * Overdue invoice.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_OVERDUE,
            'issue_date' => now()->subDays(45),
            'due_date' => now()->subDays(15),
            'amount_paid' => 0,
        ]);
    }

    /**
     * Cancelled invoice.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_CANCELLED,
        ]);
    }

    /**
     * High value invoice.
     */
    public function highValue(): static
    {
        $subtotal = $this->faker->randomFloat(2, 50000, 200000);
        $taxAmount = $subtotal * 0.08;
        $total = $subtotal + $taxAmount;

        return $this->state(fn (array $attributes) => [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'balance_due' => $total,
        ]);
    }
}
