<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

use Domain\Shared\Exceptions\InvalidEmailException;

final readonly class Email
{
    private function __construct(
        private string $value
    ) {
    }

    public static function from(string $email): self
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email address: {$email}");
        }

        return new self($email);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function domain(): string
    {
        return explode('@', $this->value)[1];
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
