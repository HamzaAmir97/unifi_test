<?php

namespace App\Http\Requests;

use App\Mock\Unverified;

class GenerateVouchersRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return 'generate-vouchers';
    }

    protected function extraRules(): array
    {
        return ['count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.Unverified::MAX_VOUCHERS_PER_GENERATE]];
    }
}
