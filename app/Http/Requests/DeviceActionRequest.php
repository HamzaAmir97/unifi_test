<?php

namespace App\Http\Requests;

class DeviceActionRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return 'execute-adopted-device-action';
    }
}
