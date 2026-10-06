<?php

namespace App\Http\Requests;

class ClientActionRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return 'execute-client-action';
    }
}
