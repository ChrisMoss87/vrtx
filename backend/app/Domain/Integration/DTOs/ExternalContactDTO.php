<?php

declare(strict_types=1);

namespace App\Domain\Integration\DTOs;

/**
 * Normalized contact data from external integrations
 */
final readonly class ExternalContactDTO
{
    public function __construct(
        public string $externalId,
        public string $provider,
        public ?string $displayName = null,
        public ?string $companyName = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $mobile = null,
        public ?string $website = null,
        public ?string $billingAddressLine1 = null,
        public ?string $billingAddressLine2 = null,
        public ?string $billingCity = null,
        public ?string $billingState = null,
        public ?string $billingPostalCode = null,
        public ?string $billingCountry = null,
        public ?string $shippingAddressLine1 = null,
        public ?string $shippingAddressLine2 = null,
        public ?string $shippingCity = null,
        public ?string $shippingState = null,
        public ?string $shippingPostalCode = null,
        public ?string $shippingCountry = null,
        public ?string $taxNumber = null,
        public ?string $currency = null,
        public ?string $notes = null,
        public ?bool $isActive = true,
        public array $metadata = [],
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'provider' => $this->provider,
            'display_name' => $this->displayName,
            'company_name' => $this->companyName,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'billing_address_line1' => $this->billingAddressLine1,
            'billing_address_line2' => $this->billingAddressLine2,
            'billing_city' => $this->billingCity,
            'billing_state' => $this->billingState,
            'billing_postal_code' => $this->billingPostalCode,
            'billing_country' => $this->billingCountry,
            'shipping_address_line1' => $this->shippingAddressLine1,
            'shipping_address_line2' => $this->shippingAddressLine2,
            'shipping_city' => $this->shippingCity,
            'shipping_state' => $this->shippingState,
            'shipping_postal_code' => $this->shippingPostalCode,
            'shipping_country' => $this->shippingCountry,
            'tax_number' => $this->taxNumber,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
