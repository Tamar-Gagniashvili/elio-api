<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeadStatus extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];
}
