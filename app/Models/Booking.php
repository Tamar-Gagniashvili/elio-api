<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use BelongsToTenant, HasUuids;

    public const STATUSES = ['scheduled', 'completed', 'cancelled', 'no_show'];

    protected $guarded = [];

    protected $attributes = [
        'status' => 'scheduled',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function staffProfile()
    {
        return $this->hasOne(Profile::class, 'user_id', 'staff_user_id');
    }

    /**
     * Active (non-cancelled) bookings for a staff member that overlap the given range.
     */
    public function scopeOverlapping(Builder $query, int|string $staffUserId, \DateTimeInterface|string $startsAt, \DateTimeInterface|string $endsAt): Builder
    {
        return $query->where('staff_user_id', $staffUserId)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }
}
