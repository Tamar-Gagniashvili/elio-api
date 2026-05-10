<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConversationObject extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];

    public function relinkObject()
    {
        return $this->belongsTo(RelinkObject::class, 'object_id');
    }
}
