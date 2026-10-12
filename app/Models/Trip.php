<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use BelongsToCompany;

    const OPEN = ['programado', 'en_viaje'];

    protected $fillable = ['customer_id', 'customer_name', 'origin_location_id', 'destination_location_id', 'truck_id', 'driver_id', 'date', 'origin', 'destination', 'cargo', 'tons', 'planned_km', 'actual_km', 'notes', 'status', 'historical', 'started_at', 'completed_at', 'cancellation_reason', 'created_by'];

    protected function casts(): array
    {
        return ['historical' => 'boolean', 'tons' => 'decimal:2', 'planned_km' => 'decimal:1', 'actual_km' => 'decimal:1'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TripExpense::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
