<?php

namespace App\Support;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;

class TripReport
{
    public function query(array $filters, ?int $driverId = null, ?int $truckId = null): Builder
    {
        return Trip::query()->with(['truck:id,plate,brand,model', 'driver:id,name'])
            ->when($driverId, fn ($q) => $q->where('driver_id', $driverId))
            ->when($truckId, fn ($q) => $q->where('truck_id', $truckId))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('date', '<=', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['truck_id'] ?? null, fn ($q, $v) => $q->where('truck_id', $v))
            ->when($filters['driver_id'] ?? null, fn ($q, $v) => $q->where('driver_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('origin', 'like', "%$v%")->orWhere('destination', 'like', "%$v%")->orWhere('cargo', 'like', "%$v%")));
    }

    public function totals(Builder $query): array
    {
        $row = (clone $query)->toBase()->reorder()->limit(null)->offset(0)->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'completado' THEN actual_km ELSE 0 END) AS km, SUM(CASE WHEN status = 'completado' THEN tons ELSE 0 END) AS tons, SUM(status = 'completado') AS completed, SUM(status = 'en_viaje') AS running")->first();

        return ['total' => (int) $row->total, 'km' => (float) $row->km, 'tons' => (float) $row->tons, 'completed' => (int) $row->completed, 'running' => (int) $row->running];
    }
}
