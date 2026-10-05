<?php

namespace Tests\Unit\Manifest;

use App\Mock\Manifest\CollectionSanitizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CollectionSanitizerTest extends TestCase
{
    /** Synthetic values built at runtime: the real ones never appear in this repository. */
    private function fakeKey(): string
    {
        return str_repeat('Fk9', 13);
    }

    private function fakeConsole(): string
    {
        return str_repeat('C0', 15).':'.str_repeat('4', 10);
    }

    private function collection(): string
    {
        return json_encode([
            'info' => ['name' => 'UniFi Network API'],
            'item' => [['name' => 'Application Info', 'item' => [['name' => 'Get Application Info', 'request' => ['method' => 'GET', 'url' => ['raw' => '{{baseUrl}}/v1/info']]]]]],
            'auth' => ['type' => 'apikey', 'apikey' => [
                ['key' => 'value', 'value' => $this->fakeKey(), 'type' => 'string'],
                ['key' => 'key', 'value' => 'X-API-KEY', 'type' => 'string'],
            ]],
            'variable' => [
                ['key' => 'consoleId', 'value' => $this->fakeConsole()],
                ['key' => 'baseUrl', 'value' => 'https://api.ui.com/v1/connector/consoles/{{consoleId}}/proxy/network/integration'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function test_it_replaces_only_the_key_and_the_console(): void
    {
        $original = $this->collection();
        $clean = (new CollectionSanitizer('mock-console'))->sanitize($original);

        $this->assertStringNotContainsString($this->fakeKey(), $clean);
        $this->assertStringNotContainsString($this->fakeConsole(), $clean);

        $expected = json_decode($original, true);
        $expected['auth']['apikey'][0]['value'] = '{{apiKey}}';
        $expected['variable'][0]['value'] = 'mock-console';
        $this->assertSame($expected, json_decode($clean, true));
    }

    public function test_every_other_byte_is_preserved(): void
    {
        $original = $this->collection();
        $clean = (new CollectionSanitizer('mock-console'))->sanitize($original);

        $this->assertSame(
            str_replace(['"'.$this->fakeKey().'"', '"'.$this->fakeConsole().'"'], ['"{{apiKey}}"', '"mock-console"'], $original),
            $clean,
        );
    }

    public function test_it_is_idempotent(): void
    {
        $sanitizer = new CollectionSanitizer('mock-console');
        $once = $sanitizer->sanitize($this->collection());

        $this->assertSame($once, $sanitizer->sanitize($once));
    }

    public function test_it_refuses_when_the_key_appears_more_than_once(): void
    {
        $twice = str_replace('"Get Application Info"', '"'.$this->fakeKey().'"', $this->collection());

        $this->expectException(RuntimeException::class);
        (new CollectionSanitizer('mock-console'))->sanitize($twice);
    }
}
