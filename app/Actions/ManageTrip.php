<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Location;
use App\Models\Trip;
use App\Models\Truck;
use App\Support\Audit;
use App\Support\CompanyTransaction;
use Illuminate\Validation\ValidationException;

class ManageTrip
{
    public function save(array $data, ?Trip $trip = null): Trip
    {
        return app(CompanyTransaction::class)->run(function () use ($data, $trip) {
            if ($trip) {
                $trip->refresh();
                $this->require($trip->status === 'programado', 'Solo se pueden editar viajes programados.');
            }
            $historical = ! $trip && ! empty($data['historical']);
            $this->require(! $historical || auth()->user()->isAdmin(), 'Solo un administrador puede cargar históricos.');
            if ($historical) {
                $this->require($data['date'] <= now(auth()->user()->company->timezone)->toDateString(), 'La fecha histórica no puede ser futura.');
            }
            $truck = Truck::whereNull('archived_at')->findOrFail($data['truck_id']);
            Driver::whereNull('archived_at')->findOrFail($data['driver_id']);
            $this->require((float) $data['tons'] <= (float) $truck->capacity_tons, 'La carga supera la capacidad del camión.');
            $before = $trip?->getAttributes();
            if (array_key_exists('customer_id', $data)) {
                $customerId = $data['customer_id'] ?: null;
                if (! $trip || (int) $trip->customer_id !== (int) $customerId) {
                    $data['customer_name'] = $customerId ? Customer::findOrFail($customerId)->name : null;
                }
            }
            foreach (['origin', 'destination'] as $field) {
                $key = $field.'_location_id';
                if (! empty($data[$key]) && (! $trip || (int) $trip->$key !== (int) $data[$key] || empty($data[$field]))) {
                    $data[$field] = Location::findOrFail($data[$key])->address;
                }
            }
            unset($data['historical']);
            if (! $historical) {
                unset($data['actual_km']);
            }
            if ($trip) {
                $trip->update($data);
            } else {
                $trip = Trip::create([...$data, 'status' => $historical ? 'completado' : 'programado', 'historical' => $historical, 'completed_at' => $historical ? now() : null, 'created_by' => auth()->id()]);
            }
            Audit::record($trip, $before ? 'viaje_editado' : 'viaje_creado', ['before' => $before, 'after' => $trip->getAttributes()]);

            return $trip;
        });
    }

    public function transition(Trip $trip, string $target, array $data = []): void
    {
        app(CompanyTransaction::class)->run(function () use ($trip, $target, $data) {
            $trip->refresh();
            $before = $trip->status;
            if ($target === 'en_viaje') {
                $this->require($before === 'programado', 'El viaje ya no está programado.');
                $truck = Truck::findOrFail($trip->truck_id);
                $driver = Driver::findOrFail($trip->driver_id);
                $this->require(! $truck->archived_at && ! $driver->archived_at, 'El camión o chofer está archivado.');
                $this->require(! $truck->maintenance, 'El camión está en mantenimiento.');
                $this->require((float) $trip->tons <= (float) $truck->capacity_tons, 'La carga supera la capacidad actual del camión.');
                $this->require(! Trip::where('status', 'en_viaje')->where(fn ($q) => $q->where('truck_id', $truck->id)->orWhere('driver_id', $driver->id))->exists(), 'El camión o chofer ya tiene un viaje en curso.');
                $trip->update(['status' => $target, 'started_at' => now()]);
            } elseif ($target === 'completado') {
                $this->require($before === 'en_viaje', 'Solo se puede completar un viaje en curso.');
                $trip->update(['status' => $target, 'actual_km' => $data['actual_km'], 'completed_at' => now()]);
            } else {
                $this->require($target === 'anulado' && in_array($before, Trip::OPEN), 'Este viaje está cerrado.');
                $trip->update(['status' => 'anulado', 'cancellation_reason' => $data['cancellation_reason']]);
            }
            Audit::record($trip, 'estado_viaje', ['from' => $before, 'to' => $target, ...$data]);
        });
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['operation' => $message]);
        }
    }
}
