<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SignatureField;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SignatureTemplate>
 */
class SignatureTemplateFactory extends Factory
{
    protected $model = SignatureTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Standard Contract Signing',
                'NDA Signature Template',
                'Multi-Party Agreement',
                'Sales Contract Template',
                'Employment Agreement Signing',
            ]),
            'description' => $this->faker->sentence(),
            'signers' => [
                [
                    'name' => 'Primary Signer',
                    'email' => '',
                    'role' => SignatureSigner::ROLE_SIGNER,
                ],
            ],
            'fields' => [
                [
                    'field_type' => SignatureField::TYPE_SIGNATURE,
                    'page_number' => 1,
                    'x_position' => 100,
                    'y_position' => 600,
                    'width' => 200,
                    'height' => 50,
                    'required' => true,
                    'label' => 'Signature',
                ],
                [
                    'field_type' => SignatureField::TYPE_DATE,
                    'page_number' => 1,
                    'x_position' => 350,
                    'y_position' => 600,
                    'width' => 100,
                    'height' => 30,
                    'required' => true,
                    'label' => 'Date',
                ],
            ],
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Inactive template.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Multi-signer template.
     */
    public function multiSigner(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Multi-Party Agreement',
            'signers' => [
                ['name' => 'Party A', 'email' => '', 'role' => SignatureSigner::ROLE_SIGNER],
                ['name' => 'Party B', 'email' => '', 'role' => SignatureSigner::ROLE_SIGNER],
                ['name' => 'Witness', 'email' => '', 'role' => SignatureSigner::ROLE_VIEWER],
            ],
            'fields' => [
                [
                    'field_type' => SignatureField::TYPE_SIGNATURE,
                    'page_number' => 1,
                    'x_position' => 100,
                    'y_position' => 500,
                    'width' => 200,
                    'height' => 50,
                    'required' => true,
                    'label' => 'Party A Signature',
                ],
                [
                    'field_type' => SignatureField::TYPE_SIGNATURE,
                    'page_number' => 1,
                    'x_position' => 100,
                    'y_position' => 600,
                    'width' => 200,
                    'height' => 50,
                    'required' => true,
                    'label' => 'Party B Signature',
                ],
                [
                    'field_type' => SignatureField::TYPE_DATE,
                    'page_number' => 1,
                    'x_position' => 350,
                    'y_position' => 600,
                    'width' => 100,
                    'height' => 30,
                    'required' => true,
                    'label' => 'Date',
                ],
            ],
        ]);
    }

    /**
     * With initials field.
     */
    public function withInitials(): static
    {
        return $this->state(function (array $attributes) {
            $fields = $attributes['fields'] ?? [];
            $fields[] = [
                'field_type' => SignatureField::TYPE_INITIALS,
                'page_number' => 1,
                'x_position' => 500,
                'y_position' => 100,
                'width' => 60,
                'height' => 30,
                'required' => true,
                'label' => 'Initials',
            ];

            return ['fields' => $fields];
        });
    }
}
