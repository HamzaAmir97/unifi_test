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
 * - keys come out required first, then optional (the collection's order), then the type's
 *   declared extra keys; internal values (e.g. a client's voucher link) never leak.
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

    /** Keys outside the manifest's field set that this type still returns (e.g. a hotspot configuration). @return list<string> */
    protected function extraKeys(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function present(MockResource $resource): array
    {
        [$entry, $path] = $this->fieldsFrom();

        return $this->presentAs($resource, $entry, $path);
    }

    /** Shaped against another manifest object, e.g. the summary a list returns. @return array<string, mixed> */
    public function presentAs(MockResource $resource, string $entry, string $path): array
    {
        return $this->shape($this->values($resource), $entry, $path);
    }

    /**
     * What a filter sees: every value, stored and derived, before shaping — so a property the
     * collection declares filterable is filterable even when the response does not show it.
     *
     * @return array<string, mixed>
     */
    public function view(MockResource $resource): array
    {
        return $this->values($resource);
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
    protected function shape(array $values, string $entry, string $path): array
    {
        $fields = $this->manifest->responseFields($entry, $path);
        $mode = $this->settings->optionalFields();

        $out = [];
        foreach ($fields['required'] as $key) {
            if (! array_key_exists($key, $values)) {
                throw new LogicException(static::class." is missing required field '{$key}'.");
            }
            $out[$key] = $values[$key];
        }
        foreach ([...$fields['optional'], ...$this->extraKeys()] as $key) {
            if (array_key_exists($key, $out)) {
                continue;
            }
            $value = $values[$key] ?? null;
            if ($value !== null) {
                $out[$key] = $value;
            } elseif ($mode === OptionalFieldMode::Null) {
                $out[$key] = null;
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
