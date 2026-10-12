<?php

namespace Database\Seeders;

use App\Actions\ManageAssignment;
use App\Actions\ManageTrip;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Los datos de ejemplo solo se permiten en local o testing.');
        }
        if (Company::whereIn('name', ['Rodavial · Demostración', 'Transporte del Norte · Demostración'])->exists()) {
            $this->command?->warn('La demostración ya existe; no se modificó.');

            return;
        }
        DB::transaction(function () {
            User::firstOrCreate(['email' => 'superadmin@example.test'], ['name' => 'Administración demo', 'role' => 'superadmin', 'password' => 'DemoTransporte2026!', 'active' => true]);
            foreach (['Rodavial · Demostración', 'Transporte del Norte · Demostración'] as $index => $name) {
                $company = Company::create(['name' => $name, 'tax_id' => 'RUT de demostración', 'address' => 'Montevideo, Uruguay', 'phone' => '099 000 000', 'timezone' => 'America/Montevideo']);
                $admin = User::create(['company_id' => $company->id, 'name' => $index ? 'Ana Pereira' : 'Lucía Fernández', 'email' => $index ? 'norte@example.test' : 'admin@example.test', 'role' => 'admin', 'password' => 'DemoTransporte2026!']);
                User::create(['company_id' => $company->id, 'name' => 'Operaciones demo', 'email' => $index ? 'operador.norte@example.test' : 'operador@example.test', 'role' => 'operator', 'password' => 'DemoTransporte2026!']);
                Auth::login($admin);
                $names = ['Rodrigo Méndez', 'Alicia Cabrera', 'Leandro Ocampo', 'Sofía Silva', 'Martín Duarte', 'Lucía Bianchi'];
                $units = [['ABT 4521', 'Volvo', 'FH 460', 2021, 32], ['CDE 7890', 'Scania', 'R 450', 2019, 25], ['FGR 1134', 'Mercedes-Benz', 'Actros 2648', 2022, 40], ['KLM 2207', 'Iveco', 'Stralis 460', 2018, 28], ['STU 6045', 'Volvo', 'FMX 500', 2020, 30], ['PQR 3388', 'Scania', 'G 410', 2023, 26]];
                foreach ($units as $i => [$plate,$brand,$model,$year,$capacity]) {
                    $truck = Truck::create(['plate' => $plate, 'brand' => $brand, 'model' => $model, 'year' => $year, 'capacity_tons' => $capacity, 'odometer' => 98450 + $i * 62300, 'fuel' => 'diesel', 'maintenance' => $i === 3, 'last_service' => now()->subMonths(2)->toDateString(), 'next_service' => now()->addMonth()->toDateString()]);
                    $driver = Driver::create(['name' => $names[$i], 'license' => 'DEMO-'.($i + 1), 'category' => 'Profesional', 'phone' => '099 000 00'.$i, 'hired_on' => '2020-03-01']);
                    app(ManageAssignment::class)->assign($driver, $truck->id, true);
                    $data = ['truck_id' => $truck->id, 'driver_id' => $driver->id, 'date' => now()->subDays($i + 1)->toDateString(), 'origin' => 'Montevideo', 'destination' => ['Rivera', 'Colonia', 'Fray Bentos', 'Minas', 'Salto', 'Maldonado'][$i], 'cargo' => ['Carga general', 'Lácteos', 'Celulosa', 'Cemento', 'Granos', 'Bebidas'][$i], 'tons' => 20, 'planned_km' => 112 + $i * 65, 'notes' => 'Datos de demostración.'];
                    app(ManageTrip::class)->save([...$data, 'historical' => true, 'actual_km' => 120 + $i * 65]);
                    if ($i !== 3) {
                        $trip = app(ManageTrip::class)->save([...$data, 'date' => now()->toDateString()]);
                        if ($i === 0 || $i === 2) {
                            app(ManageTrip::class)->transition($trip, 'en_viaje');
                        }
                    }
                }
                Auth::logout();
            }
        });
    }
}
