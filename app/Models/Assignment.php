<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    use BelongsToCompany;

    protected $fillable = ['truck_id', 'driver_id', 'principal', 'started_at', 'ended_at', 'created_by', 'ended_by'];

    protected function casts(): array
    {
        return ['principal' => 'boolean'];
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
