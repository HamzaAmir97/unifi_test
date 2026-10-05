<?php

namespace Tests\Unit\Manifest;

use App\Mock\Manifest\FieldSets;
use App\Mock\Manifest\ManifestBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Pins the manifest to the measurements in research R1 and spec fact 7. Built from the
 * committed sanitized collection, then compared with the committed manifest.
 */
class ManifestBuilderTest extends TestCase
{
    private static ?array $manifest = null;

    private function manifest(): array
    {
        if (self::$manifest === null) {
            $root = dirname(__DIR__, 3);
            $collection = json_decode(file_get_contents($root.'/postman/UniFi-Network-API.postman_collection.json'), true);
            self::$manifest = (new ManifestBuilder)->build($collection);
        }

        return self::$manifest;
    }

    private function entry(string $id): array
    {
        foreach ($this->manifest()['entries'] as $e) {
            if ($e['id'] === $id) {
                return $e;
            }
        }
        $this->fail("No entry {$id}");
    }

    public function test_exactly_73_entries_in_13_groups(): void
    {
        $entries = $this->manifest()['entries'];

        $this->assertCount(73, $entries);
        $this->assertSame(
            ['Application Info' => 1, 'Sites' => 1, 'UniFi Devices' => 8, 'Clients' => 3, 'Networks' => 6, 'WiFi Broadcasts' => 5,
                'Hotspot' => 5, 'Firewall' => 13, 'Access Control (ACL Rules)' => 7, 'Switching' => 6, 'DNS Policies' => 5,
                'Traffic Matching Lists' => 5, 'Supporting Resources' => 8],
            array_count_values(array_column($entries, 'group')),
        );
    }

    public function test_the_committed_manifest_is_what_the_collection_produces(): void
    {
        $committed = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/manifest.json'), true);

        $this->assertSame($this->manifest(), $committed, 'Run php artisan mock:build-manifest');
    }

    public function test_filterable_property_counts_match_the_collection(): void
    {
        $expected = [
            'list-local-sites' => 3, 'list-devices-pending-adoption' => 8, 'list-adopted-devices' => 11, 'list-connected-clients' => 7,
            'list-networks' => 7, 'list-wifi-broadcasts' => 13, 'list-vouchers' => 13, 'delete-vouchers' => 13,
            'list-firewall-policies' => 5, 'list-firewall-zones' => 4, 'list-acl-rules' => 23, 'list-lags' => 7,
            'list-mc-lag-domains' => 4, 'list-switch-stacks' => 4, 'list-dns-policies' => 17, 'list-traffic-matching-lists' => 2,
            'list-countries' => 2, 'list-dpi-applications' => 2, 'list-dpi-application-categories' => 2, 'list-device-tags' => 3,
            'list-radius-profiles' => 3, 'list-vpn-servers' => 5, 'list-site-to-site-vpn-tunnels' => 5, 'list-wan-interfaces' => 0,
        ];
        foreach ($expected as $id => $count) {
            $this->assertCount($count, $this->entry($id)['filter']['properties'], $id);
        }
    }

    public function test_status_codes_and_content_types(): void
    {
        $entries = collect($this->manifest()['entries']);

        $this->assertSame(8, $entries->where('status', 201)->count());
        $this->assertSame(200, $this->entry('adopt-devices')['status'], 'adopt is the only create answering 200');
        $this->assertSame(10, $entries->where('contentType', 'text/plain')->count());
        $this->assertSame('application/json', $this->entry('delete-vouchers')['contentType']);
        $this->assertSame('application/json', $this->entry('delete-voucher')['contentType']);
        $this->assertSame('application/json', $this->entry('execute-client-action')['contentType']);
    }

    public function test_query_facts(): void
    {
        $this->assertSame(100, $this->entry('list-vouchers')['defaultLimit']);
        $this->assertSame(25, $this->entry('list-networks')['defaultLimit']);
        $this->assertTrue($this->entry('list-local-sites')['filter']['disabled']);
        $this->assertTrue($this->entry('delete-vouchers')['filter']['required']);
        $this->assertCount(23, collect($this->manifest()['entries'])->where('isList', true));

        $ordering = collect($this->entry('get-user-defined-firewall-policy-ordering')['query'])->where('required', true)->pluck('key')->all();
        $this->assertSame(['sourceFirewallZoneId', 'destinationFirewallZoneId'], $ordering);
    }

    public function test_device_tags_has_a_table_without_a_parameter_and_wans_has_neither(): void
    {
        $tags = $this->entry('list-device-tags');
        $this->assertTrue($tags['flags']['tableWithoutParameter']);
        $this->assertFalse($tags['filter']['parameter']);

        $wans = $this->entry('list-wan-interfaces');
        $this->assertFalse($wans['flags']['tableWithoutParameter']);
        $this->assertFalse($wans['filter']['parameter']);
        $this->assertSame([], $wans['filter']['properties']);
    }

    public function test_22_examples_are_truncated(): void
    {
        $this->assertSame(22, collect($this->manifest()['entries'])->where('flags.truncatedExample', true)->count());
    }

    public function test_field_sets_match_spec_fact_7(): void
    {
        $voucher = $this->entry('get-voucher-details')['response']['fields'][''];
        $this->assertSame(['authorizedGuestCount', 'code', 'createdAt', 'expired', 'id', 'name', 'timeLimitMinutes'], $voucher['required']);
        $this->assertSame(['activatedAt', 'authorizedGuestLimit', 'dataUsageLimitMBytes', 'expiresAt', 'rxRateLimitKbps', 'txRateLimitKbps'], $voucher['optional']);

        $generate = $this->entry('generate-vouchers')['request']['fields'][''];
        $this->assertSame(['name', 'timeLimitMinutes'], $generate['required']);

        $client = $this->entry('get-connected-client-details')['response']['fields'][''];
        $this->assertSame(['access', 'id', 'name', 'type'], $client['required']);
        $this->assertSame(['connectedAt', 'ipAddress'], $client['optional']);

        $this->assertContains('dhcpGuarding', $this->entry('get-network-details')['response']['fields']['']['optional']);
        $this->assertSame(['connectionStateFilter', 'description', 'ipsecFilter', 'schedule'], $this->entry('get-firewall-policy')['response']['fields']['']['optional']);
        // Fact 7's ACL set is the CREATE BODY (index optional there); the response requires index.
        $this->assertSame(['description', 'destinationFilter', 'enforcingDeviceFilter', 'index', 'sourceFilter'], $this->entry('create-acl-rule')['request']['fields']['']['optional']);
        $this->assertContains('index', $this->entry('get-acl-rule')['response']['fields']['']['required']);
    }

    public function test_request_placeholders_become_types(): void
    {
        $types = $this->entry('generate-vouchers')['request']['fields']['']['types'];

        $this->assertSame('string', $types['name']);
        $this->assertSame('integer', $types['timeLimitMinutes']);
        $this->assertSame('integer', $types['count']);
    }

    public function test_observed_enum_values(): void
    {
        $observed = $this->manifest()['observedEnums'];

        $this->assertContains('PENDING_ADOPTION', $observed['state']);
        $this->assertSame(['ALLOW', 'BLOCK'], $observed['action']);
        $this->assertSame(['MATCH_ENCRYPTED', 'MATCH_NOT_ENCRYPTED'], $observed['ipsecFilter']);
        $this->assertArrayNotHasKey('model', $observed);
    }

    public function test_field_order_split(): void
    {
        $this->assertSame(['required' => ['b', 'c'], 'optional' => ['a', 'd'], 'noDescent' => false], FieldSets::split(['b', 'c', 'a', 'd']));
        $this->assertSame(['required' => ['enabled', 'type'], 'optional' => [], 'noDescent' => true], FieldSets::split(['enabled', 'type']));
    }
}
