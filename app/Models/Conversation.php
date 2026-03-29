<?php

namespace App\Models;

use App\Events\ConversationUpdated;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];

    protected $dispatchesEvents = [
        'created' => ConversationUpdated::class,
        'updated' => ConversationUpdated::class,
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'assignment_seen_by_assignee' => 'boolean',
        ];
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
