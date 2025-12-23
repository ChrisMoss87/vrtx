<?php

declare(strict_types=1);

namespace App\Domain\Email\DTOs;

final readonly class OAuthCallbackDTO
{
    public function __construct(
        public ?string $code,
        public ?string $state,
        public ?string $error,
        public ?string $errorDescription,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'] ?? null,
            state: $data['state'] ?? null,
            error: $data['error'] ?? null,
            errorDescription: $data['error_description'] ?? null,
        );
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function isValid(): bool
    {
        return !$this->hasError() && $this->code !== null && $this->state !== null;
    }

    public function getErrorMessage(): string
    {
        if (!$this->hasError()) {
            return '';
        }

        return $this->errorDescription ?? match ($this->error) {
            'access_denied' => 'You denied access to your email account',
            'invalid_request' => 'The authorization request was invalid',
            'unauthorized_client' => 'The application is not authorized',
            'server_error' => 'The authorization server encountered an error',
            'temporarily_unavailable' => 'The authorization server is temporarily unavailable',
            default => "Authorization failed: {$this->error}",
        };
    }
}
