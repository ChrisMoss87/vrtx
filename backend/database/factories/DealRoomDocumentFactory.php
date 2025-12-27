<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\DealRoom\Entities\DealRoomDocument;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\DealRoom\Entities\DealRoomDocument>
 */
class DealRoomDocumentFactory extends Factory
{
    protected $model = DealRoomDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $documentTypes = [
            ['name' => 'Proposal.pdf', 'mime' => 'application/pdf', 'ext' => 'pdf'],
            ['name' => 'Contract.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'ext' => 'docx'],
            ['name' => 'Pricing Sheet.xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ext' => 'xlsx'],
            ['name' => 'Product Demo.mp4', 'mime' => 'video/mp4', 'ext' => 'mp4'],
            ['name' => 'Technical Specs.pdf', 'mime' => 'application/pdf', 'ext' => 'pdf'],
            ['name' => 'Case Study.pdf', 'mime' => 'application/pdf', 'ext' => 'pdf'],
            ['name' => 'NDA.pdf', 'mime' => 'application/pdf', 'ext' => 'pdf'],
            ['name' => 'SOW.pdf', 'mime' => 'application/pdf', 'ext' => 'pdf'],
        ];

        $doc = $this->faker->randomElement($documentTypes);

        return [
            'room_id' => DealRoom::factory(),
            'name' => $doc['name'],
            'file_path' => 'deal-rooms/' . $this->faker->uuid() . '.' . $doc['ext'],
            'file_size' => $this->faker->numberBetween(102400, 10485760),
            'mime_type' => $doc['mime'],
            'version' => 1,
            'description' => $this->faker->optional(0.5)->sentence(),
            'is_visible_to_external' => $this->faker->boolean(80),
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * PDF document.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->words(2, true) . '.pdf',
            'mime_type' => 'application/pdf',
            'file_path' => 'deal-rooms/' . $this->faker->uuid() . '.pdf',
        ]);
    }

    /**
     * Word document.
     */
    public function docx(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->words(2, true) . '.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_path' => 'deal-rooms/' . $this->faker->uuid() . '.docx',
        ]);
    }

    /**
     * Visible to external members.
     */
    public function visibleToExternal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible_to_external' => true,
        ]);
    }

    /**
     * Internal only.
     */
    public function internalOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible_to_external' => false,
        ]);
    }
}
