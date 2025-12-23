<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Shared\Contracts\LoggerInterface;
use Illuminate\Support\Facades\Log;

/**
 * Laravel implementation of LoggerInterface.
 */
final class LaravelLogger implements LoggerInterface
{
    public function emergency(string $message, array $context = []): void
    {
        Log::emergency($message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        Log::alert($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        Log::notice($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        Log::log($level, $message, $context);
    }
}
