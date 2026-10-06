<?php

namespace App\Mock\Errors;

use App\Mock\Unverified;
use RuntimeException;

/**
 * Every refusal the mock decides on. Rendered through ErrorEnvelope by the single
 * exception handler in bootstrap/app.php (R5); never rendered by a controller.
 */
final class MockError extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self(400, Unverified::ERROR_CODES['request_invalid'], $message);
    }

    public static function filterInvalid(string $message): self
    {
        return new self(400, Unverified::ERROR_CODES['filter_invalid'], $message);
    }

    public static function unauthorized(): self
    {
        return new self(401, Unverified::ERROR_CODES['unauthorized'], 'Missing or invalid X-API-KEY.');
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self(404, Unverified::ERROR_CODES['not_found'], $message);
    }

    public static function conflict(string $message, int $status = 409): self
    {
        return new self($status, $status === 409 ? Unverified::ERROR_CODES['conflict'] : Unverified::ERROR_CODES['request_invalid'], $message);
    }

    public static function withStatus(int $status, string $message): self
    {
        return match (true) {
            $status === 404 => self::notFound($message),
            $status === 409 => self::conflict($message),
            default => new self($status, Unverified::ERROR_CODES['request_invalid'], $message),
        };
    }
}
