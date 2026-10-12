<?php

namespace App\Actions;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Truck;
use App\Support\Audit;
use App\Support\CompanyTransaction;
use Illuminate\Validation\ValidationException;

class ManageFleet
{
    public function save(string $class, array $data, Truck|Driver|null $model = null): Truck|Driver
    {
        return app(CompanyTransaction::class)->run(function () use ($class, $data, $model) {
            if ($model) {
                $model->refresh();
                if ($model->archived_at) {
                    throw ValidationException::withMessages(['operation' => 'Restaurá el registro antes de editarlo.']);
                }
                if ($model instanceof Truck && ! empty($data['maintenance']) && $model->trips()->where('status', 'en_viaje')->exists()) {
                    throw ValidationException::withMessages(['maintenance' => 'No se puede poner en mantenimiento un camión en ruta.']);
                }
                $model->update($data);
            } else {
                $model = $class::create($data);
            }
            Audit::record($model, 'datos_guardados', $data);

            return $model;
        });
    }

    public function archive(Truck|Driver $model): void
    {
        app(CompanyTransaction::class)->run(function () use ($model) {
            $model->refresh();
            if (! $model->archived_at && $model->trips()->whereIn('status', Trip::OPEN)->exists()) {
                throw ValidationException::withMessages(['operation' => 'Completá o anulá los viajes abiertos antes de archivar.']);
            }
            $archive = ! $model->archived_at;
            $model->update(['archived_at' => $archive ? now() : null]);
            if ($archive) {
                foreach ($model->assignments()->whereNull('ended_at')->get() as $assignment) {
                    $assignment->update(['ended_at' => now(), 'ended_by' => auth()->id()]);
                    Audit::record($assignment, 'asignacion_finalizada_por_archivo');
                }
            }
            Audit::record($model, $archive ? 'archivado' : 'restaurado');
        });
    }
}
