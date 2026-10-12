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
    public function assign(Driver $driver, ?int $truckId, bool $principal): void
    {
        app(CompanyTransaction::class)->run(function () use ($driver, $truckId, $principal) {
            $driver->refresh();
            if ($driver->archived_at) {
                throw ValidationException::withMessages(['operation' => 'El chofer está archivado.']);
            }
            if ($truckId) {
                Truck::whereNull('archived_at')->findOrFail($truckId);
                if ($principal && Assignment::where('truck_id', $truckId)->where('driver_id', '!=', $driver->id)->where('principal', true)->whereNull('ended_at')->exists()) {
                    throw ValidationException::withMessages(['truck_id' => 'Este camión ya tiene un titular. Finalizá esa asignación primero.']);
                }
            }
            foreach ($driver->assignments()->whereNull('ended_at')->get() as $old) {
                $old->update(['ended_at' => now(), 'ended_by' => auth()->id()]);
                Audit::record($old, 'asignacion_finalizada');
            }
            if ($truckId) {
                $assignment = Assignment::create(['truck_id' => $truckId, 'driver_id' => $driver->id, 'principal' => $principal, 'started_at' => now(), 'created_by' => auth()->id()]);
                Audit::record($assignment, 'asignacion_creada', $assignment->getAttributes());
            }
        });
    }
}
