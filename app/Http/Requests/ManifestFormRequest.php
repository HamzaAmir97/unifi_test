<?php

namespace App\Http\Requests;

use App\Mock\Manifest\Manifest;
use App\Mock\Manifest\ManifestRules;
use App\Mock\Validation\EnumWarden;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A request body validated by the field sets the manifest derived from the collection
 * (FR-012a). Subclasses name their manifest entry and may add rules the collection
 * cannot express (uniqueness, existence).
 *
 * A failure throws ValidationException, which the single handler renders as 400.
 */
abstract class ManifestFormRequest extends FormRequest
{
    abstract protected function entry(): string;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_replace(
            app(ManifestRules::class)->rules(app(Manifest::class)->requestFields($this->entry())),
            $this->extraRules(),
        );
    }

    /** @return array<string, mixed> */
    protected function extraRules(): array
    {
        return [];
    }

    /** FR-026: unknown type/action values warn, or refuse in strict mode. */
    public function after(): array
    {
        return [fn (Validator $validator) => app(EnumWarden::class)->inspect($this, $this->all(), $validator)];
    }
}
