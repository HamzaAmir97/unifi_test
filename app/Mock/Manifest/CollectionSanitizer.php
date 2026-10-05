<?php

namespace App\Mock\Manifest;

use RuntimeException;

/**
 * Removes the two secrets the collection arrives with (R13, FR-021, FR-024):
 * the API key value becomes "{{apiKey}}" and the consoleId variable becomes the mock's.
 *
 * The replacement is done on the raw text, at the exact JSON literal located through
 * the decoded structure, so every other byte of the file is preserved.
 */
final class CollectionSanitizer
{
    public const API_KEY_REFERENCE = '{{apiKey}}';

    public function __construct(private readonly string $mockConsoleId = 'mock-console') {}

    public function sanitize(string $raw): string
    {
        $collection = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        $key = $this->apiKeyValue($collection);
        $console = $this->consoleIdValue($collection);

        if ($key !== self::API_KEY_REFERENCE) {
            $raw = $this->replaceOnce($raw, $key, self::API_KEY_REFERENCE, 'auth.apikey value');
        }
        if ($console !== $this->mockConsoleId) {
            $raw = $this->replaceOnce($raw, $console, $this->mockConsoleId, 'consoleId variable');
        }

        return $raw;
    }

    public function apiKeyValue(array $collection): string
    {
        foreach ($collection['auth']['apikey'] ?? [] as $entry) {
            if (($entry['key'] ?? null) === 'value') {
                return (string) $entry['value'];
            }
        }
        throw new RuntimeException('The collection has no auth.apikey value entry.');
    }

    public function consoleIdValue(array $collection): string
    {
        foreach ($collection['variable'] ?? [] as $variable) {
            if (($variable['key'] ?? null) === 'consoleId') {
                return (string) $variable['value'];
            }
        }
        throw new RuntimeException('The collection has no consoleId variable.');
    }

    private function replaceOnce(string $raw, string $from, string $to, string $what): string
    {
        $needle = $this->literal($from);
        $count = substr_count($raw, $needle);
        if ($count !== 1) {
            throw new RuntimeException("Expected the {$what} exactly once as a JSON string, found {$count}; refusing to guess.");
        }

        return str_replace($needle, $this->literal($to), $raw);
    }

    private function literal(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
