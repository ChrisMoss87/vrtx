<?php

declare(strict_types=1);

namespace App\Domain\Integration\ValueObjects;

final readonly class IntegrationOAuthState
{
    private function __construct(
        public int $userId,
        public IntegrationProvider $provider,
        public ?int $reconnectConnectionId,
        public ?string $redirectTo,
        public \DateTimeImmutable $expiresAt,
    ) {}

    public static function create(
        int $userId,
        IntegrationProvider $provider,
        ?int $reconnectConnectionId = null,
        ?string $redirectTo = null,
        int $ttlMinutes = 10,
    ): self {
        return new self(
            userId: $userId,
            provider: $provider,
            reconnectConnectionId: $reconnectConnectionId,
            redirectTo: $redirectTo,
            expiresAt: new \DateTimeImmutable("+{$ttlMinutes} minutes"),
        );
    }

    public static function fromEncoded(string $encoded): self
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid OAuth state encoding');
        }

        $data = json_decode($decoded, true);
        if ($data === null || !isset($data['user_id'], $data['provider'], $data['expires_at'])) {
            throw new \InvalidArgumentException('Invalid OAuth state data');
        }

        $expiresAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['expires_at']);
        if ($expiresAt === false) {
            throw new \InvalidArgumentException('Invalid OAuth state expiration');
        }

        $provider = IntegrationProvider::tryFrom($data['provider']);
        if ($provider === null) {
            throw new \InvalidArgumentException("Invalid integration provider: {$data['provider']}");
        }

        return new self(
            userId: (int) $data['user_id'],
            provider: $provider,
            reconnectConnectionId: isset($data['reconnect_connection_id']) ? (int) $data['reconnect_connection_id'] : null,
            redirectTo: $data['redirect_to'] ?? null,
            expiresAt: $expiresAt,
        );
    }

    public function encode(): string
    {
        $data = [
            'user_id' => $this->userId,
            'provider' => $this->provider->value,
            'reconnect_connection_id' => $this->reconnectConnectionId,
            'redirect_to' => $this->redirectTo,
            'expires_at' => $this->expiresAt->format(\DateTimeInterface::ATOM),
        ];

        return base64_encode(json_encode($data));
    }

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() > $this->expiresAt;
    }

    public function isReconnect(): bool
    {
        return $this->reconnectConnectionId !== null;
    }
}
