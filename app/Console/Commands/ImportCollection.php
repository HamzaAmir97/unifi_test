<?php

namespace App\Console\Commands;

use App\Mock\Manifest\CollectionSanitizer;
use Illuminate\Console\Command;

/**
 * Copies the UniFi collection into postman/, sanitized (R13). The source file is never
 * copied verbatim, and nothing is written if a secret could not be replaced.
 *
 * It also writes the request-only snapshot that PostmanRequestsIntactTest compares
 * against, so later edits to the collection (pm.test scripts) can never alter a request.
 */
class ImportCollection extends Command
{
    public const TARGET = 'postman/UniFi-Network-API.postman_collection.json';

    public const SNAPSHOT = 'tests/Fixtures/collection-requests.snapshot.json';

    protected $signature = 'mock:import-collection {path : The collection exported from Postman}';

    protected $description = 'Import the UniFi Network API collection with its API key and console id removed';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("No file at {$path}");

            return self::FAILURE;
        }

        $sanitizer = new CollectionSanitizer(config('unifi_mock.console_id'));
        $clean = $sanitizer->sanitize(file_get_contents($path));

        $decoded = json_decode($clean, true, flags: JSON_THROW_ON_ERROR);
        if ($sanitizer->apiKeyValue($decoded) !== CollectionSanitizer::API_KEY_REFERENCE
            || $sanitizer->consoleIdValue($decoded) !== config('unifi_mock.console_id')) {
            $this->error('Sanitization did not replace both values; nothing written.');

            return self::FAILURE;
        }

        file_put_contents(base_path(self::TARGET), $clean);
        @mkdir(dirname(base_path(self::SNAPSHOT)), 0777, true);
        file_put_contents(
            base_path(self::SNAPSHOT),
            json_encode(self::requestProjection($decoded), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        $this->info('Written '.self::TARGET.' (API key and console id replaced) and '.self::SNAPSHOT.'.');

        return self::SUCCESS;
    }

    /**
     * Every request with its examples, without test/prerequest scripts (R16: requests only).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function requestProjection(array $collection): array
    {
        $out = [];
        foreach ($collection['item'] as $group) {
            foreach ($group['item'] ?? [] as $item) {
                if (! isset($item['request'])) {
                    continue; // folders added later (e.g. the read-only folder) are not part of the contract
                }
                $out[] = [
                    'group' => $group['name'],
                    'name' => $item['name'],
                    'request' => $item['request'],
                    'response' => $item['response'] ?? [],
                ];
            }
        }

        return $out;
    }
}
