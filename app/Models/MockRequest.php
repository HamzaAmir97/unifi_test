<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MockRequest extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['request_id', 'method', 'path', 'query', 'body', 'api_key_masked', 'status', 'duration_ms', 'warnings', 'created_at'];

    protected function casts(): array
    {
        return ['query' => 'array', 'body' => 'array', 'warnings' => 'array', 'created_at' => 'datetime:Y-m-d\TH:i:s.v\Z'];
    }
}
