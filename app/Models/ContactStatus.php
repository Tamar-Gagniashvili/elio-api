<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContactStatus extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function status()
    {
        return $this->belongsTo(LeadStatus::class, 'status_id');
    }
}
