<?php

namespace App\Actions;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Truck;
use App\Support\Audit;
use App\Support\CompanyTransaction;
use Illuminate\Validation\ValidationException;

class ManageAssignment
{
    public function assign(Driver $driver, ?int $truckId): void
    {
        app(CompanyTransaction::class)->run(function () use ($driver, $truckId) {
            $driver->refresh();
            if ($driver->archived_at) {
                throw ValidationException::withMessages(['operation' => 'El chofer está archivado.']);
            }
            if ($truckId) {
                Truck::whereNull('archived_at')->findOrFail($truckId);
                if (Assignment::where('truck_id', $truckId)->where('driver_id', $driver->id)->whereNull('ended_at')->exists()) {
                    return;
                }
            }
            $open = Assignment::whereNull('ended_at')->where(function ($query) use ($driver, $truckId) {
                $query->where('driver_id', $driver->id)->when($truckId, fn ($q) => $q->orWhere('truck_id', $truckId));
            })->get();
            foreach ($open as $old) {
                $old->update(['ended_at' => now(), 'ended_by' => auth()->id()]);
                Audit::record($old, 'asignacion_finalizada');
            }
            if ($truckId) {
                $assignment = Assignment::create(['truck_id' => $truckId, 'driver_id' => $driver->id, 'started_at' => now(), 'created_by' => auth()->id()]);
                Audit::record($assignment, 'asignacion_creada', $assignment->getAttributes());
            }
        });
    }
}
