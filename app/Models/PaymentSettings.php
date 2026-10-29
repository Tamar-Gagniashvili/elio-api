<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentSettings extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'provider',
        'bog_settings',
        'flitt_settings',
    ];

    protected $hidden = [
        'bog_settings',
        'flitt_settings',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'bog_settings' => 'encrypted:array',
            'flitt_settings' => 'encrypted:array',
        ];
    }
}
