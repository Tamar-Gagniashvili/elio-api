<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'external_id',
        'provider',
        'payable_type',
        'payable_id',
        'contact_id',
        'status',
        'description',
        'ccy',
        'amount',
        'refund_amount',
        'request_payload',
        'response_payload',
        'callback_payload',
    ];

    protected $attributes = [
        'status' => 'created',
    ];

    /**
     * What the payment is for, e.g. a booking.
     *
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'callback_payload' => 'array',
        ];
    }
}
