<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MetaConnection extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];

    protected $hidden = ['page_access_token', 'user_access_token'];

    protected function casts(): array
    {
        return [
            'page_access_token' => 'encrypted',
            'user_access_token' => 'encrypted',
            'user_token_expires_at' => 'datetime',
        ];
    }
}
