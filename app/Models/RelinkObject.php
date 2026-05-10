<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RelinkObject extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
        ];
    }

    public function status()
    {
        return $this->belongsTo(ObjectStatus::class, 'status_id');
    }

    public function type()
    {
        return $this->belongsTo(ObjectType::class, 'object_type_id');
    }
}
