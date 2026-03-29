<?php

namespace App\Models;

use App\Events\MessageSent;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];

    protected $dispatchesEvents = [
        'created' => MessageSent::class,
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
