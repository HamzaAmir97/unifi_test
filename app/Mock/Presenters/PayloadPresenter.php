<?php

namespace App\Mock\Presenters;

use App\Mock\Manifest\Manifest;
use App\Mock\Settings\MockSettings;
use App\Models\MockResource;

/**
 * For types whose response is simply `id` + the stored fields, shaped against one manifest
 * object: firewall zones and policies, ACL rules, traffic lists, reference data, switching.
 * Internal keys in the payload never leak, because shaping keeps only the field set.
 */
class PayloadPresenter extends Presenter
{
    /** @param list<string> $extra */
    public function __construct(
        Manifest $manifest,
        MockSettings $settings,
        private readonly string $entry,
        private readonly string $path = '',
        private readonly array $extra = [],
        private readonly bool $withId = true,
    ) {
        parent::__construct($manifest, $settings);
    }

    /** @param list<string> $extra */
    public static function for(string $entry, string $path = '', array $extra = [], bool $withId = true): self
    {
        return new self(app(Manifest::class), app(MockSettings::class), $entry, $path, $extra, $withId);
    }

    protected function fieldsFrom(): array
    {
        return [$this->entry, $this->path];
    }

    protected function extraKeys(): array
    {
        return $this->extra;
    }

    protected function values(MockResource $resource): array
    {
        $values = $this->withId ? ['id' => $resource->uuid] + $resource->payload : $resource->payload;
        if (array_key_exists('position', $resource->getAttributes()) && ! array_key_exists('index', $values)) {
            $values['index'] = $resource->position; // server-assigned order (firewall policies, ACL rules)
        }

        return $values;
    }
}
