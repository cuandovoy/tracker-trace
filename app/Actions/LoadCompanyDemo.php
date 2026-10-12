<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Truck;
use App\Support\Audit;
use App\Support\CompanyTransaction;
use App\Support\Tenant;
use Illuminate\Support\Facades\Gate;

class LoadCompanyDemo
{
    public function __construct(
        private CompanyTransaction $transaction,
        private ManageFleet $fleet,
        private ManageAssignment $assignments,
        private ManageTrip $trips,
        private Tenant $tenant,
    ) {}

    public function run(): bool
    {
        Gate::authorize('manage-company');

        return $this->transaction->run(function (): bool {
            $company = Company::findOrFail($this->tenant->id());
            if ($company->demo_loaded_at) {
                return false;
            }

            $today = now($company->timezone)->startOfDay();
            $units = [
                ['Volvo', 'FH 460', 'Ana Ejemplo', 'Rivera', 'Granos', 500],
                ['Scania', 'R 450', 'Bruno Ejemplo', 'Colonia', 'Carga general', 180],
                ['Mercedes-Benz', 'Actros', 'Carolina Ejemplo', 'Maldonado', 'Bebidas', 135],
            ];
            $sequence = 1;
            foreach ($units as $index => [$brand, $model, $name, $destination, $cargo, $distance]) {
                do {
                    $plate = 'DEMO-'.str_pad((string) $sequence++, 3, '0', STR_PAD_LEFT);
                } while (Truck::where('plate', $plate)->exists());

                $truck = $this->fleet->save(Truck::class, [
                    'plate' => $plate, 'brand' => $brand, 'model' => $model,
                    'year' => 2022, 'capacity_tons' => 30, 'odometer' => 120000,
                    'fuel' => 'diesel', 'maintenance' => false,
                    'last_service' => $today->copy()->subMonth()->toDateString(),
                    'next_service' => $today->copy()->addMonth()->toDateString(),
                ]);
                $driver = $this->fleet->save(Driver::class, [
                    'name' => $name.' · Prueba', 'license' => $plate,
                    'category' => 'Profesional', 'phone' => null,
                    'hired_on' => $today->copy()->subYear()->toDateString(),
                ]);
                $this->assignments->assign($driver, $truck->id, true);
                $data = [
                    'truck_id' => $truck->id, 'driver_id' => $driver->id,
                    'origin' => 'Montevideo', 'destination' => $destination,
                    'cargo' => $cargo.' · Prueba', 'tons' => 18.5,
                    'planned_km' => $distance, 'notes' => 'Datos de prueba cargados desde Configuración.',
                ];
                $this->trips->save([...$data, 'date' => $today->copy()->subDays($index + 1)->toDateString(), 'historical' => true, 'actual_km' => $distance + 4.5]);
                $trip = $this->trips->save([...$data, 'date' => $today->toDateString()]);
                if ($index === 0) {
                    $this->trips->transition($trip, 'en_viaje');
                } elseif ($index === 2) {
                    $this->trips->transition($trip, 'anulado', ['cancellation_reason' => 'Anulación de ejemplo.']);
                }
            }

            $company->forceFill(['demo_loaded_at' => now()])->save();
            Audit::record($company, 'datos_prueba_cargados', ['trucks' => 3, 'drivers' => 3, 'trips' => 6]);

            return true;
        });
    }
}
