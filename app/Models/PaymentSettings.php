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
        'advance_payment_enabled',
        'advance_payment_hours_before',
        'advance_payment_message',
    ];

    protected $attributes = [
        'advance_payment_enabled' => false,
        'advance_payment_hours_before' => 24,
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
            'advance_payment_enabled' => 'boolean',
            'advance_payment_hours_before' => 'integer',
        ];
    }
}
