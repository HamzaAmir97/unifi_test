<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * FR-024 / SC-004. This test proves the absence of the live key and console id by
 * STRUCTURE and SHAPE, and contains neither value itself: a test that held them would
 * itself be the leak.
 */
class NoSecretsTest extends TestCase
{
    private const COLLECTION = 'postman/UniFi-Network-API.postman_collection.json';

    private function base(string $path = ''): string
    {
        return dirname(__DIR__, 2).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }

    private function json(string $path): array
    {
        return json_decode(file_get_contents($this->base($path)), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_collection_key_is_the_variable_reference(): void
    {
        $values = array_column($this->json(self::COLLECTION)['auth']['apikey'], 'value', 'key');

        $this->assertSame('{{apiKey}}', $values['value']);
        $this->assertSame('X-API-KEY', $values['key']);
    }

    public function test_the_collection_console_is_the_mock_value(): void
    {
        $vars = array_column($this->json(self::COLLECTION)['variable'], 'value', 'key');

        $this->assertSame('mock-console', $vars['consoleId']);
    }

    public function test_the_production_environment_template_is_empty(): void
    {
        $values = array_column($this->json('postman/unifi-production.postman_environment.json')['values'], 'value', 'key');

        $this->assertSame('', $values['apiKey']);
        $this->assertSame('', $values['consoleId']);
        $this->assertSame('', $values['siteId']);
    }

    public function test_the_two_environments_differ_only_in_the_four_values(): void
    {
        $mock = array_column($this->json('postman/unifi-mock.postman_environment.json')['values'], 'key');
        $prod = array_column($this->json('postman/unifi-production.postman_environment.json')['values'], 'key');

        sort($mock);
        sort($prod);
        $this->assertSame(['apiKey', 'baseUrl', 'consoleId', 'siteId'], $prod);
        // The mock environment may add control-plane variables, never anything else the
        // integration collection reads.
        $this->assertSame([], array_values(array_diff($prod, $mock)));
        $this->assertSame([], array_values(array_diff($mock, [...$prod, 'controlBase', 'clientId'])));
    }

    public function test_no_tracked_text_file_contains_a_key_shaped_string(): void
    {
        $files = [$this->base('.env.example'), $this->base('README.md')];
        foreach (['postman', 'resources', 'tests', 'config', 'app', 'routes', 'database'] as $dir) {
            if (! is_dir($this->base($dir))) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && preg_match('/\.(json|php|md|txt|yml|yaml|html)$/', $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $offenders = [];
        foreach (array_filter($files, 'is_file') as $file) {
            foreach ($this->keyShapedStrings(file_get_contents($file)) as $match) {
                $offenders[] = basename($file).': '.substr($match, 0, 4).'…('.strlen($match).')';
            }
        }

        $this->assertSame([], $offenders, 'Key-shaped strings found: '.implode(', ', $offenders));
    }

    public function test_the_shape_detector_catches_both_secret_shapes(): void
    {
        // Built at runtime so this file holds no secret-shaped literal.
        $fakeKey = str_repeat('aB3', 11);
        $fakeConsole = str_repeat('9A', 15).':'.str_repeat('7', 10);

        $this->assertCount(1, $this->keyShapedStrings("x {$fakeKey} y"));
        $this->assertCount(1, $this->keyShapedStrings("x {$fakeConsole} y"));
        $this->assertCount(0, $this->keyShapedStrings('multicastToUnicastConversionEnabled 2c0f8b06-04c3-a24d-020d-6ab1c7cd5761'));
    }

    /** A 32+ alphanumeric run mixing letters and digits (API-key shape), or HEX{20,}:DIGITS (console-id shape). */
    private function keyShapedStrings(string $text): array
    {
        preg_match_all('/[A-Za-z0-9]{32,}/', $text, $runs);
        preg_match_all('/[0-9A-Fa-f]{20,}:[0-9]{6,}/', $text, $consoles);

        $keys = array_filter($runs[0], fn ($r) => preg_match('/\d/', $r) && preg_match('/[A-Za-z]/', $r));

        return array_values(array_merge($keys, $consoles[0]));
    }
}
