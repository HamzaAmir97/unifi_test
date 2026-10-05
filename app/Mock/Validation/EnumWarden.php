<?php

namespace App\Mock\Validation;

use App\Http\Middleware\RecordRequest;
use App\Mock\Manifest\Manifest;
use App\Mock\Settings\MockSettings;
use App\Mock\Unverified;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

/**
 * FR-026 / D2. A request value for a `type`-like field that the collection never shows
 * (observedEnums) and the mock does not itself use (Unverified::INVENTED_ENUM_VALUES) is
 * accepted with an `unknown_enum` warning in the request log — or refused with 400 when
 * strict mode is on. It never fails silently.
 */
final class EnumWarden
{
    public function __construct(
        private readonly Manifest $manifest,
        private readonly MockSettings $settings,
    ) {}

    public function inspect(Request $request, array $body, ?Validator $validator = null): void
    {
        foreach ($this->unknown($body) as [$path, $value]) {
            if ($this->settings->strict() && $validator !== null) {
                $validator->errors()->add($path, "Unknown value '{$value}' for {$path} (strict mode).");
            } else {
                RecordRequest::warn($request, 'unknown_enum', ['field' => $path, 'value' => $value]);
            }
        }
    }

    /** @return list<array{0: string, 1: string}> */
    public function unknown(array $body, string $prefix = ''): array
    {
        $found = [];
        foreach ($body as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $field = is_int($key) ? $this->lastSegment($prefix) : (string) $key;

            if (is_array($value)) {
                $found = [...$found, ...$this->unknown($value, $path)];
            } elseif (is_string($value) && in_array($field, Unverified::ENUM_CHECKED_FIELDS, true) && ! $this->known($field, $value)) {
                $found[] = [$path, $value];
            }
        }

        return $found;
    }

    public function known(string $field, string $value): bool
    {
        $observed = $this->manifest->observedEnums()[$field] ?? [];
        $invented = Unverified::INVENTED_ENUM_VALUES[$field] ?? [];

        return in_array($value, $observed, true) || in_array($value, $invented, true);
    }

    private function lastSegment(string $path): string
    {
        $parts = explode('.', $path);

        return (string) end($parts);
    }
}
