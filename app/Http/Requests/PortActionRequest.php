<?php

namespace App\Http\Requests;

class PortActionRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return 'execute-port-action';
    }
}
