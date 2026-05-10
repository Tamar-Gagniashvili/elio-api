<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContactNote extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'reminder_at' => 'datetime',
        'reminder_shown' => 'boolean',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
