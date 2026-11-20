<?php

namespace App\Support;

use App\Models\Driver;
use App\Models\Trip;
use Carbon\CarbonImmutable;

/**
 * Single source of truth for a driver's monthly settlement (PDF, CSV and any screen).
 * All amounts are integer cents.
 */
final class DriverSettlement
{
    public const STATUS_LABELS = ['programado' => 'Programado', 'en_viaje' => 'En viaje', 'completado' => 'Completado', 'anulado' => 'Anulado'];

    /**
     * @return array{driver: Driver, month: CarbonImmutable, rows: list<array{date: string, origin: string, destination: string, cargo: string, plate: string, customer: string, viaticos_qty: int, value: ?int, collected: bool, km: string, status: string}>, totals: array{trips: int, value: int, collected: int, commission: int, viaticos_qty: int, viaticos: int, payable: int}}
     */
    public function for(Driver $driver, CarbonImmutable $month): array
    {
        $trips = Trip::with('truck:id,plate')
            ->where('driver_id', $driver->id)
            ->whereBetween('date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->orderBy('date')->orderBy('id')
            ->get();

        $totals = ['trips' => 0, 'value' => 0, 'collected' => 0, 'commission' => 0, 'viaticos_qty' => 0, 'viaticos' => 0, 'payable' => 0];
        $rows = [];
        foreach ($trips as $trip) {
            $value = $trip->value_amount === null ? null : Money::toCents($trip->value_amount);
            $valueCents = $value ?? 0;
            $totals['trips']++;
            $totals['value'] += $valueCents;
            $totals['collected'] += $trip->collected ? $valueCents : 0;
            $totals['commission'] += Money::commission($valueCents, $trip->commission_rate);
            $totals['viaticos_qty'] += $trip->viaticos_qty;
            $totals['viaticos'] += $trip->viaticos_qty * Money::toCents($trip->viatico_unit_amount);
            $rows[] = [
                'date' => CarbonImmutable::parse($trip->date)->format('d/m/Y'),
                'origin' => (string) $trip->origin,
                'destination' => (string) $trip->destination,
                'cargo' => (string) $trip->cargo,
                'plate' => $trip->truck->plate,
                'customer' => (string) $trip->customer_name,
                'viaticos_qty' => $trip->viaticos_qty,
                'value' => $value,
                'collected' => $trip->collected,
                'km' => number_format((float) ($trip->actual_km ?? $trip->planned_km), 1, ',', ''),
                'status' => self::STATUS_LABELS[$trip->status] ?? $trip->status,
            ];
        }
        $totals['payable'] = $totals['commission'] + $totals['viaticos'];

        return ['driver' => $driver, 'month' => $month, 'rows' => $rows, 'totals' => $totals];
    }
}
