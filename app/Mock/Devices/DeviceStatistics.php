<?php

namespace App\Mock\Devices;

use App\Models\MockResource;
use Carbon\CarbonImmutable;

/**
 * "Latest statistics" for a device, derived on every read: stable within a minute, varying
 * between minutes, percentages always within 0–100 (the collection's example says 3984%).
 * Values are invented (UNVERIFIED).
 */
final class DeviceStatistics
{
    public static function for(MockResource $device): array
    {
        $now = CarbonImmutable::now()->utc();
        $seed = crc32($device->uuid.$now->format('YmdHi'));
        $pct = fn (int $salt, float $min, float $max) => round($min + (crc32($seed.':'.$salt) % 1000) / 1000 * ($max - $min), 1);
        $adoptedAt = CarbonImmutable::parse($device->payload['adoptedAt'] ?? $now->subDay()->toIso8601String());

        $radios = array_map(fn ($r, $i) => [
            'frequencyGHz' => $r['frequencyGHz'],
            'txRetriesPct' => $pct(10 + $i, 0.5, 12.0),
        ], $device->payload['interfaces']['radios'] ?? [], array_keys($device->payload['interfaces']['radios'] ?? []));

        return [
            'interfaces' => ['radios' => $radios],
            'cpuUtilizationPct' => $pct(1, 3.0, 45.0),
            'lastHeartbeatAt' => $now->subSeconds(10)->format('Y-m-d\TH:i:s.v\Z'),
            'loadAverage15Min' => $pct(2, 0.1, 1.5),
            'loadAverage1Min' => $pct(3, 0.1, 2.0),
            'loadAverage5Min' => $pct(4, 0.1, 1.8),
            'memoryUtilizationPct' => $pct(5, 20.0, 80.0),
            'nextHeartbeatAt' => $now->addSeconds(20)->format('Y-m-d\TH:i:s.v\Z'),
            'uplink' => ['rxRateBps' => 1000 * (int) $pct(6, 100, 90000), 'txRateBps' => 1000 * (int) $pct(7, 50, 40000)],
            'uptimeSec' => max(0, (int) $adoptedAt->diffInSeconds($now)),
        ];
    }
}
