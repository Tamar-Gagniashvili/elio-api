<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChatDistribution extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [];

    protected $table = 'chat_distribution';
}
