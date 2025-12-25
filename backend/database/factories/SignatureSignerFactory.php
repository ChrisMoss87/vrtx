<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SignatureSigner>
 */
class SignatureSignerFactory extends Factory
{
    protected $model = SignatureSigner::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => SignatureRequest::factory(),
            'email' => $this->faker->email(),
            'name' => $this->faker->name(),
            'role' => $this->faker->randomElement(SignatureSigner::ROLES),
            'sign_order' => $this->faker->numberBetween(1, 5),
            'status' => SignatureSigner::STATUS_PENDING,
            'access_token' => Str::random(64),
            'sent_at' => null,
            'viewed_at' => null,
            'signed_at' => null,
            'declined_at' => null,
            'decline_reason' => null,
            'signed_ip' => null,
            'signed_user_agent' => null,
            'signature_data' => null,
            'contact_id' => null,
        ];
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignatureSigner::STATUS_PENDING,
            'sent_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Viewed status.
     */
    public function viewed(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-7 days', '-1 day');

        return $this->state(fn (array $attributes) => [
            'status' => SignatureSigner::STATUS_VIEWED,
            'sent_at' => $sentAt,
            'viewed_at' => $this->faker->dateTimeBetween($sentAt, 'now'),
        ]);
    }

    /**
     * Signed status.
     */
    public function signed(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-14 days', '-7 days');
        $viewedAt = $this->faker->dateTimeBetween($sentAt, '-3 days');

        return $this->state(fn (array $attributes) => [
            'status' => SignatureSigner::STATUS_SIGNED,
            'sent_at' => $sentAt,
            'viewed_at' => $viewedAt,
            'signed_at' => $this->faker->dateTimeBetween($viewedAt, 'now'),
            'signed_ip' => $this->faker->ipv4(),
            'signed_user_agent' => $this->faker->userAgent(),
            'signature_data' => [
                'type' => 'drawn',
                'data' => 'base64_signature_data_here',
            ],
        ]);
    }

    /**
     * Declined status.
     */
    public function declined(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-14 days', '-7 days');

        return $this->state(fn (array $attributes) => [
            'status' => SignatureSigner::STATUS_DECLINED,
            'sent_at' => $sentAt,
            'viewed_at' => $this->faker->dateTimeBetween($sentAt, '-3 days'),
            'declined_at' => $this->faker->dateTimeBetween('-3 days', 'now'),
            'decline_reason' => $this->faker->randomElement([
                'Terms not acceptable',
                'Need to review with legal team',
                'Pricing concerns',
                'Timeline issues',
            ]),
        ]);
    }

    /**
     * Signer role.
     */
    public function signer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => SignatureSigner::ROLE_SIGNER,
        ]);
    }

    /**
     * Viewer role.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => SignatureSigner::ROLE_VIEWER,
        ]);
    }

    /**
     * Approver role.
     */
    public function approver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => SignatureSigner::ROLE_APPROVER,
        ]);
    }

    /**
     * CC role.
     */
    public function cc(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => SignatureSigner::ROLE_CC,
        ]);
    }

    /**
     * First in order.
     */
    public function first(): static
    {
        return $this->state(fn (array $attributes) => [
            'sign_order' => 1,
        ]);
    }

    /**
     * With specific order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sign_order' => $order,
        ]);
    }
}
