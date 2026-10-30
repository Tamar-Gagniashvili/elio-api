<?php

namespace App\Models;

use App\Enums\SmsProvider;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SmsSettings extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'sms_provider',
        'inex_phone_settings',
    ];

    protected $hidden = [
        'inex_phone_settings',
    ];

    protected function casts(): array
    {
        return [
            'sms_provider' => SmsProvider::class,
            'inex_phone_settings' => 'encrypted:array',
        ];
    }
}
