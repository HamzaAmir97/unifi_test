<?php

namespace App\Http\Requests;

class AdoptDeviceRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return 'adopt-devices';
    }
}
