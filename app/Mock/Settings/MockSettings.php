<?php

namespace App\Mock\Settings;

use App\Enums\OptionalFieldMode;
use App\Models\MockSetting;

/**
 * Typed access to `mock_settings`. A key with no row falls back to config, so a fresh
 * database behaves exactly as `.env` says.
 */
final class MockSettings
{
    public const CLOCK = 'clock';

    public const FAULTS = 'faults';

    public const STRICT = 'strict';

    public const OPTIONAL_FIELDS = 'optional_fields';

    public function get(string $key, mixed $default = null): mixed
    {
        $row = MockSetting::query()->find($key);

        return $row === null ? $default : $row->value;
    }

    public function put(string $key, mixed $value): void
    {
        MockSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function forget(string $key): void
    {
        MockSetting::query()->whereKey($key)->delete();
    }

    public function strict(): bool
    {
        return (bool) $this->get(self::STRICT, config('unifi_mock.strict'));
    }

    public function optionalFields(): OptionalFieldMode
    {
        return OptionalFieldMode::tryFrom((string) $this->get(self::OPTIONAL_FIELDS, config('unifi_mock.optional_fields')))
            ?? OptionalFieldMode::Omit;
    }

    /** Back to the `.env` defaults: no clock, no faults, no overrides. */
    public function restoreDefaults(): void
    {
        MockSetting::query()->delete();
    }
}
