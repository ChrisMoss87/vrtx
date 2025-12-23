<?php

declare(strict_types=1);

namespace App\Domain\Communication\ValueObjects;

final readonly class MessageParticipant
{
    public function __construct(
        public ?int $userId,
        public ?string $name,
        public ?string $email,
        public ?string $phone,
        public ?RecordContext $recordContext = null,
    ) {}

    public static function fromUser(int $userId, string $name, ?string $email = null): self
    {
        return new self(
            userId: $userId,
            name: $name,
            email: $email,
            phone: null,
            recordContext: null,
        );
    }

    public static function fromContact(
        string $name,
        ?string $email,
        ?string $phone,
        ?RecordContext $recordContext = null,
    ): self {
        return new self(
            userId: null,
            name: $name,
            email: $email,
            phone: $phone,
            recordContext: $recordContext,
        );
    }

    public static function fromEmail(string $email, ?string $name = null): self
    {
        return new self(
            userId: null,
            name: $name ?? self::extractNameFromEmail($email),
            email: $email,
            phone: null,
            recordContext: null,
        );
    }

    public static function fromPhone(string $phone, ?string $name = null): self
    {
        return new self(
            userId: null,
            name: $name,
            email: null,
            phone: $phone,
            recordContext: null,
        );
    }

    public static function fromArray(array $data): self
    {
        $recordContext = isset($data['record_context'])
            ? RecordContext::fromArray($data['record_context'])
            : null;

        return new self(
            userId: $data['user_id'] ?? null,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            recordContext: $recordContext,
        );
    }

    public function isUser(): bool
    {
        return $this->userId !== null;
    }

    public function isLinkedToRecord(): bool
    {
        return $this->recordContext !== null;
    }

    public function getDisplayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->email) {
            return self::extractNameFromEmail($this->email);
        }

        if ($this->phone) {
            return $this->phone;
        }

        return 'Unknown';
    }

    public function getPrimaryIdentifier(): ?string
    {
        return $this->email ?? $this->phone;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'record_context' => $this->recordContext?->toArray(),
        ];
    }

    private static function extractNameFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        $localPart = $parts[0];

        // Replace common separators with spaces
        $name = str_replace(['.', '_', '-', '+'], ' ', $localPart);

        // Capitalize words
        return ucwords($name);
    }
}
