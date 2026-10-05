<?php

namespace Tests\Unit\Manifest;

use App\Mock\Manifest\Manifest;
use App\Mock\Manifest\ManifestRules;
use PHPUnit\Framework\TestCase;

class ManifestRulesTest extends TestCase
{
    private function rulesFor(string $entry): array
    {
        $manifest = new Manifest(dirname(__DIR__, 3).'/resources/manifest.json');

        return (new ManifestRules)->rules($manifest->requestFields($entry));
    }

    public function test_the_voucher_generate_body(): void
    {
        $rules = $this->rulesFor('generate-vouchers');

        $this->assertSame(['required', 'string'], $rules['name']);
        $this->assertSame(['required', 'integer', 'min:1'], $rules['timeLimitMinutes']);
        $this->assertSame(['sometimes', 'nullable', 'integer', 'min:1'], $rules['count']);
        $this->assertSame(['sometimes', 'nullable', 'integer', 'min:1'], $rules['dataUsageLimitMBytes']);
    }

    public function test_nested_required_keys_follow_their_parent(): void
    {
        $rules = $this->rulesFor('create-firewall-policy');

        $this->assertSame(['required', 'uuid'], $rules['source.zoneId']);
        $this->assertSame(['required_with:schedule', 'string'], $rules['schedule.mode'], 'schedule is optional, so its keys are required only with it');
    }

    public function test_map_like_objects_are_not_validated_inside(): void
    {
        $rules = $this->rulesFor('create-wifi-broadcast');

        $this->assertArrayNotHasKey('basicDataRateKbpsByFrequencyGHz.5', $rules);
        $this->assertArrayHasKey('basicDataRateKbpsByFrequencyGHz', $rules);
    }
}
