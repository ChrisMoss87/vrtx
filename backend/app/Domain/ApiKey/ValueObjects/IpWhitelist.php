<?php

declare(strict_types=1);

namespace App\Domain\ApiKey\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class IpWhitelist implements JsonSerializable
{
    /**
     * @param array<string> $ips
     */
    public function __construct(
        private array $ips = [],
    ) {
        foreach ($ips as $ip) {
            if (!$this->isValidIpOrCidr($ip)) {
                throw new InvalidArgumentException("Invalid IP address or CIDR: {$ip}");
            }
        }
    }

    /**
     * @param array<string> $ips
     */
    public static function fromArray(array $ips): self
    {
        return new self(array_values(array_unique(array_filter($ips))));
    }

    public static function allowAll(): self
    {
        return new self([]);
    }

    /**
     * @return array<string>
     */
    public function toArray(): array
    {
        return $this->ips;
    }

    /**
     * Check if an IP address is allowed.
     */
    public function allows(string $ip): bool
    {
        // Empty whitelist means all IPs are allowed
        if (empty($this->ips)) {
            return true;
        }

        foreach ($this->ips as $allowed) {
            if ($this->ipMatches($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this whitelist restricts IPs.
     */
    public function isRestricted(): bool
    {
        return !empty($this->ips);
    }

    /**
     * Check if this whitelist allows all IPs.
     */
    public function allowsAll(): bool
    {
        return empty($this->ips);
    }

    /**
     * Add an IP and return a new instance.
     */
    public function with(string $ip): self
    {
        if (!$this->isValidIpOrCidr($ip)) {
            throw new InvalidArgumentException("Invalid IP address or CIDR: {$ip}");
        }

        if (in_array($ip, $this->ips, true)) {
            return $this;
        }

        $ips = $this->ips;
        $ips[] = $ip;

        return new self($ips);
    }

    /**
     * Remove an IP and return a new instance.
     */
    public function without(string $ip): self
    {
        return new self(array_filter($this->ips, fn ($i) => $i !== $ip));
    }

    public function count(): int
    {
        return count($this->ips);
    }

    public function jsonSerialize(): array
    {
        return $this->ips;
    }

    public function __toString(): string
    {
        if (empty($this->ips)) {
            return 'All IPs allowed';
        }

        return implode(', ', $this->ips);
    }

    /**
     * Validate an IP address or CIDR notation.
     */
    private function isValidIpOrCidr(string $ip): bool
    {
        // Check for CIDR notation
        if (str_contains($ip, '/')) {
            [$address, $prefix] = explode('/', $ip, 2);

            if (!is_numeric($prefix)) {
                return false;
            }

            $prefix = (int) $prefix;

            // IPv4 CIDR
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $prefix >= 0 && $prefix <= 32;
            }

            // IPv6 CIDR
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $prefix >= 0 && $prefix <= 128;
            }

            return false;
        }

        // Plain IP address
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check if an IP matches an allowed IP or CIDR range.
     */
    private function ipMatches(string $ip, string $allowed): bool
    {
        // Exact match
        if ($ip === $allowed) {
            return true;
        }

        // CIDR match
        if (str_contains($allowed, '/')) {
            return $this->ipInCidr($ip, $allowed);
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;

        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - $mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // IPv6 - simplified check
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);

            if ($ipBin === false || $subnetBin === false) {
                return false;
            }

            $bytes = (int) ($mask / 8);
            $bits = $mask % 8;

            for ($i = 0; $i < $bytes; $i++) {
                if ($ipBin[$i] !== $subnetBin[$i]) {
                    return false;
                }
            }

            if ($bits > 0 && $bytes < 16) {
                $maskByte = 0xFF << (8 - $bits);

                return (ord($ipBin[$bytes]) & $maskByte) === (ord($subnetBin[$bytes]) & $maskByte);
            }

            return true;
        }

        return false;
    }
}
