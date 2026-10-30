<?php

namespace App\Models;

use App\Enums\SmsMessageStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'uuid',
        'phone_number',
        'message',
        'sms_message_status',
        'parts_submitted',
        'parts_delivered',
        'total_parts',
        'response_payload',
    ];

    /**
     * InexPhone's message id lives in `uuid`, so only `id` is generated here.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['id'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    protected function casts(): array
    {
        return [
            'sms_message_status' => SmsMessageStatus::class,
            'response_payload' => 'array',
        ];
    }
}
