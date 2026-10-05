<?php

namespace App\Mock\Presenters;

use App\Enums\OptionalFieldMode;
use App\Mock\Manifest\Manifest;
use App\Mock\Settings\MockSettings;
use App\Models\MockResource;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Turns a stored row into the response object (R9).
 *
 * - derived fields are computed on every read by the subclass (`values()`), never stored;
 * - every required field of the manifest's response object is present, or this throws;
 * - an optional field with no value is omitted or null according to the G11 setting;
 * - keys come out required first, then optional, then anything else — the collection's order.
 */
abstract class Presenter
{
    public function __construct(
        protected readonly Manifest $manifest,
        protected readonly MockSettings $settings,
    ) {}

    /** @return array{0: string, 1: string} the manifest entry id and object path describing this type */
    abstract protected function fieldsFrom(): array;

    /** @return array<string, mixed> every value, stored and derived, keyed by response field */
    abstract protected function values(MockResource $resource): array;

    /** @return array<string, mixed> */
    public function present(MockResource $resource): array
    {
        return $this->shape($this->values($resource));
    }

    /**
     * @param  iterable<MockResource>  $resources
     * @return list<array<string, mixed>>
     */
    public function presentMany(iterable $resources): array
    {
        $out = [];
        foreach ($resources as $resource) {
            $out[] = $this->present($resource);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function shape(array $values): array
    {
        [$entry, $path] = $this->fieldsFrom();
        $fields = $this->manifest->responseFields($entry, $path);
        $mode = $this->settings->optionalFields();

        $out = [];
        foreach ($fields['required'] as $key) {
            if (! array_key_exists($key, $values)) {
                throw new LogicException(static::class." is missing required field '{$key}'.");
            }
            $out[$key] = $values[$key];
        }
        foreach ($fields['optional'] as $key) {
            $value = $values[$key] ?? null;
            if ($value !== null) {
                $out[$key] = $value;
            } elseif ($mode === OptionalFieldMode::Null) {
                $out[$key] = null;
            }
        }
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $out) && ! in_array($key, $fields['optional'], true)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** ISO-8601 UTC with milliseconds, as the collection writes timestamps. */
    protected static function iso(?string $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
