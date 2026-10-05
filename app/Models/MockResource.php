<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One stored resource of any type. The API serves `uuid` as `id`; `id` is internal.
 *
 * @property int $id
 * @property string $type
 * @property string|null $site_uuid
 * @property string $uuid
 * @property array<string, mixed> $payload
 * @property int $position
 */
class MockResource extends Model
{
    protected $fillable = ['type', 'site_uuid', 'uuid', 'payload', 'position'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'position' => 'integer'];
    }
}
