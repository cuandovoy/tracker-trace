<?php

namespace App\Models\Concerns;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', fn (Builder $query) => $query->where($query->getModel()->qualifyColumn('company_id'), app(Tenant::class)->id() ?? 0));
        static::creating(function ($model) {
            $id = app(Tenant::class)->id();
            if (! $id || ($model->company_id && (int) $model->company_id !== $id)) {
                throw new LogicException('Empresa no válida.');
            }
            $model->company_id = $id;
        });
        static::updating(function ($model) {
            if ($model->isDirty('company_id')) {
                throw new LogicException('No se puede cambiar la empresa.');
            }
        });
    }
}
