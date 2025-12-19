<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DealRoom;
use App\Models\DealRoomMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealRoomMember>
 */
class DealRoomMemberFactory extends Factory
{
    protected $model = DealRoomMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => DealRoom::factory(),
            'user_id' => User::factory(),
            'external_email' => null,
            'external_name' => null,
            'role' => $this->faker->randomElement([
                DealRoomMember::ROLE_TEAM,
                DealRoomMember::ROLE_STAKEHOLDER,
                DealRoomMember::ROLE_VIEWER,
            ]),
            'access_token' => null,
            'token_expires_at' => null,
            'last_accessed_at' => $this->faker->optional(0.7)->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Internal user member.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'external_email' => null,
            'external_name' => null,
        ]);
    }

    /**
     * External member (no user account).
     */
    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'external_email' => $this->faker->unique()->safeEmail(),
            'external_name' => $this->faker->name(),
            'access_token' => Str::random(64),
            'token_expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Owner role.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => DealRoomMember::ROLE_OWNER,
        ]);
    }

    /**
     * Team member role.
     */
    public function team(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => DealRoomMember::ROLE_TEAM,
        ]);
    }

    /**
     * Stakeholder role.
     */
    public function stakeholder(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => DealRoomMember::ROLE_STAKEHOLDER,
        ]);
    }

    /**
     * Viewer role.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => DealRoomMember::ROLE_VIEWER,
        ]);
    }

    /**
     * With valid access token.
     */
    public function withAccessToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_token' => Str::random(64),
            'token_expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * With expired token.
     */
    public function withExpiredToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_token' => Str::random(64),
            'token_expires_at' => now()->subDays(7),
        ]);
    }
}
