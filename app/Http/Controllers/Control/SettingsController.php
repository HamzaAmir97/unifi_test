<?php

namespace App\Http\Controllers\Control;

use App\Enums\OptionalFieldMode;
use App\Http\Controllers\Controller;
use App\Mock\Settings\MockSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** POST /__mock/settings {strict?, optionalFields?} — D2 strict mode, G11 absent vs null. */
class SettingsController extends Controller
{
    public function __invoke(Request $request, MockSettings $settings): JsonResponse
    {
        $input = $request->validate([
            'strict' => ['sometimes', 'boolean'],
            'optionalFields' => ['sometimes', Rule::enum(OptionalFieldMode::class)],
        ]);

        if (array_key_exists('strict', $input)) {
            $settings->put(MockSettings::STRICT, (bool) $input['strict']);
        }
        if (array_key_exists('optionalFields', $input)) {
            $settings->put(MockSettings::OPTIONAL_FIELDS, $input['optionalFields']);
        }

        return new JsonResponse(['strict' => $settings->strict(), 'optionalFields' => $settings->optionalFields()->value]);
    }
}
