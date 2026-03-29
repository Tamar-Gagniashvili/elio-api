<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Multitenancy\Models\Tenant as SpatieTenant;

class Tenant extends SpatieTenant
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'enabled_channels' => 'array',
        'is_active' => 'boolean',
    ];
}
