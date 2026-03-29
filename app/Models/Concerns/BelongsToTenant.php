<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Tenant;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        static::creating(function (Model $model) {
            if (! $model->tenant_id && $currentTenant = Tenant::current()) {
                $model->tenant_id = $currentTenant->id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($currentTenant = Tenant::current()) {
                $builder->where($builder->getQuery()->from.'.tenant_id', $currentTenant->id);
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(config('multitenancy.tenant_model', Tenant::class));
    }
}
