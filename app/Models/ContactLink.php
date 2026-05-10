<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContactLink extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];

    public function contactLinkGroup()
    {
        return $this->belongsTo(ContactLinkGroup::class, 'link_group_id');
    }
}
