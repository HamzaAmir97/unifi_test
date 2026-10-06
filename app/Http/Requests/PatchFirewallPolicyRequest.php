<?php

namespace App\Http\Requests;

use App\Mock\Manifest\Manifest;
use App\Mock\Manifest\ManifestRules;

/**
 * PATCH merges (spec US3 scenario 5). The collection documents only `loggingEnabled`, so
 * every field of the full policy body is accepted here but none is required.
 */
class PatchFirewallPolicyRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return 'update-firewall-policy';
    }

    public function rules(): array
    {
        $rules = app(ManifestRules::class)->rules(app(Manifest::class)->requestFields($this->entry()));

        return array_map(function (array $r) {
            $r = array_map(fn ($rule) => in_array($rule, ['required', 'present'], true) ? 'sometimes' : $rule, $r);

            return array_values(array_filter($r, fn ($rule) => ! str_starts_with($rule, 'required_with:')));
        }, $rules) + ['loggingEnabled' => ['sometimes', 'boolean']];
    }
}
