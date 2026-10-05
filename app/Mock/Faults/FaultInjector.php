<?php

namespace App\Mock\Faults;

use App\Mock\Settings\MockSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores fault rules in `mock_settings.faults` and consumes them, one hit at a time.
 * Only integration routes consult it (control-plane paths never match).
 */
final class FaultInjector
{
    public function __construct(private readonly MockSettings $settings) {}

    /** @return list<FaultRule> */
    public function all(): array
    {
        return array_map(fn ($a) => FaultRule::fromArray($a), $this->settings->get(MockSettings::FAULTS, []));
    }

    public function add(array $input): FaultRule
    {
        $rule = FaultRule::fromInput($input, (int) config('unifi_mock.max_delay_ms'), (string) Str::uuid());
        DB::transaction(function () use ($rule) {
            $rules = $this->settings->get(MockSettings::FAULTS, []);
            $rules[] = $rule->toArray();
            $this->settings->put(MockSettings::FAULTS, $rules);
        });

        return $rule;
    }

    public function clear(): int
    {
        $n = count($this->all());
        $this->settings->forget(MockSettings::FAULTS);

        return $n;
    }

    /** The first matching rule, with its count decremented (and removed at zero). */
    public function consume(string $method, string $relativePath): ?FaultRule
    {
        if (str_starts_with($relativePath, '/__mock')) {
            return null;
        }

        return DB::transaction(function () use ($method, $relativePath) {
            $rules = $this->all();
            foreach ($rules as $i => $rule) {
                if ($rule->matches($method, $relativePath)) {
                    $left = $rule->decremented();
                    if ($left->times > 0) {
                        $rules[$i] = $left;
                    } else {
                        unset($rules[$i]);
                    }
                    $this->settings->put(MockSettings::FAULTS, array_values(array_map(fn (FaultRule $r) => $r->toArray(), $rules)));

                    return $rule;
                }
            }

            return null;
        });
    }

    /** "/v1/sites/…" from the full request path. */
    public function relativePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if (preg_match('#^/v1/connector/consoles/[^/]+/proxy/network/integration(/.*)$#', $path, $m)) {
            return $m[1];
        }

        return $path;
    }
}
