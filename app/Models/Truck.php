<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Truck extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = ['plate', 'brand', 'model', 'year', 'capacity_tons', 'odometer', 'fuel', 'maintenance', 'last_service', 'next_service', 'archived_at'];

    protected function casts(): array
    {
        return ['maintenance' => 'boolean', 'capacity_tons' => 'decimal:2', 'odometer' => 'decimal:1', 'archived_at' => 'datetime'];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->whereNull('ended_at');
    }
}
