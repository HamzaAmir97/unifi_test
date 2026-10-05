<?php

namespace App\Mock\Faults;

use App\Mock\Errors\MockError;

/**
 * A pending injected failure (R12): `{match: "METHOD path-glob", status?, times, delayMs?, timeout?}`.
 * The glob applies to the path after the connector base; `*` matches any run of characters.
 */
final readonly class FaultRule
{
    public function __construct(
        public string $id,
        public string $match,
        public ?int $status,
        public int $times,
        public int $delayMs = 0,
        public bool $timeout = false,
    ) {}

    public static function fromInput(array $input, int $maxDelayMs, string $id): self
    {
        $match = trim((string) ($input['match'] ?? ''));
        if (! preg_match('/^(\*|[A-Za-z]+)\s+\S+$/', $match)) {
            throw MockError::invalid('match must be "METHOD path-glob", for example "GET */hotspot/vouchers".');
        }

        $status = $input['status'] ?? null;
        if ($status !== null && (! is_int($status) || $status < 400 || $status > 599)) {
            throw MockError::invalid('status must be an integer between 400 and 599.');
        }

        $times = $input['times'] ?? 1;
        if (! is_int($times) || $times < 1) {
            throw MockError::invalid('times must be an integer of at least 1.');
        }

        $delay = $input['delayMs'] ?? 0;
        if (! is_int($delay) || $delay < 0 || $delay > $maxDelayMs) {
            throw MockError::invalid("delayMs must be an integer between 0 and {$maxDelayMs}.");
        }

        $timeout = (bool) ($input['timeout'] ?? false);
        if ($status === null && ! $timeout && $delay === 0) {
            throw MockError::invalid('A fault needs a status, a delay or timeout.');
        }

        return new self($id, $match, $status, $times, $delay, $timeout);
    }

    public static function fromArray(array $a): self
    {
        return new self($a['id'], $a['match'], $a['status'], $a['times'], $a['delayMs'] ?? 0, $a['timeout'] ?? false);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'match' => $this->match, 'status' => $this->status, 'times' => $this->times, 'delayMs' => $this->delayMs, 'timeout' => $this->timeout];
    }

    public function matches(string $method, string $relativePath): bool
    {
        [$m, $glob] = preg_split('/\s+/', $this->match, 2);
        if ($m !== '*' && strtoupper($m) !== strtoupper($method)) {
            return false;
        }
        $pattern = '#^'.implode('.*', array_map(fn ($p) => preg_quote($p, '#'), explode('*', $glob))).'$#';

        return preg_match($pattern, $relativePath) === 1;
    }

    public function decremented(): self
    {
        return new self($this->id, $this->match, $this->status, $this->times - 1, $this->delayMs, $this->timeout);
    }
}
