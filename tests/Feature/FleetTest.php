<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use App\Support\TripSpreadsheet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FleetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function operator(string $role = 'admin'): User
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id, 'role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    private function payload(?Truck $truck = null, ?Driver $driver = null): array
    {
        return ['truck_id' => ($truck ?? Truck::factory()->create())->id, 'driver_id' => ($driver ?? Driver::factory()->create())->id, 'date' => '2026-10-10', 'origin' => 'Montevideo', 'destination' => 'Rivera', 'cargo' => 'Granos', 'tons' => 20, 'planned_km' => 500, 'notes' => 'Frágil'];
    }

    public function test_full_trip_lifecycle_preserves_odometer_and_audits(): void
    {
        $this->operator();
        $data = $this->payload();
        $this->post('/viajes', $data)->assertRedirect();
        $trip = Trip::firstOrFail();
        $this->assertSame('programado', $trip->status);
        $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'en_viaje'])->assertSessionHasNoErrors();
        $this->assertSame('en_viaje', $trip->fresh()->status);
        $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'completado', 'actual_km' => 512])->assertSessionHasNoErrors();
        $this->assertSame('completado', $trip->fresh()->status);
        $this->assertSame('512.0', $trip->fresh()->actual_km);
        $this->assertSame('100000.0', Truck::find($data['truck_id'])->odometer);
        $this->assertSame(3, AuditEvent::where('subject_type', 'trips')->count());
        $this->put('/viajes/'.$trip->id, [...$data, 'origin' => 'Cambio'])->assertSessionHasErrors('operation');
        $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'anulado', 'cancellation_reason' => 'Error'])->assertSessionHasErrors('operation');
        $this->assertSame('Montevideo', $trip->fresh()->origin);
    }

    public function test_running_truck_and_driver_cannot_be_started_twice(): void
    {
        $this->operator();
        $truck = Truck::factory()->create();
        $driver = Driver::factory()->create();
        $this->post('/viajes', $this->payload($truck, $driver));
        $first = Trip::latest('id')->first();
        $this->post('/viajes', $this->payload($truck));
        $sameTruck = Trip::latest('id')->first();
        $this->post('/viajes', $this->payload(driver: $driver));
        $sameDriver = Trip::latest('id')->first();
        $this->post('/viajes/'.$first->id.'/estado', ['status' => 'en_viaje'])->assertSessionHasNoErrors();
        foreach ([$sameTruck, $sameDriver, $first] as $trip) {
            $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'en_viaje'])->assertSessionHasErrors('operation');
        }
        $this->assertSame(1, Trip::where('status', 'en_viaje')->count());
    }

    public function test_maintenance_and_capacity_are_enforced(): void
    {
        $this->operator();
        $truck = Truck::factory()->create(['maintenance' => true]);
        $data = $this->payload($truck);
        $this->post('/viajes', [...$data, 'tons' => 33])->assertSessionHasErrors('operation');
        $this->assertSame(0, Trip::count());
        $this->post('/viajes', $data)->assertSessionHasNoErrors();
        $trip = Trip::first();
        $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'en_viaje'])->assertSessionHasErrors('operation');
        $truck->update(['maintenance' => false]);
        $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'en_viaje'])->assertSessionHasNoErrors();
        $this->put('/camiones/'.$truck->id, [...$truck->only('plate', 'brand', 'model', 'year', 'capacity_tons', 'odometer', 'fuel'), 'maintenance' => true])->assertSessionHasErrors('maintenance');
        $this->assertFalse($truck->fresh()->maintenance);
    }

    public function test_archiving_requires_closed_trips_and_preserves_history(): void
    {
        $this->operator();
        $data = $this->payload();
        $this->post('/viajes', $data);
        $trip = Trip::first();
        $this->post('/camiones/'.$data['truck_id'].'/archivo')->assertSessionHasErrors('operation');
        $this->post('/choferes/'.$data['driver_id'].'/archivo')->assertSessionHasErrors('operation');
        $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'anulado'])->assertSessionHasErrors('cancellation_reason');
        $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'anulado', 'cancellation_reason' => 'Cliente cancela'])->assertSessionHasNoErrors();
        $this->post('/camiones/'.$data['truck_id'].'/archivo')->assertSessionHasNoErrors();
        $this->assertNotNull(Truck::find($data['truck_id'])->archived_at);
        $this->assertSame('Cliente cancela', $trip->fresh()->cancellation_reason);
        $this->get('/viajes/'.$trip->id)->assertOk();
        $this->post('/camiones/'.$data['truck_id'].'/archivo')->assertSessionHasNoErrors();
        $this->assertNull(Truck::find($data['truck_id'])->archived_at);
    }

    public function test_assignments_keep_history_and_trucks_have_a_single_driver(): void
    {
        $this->operator();
        $truck = Truck::factory()->create();
        $second = Truck::factory()->create();
        $driver = Driver::factory()->create();
        $other = Driver::factory()->create();
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => $truck->id])->assertSessionHasNoErrors();
        $this->post('/viajes', $this->payload($truck, $driver));
        $trip = Trip::first();
        $this->post('/choferes/'.$other->id.'/asignacion', ['truck_id' => $truck->id])->assertSessionHasNoErrors();
        $this->assertNotNull(Assignment::where('driver_id', $driver->id)->firstOrFail()->ended_at);
        $open = Assignment::where('truck_id', $truck->id)->whereNull('ended_at')->get();
        $this->assertCount(1, $open);
        $this->assertSame($other->id, $open->first()->driver_id);
        $this->assertSame($driver->id, $trip->fresh()->driver_id);
        $this->post('/choferes/'.$other->id.'/asignacion', ['truck_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame(0, Assignment::where('truck_id', $truck->id)->whereNull('ended_at')->count());
        $this->assertSame(1, Assignment::where('driver_id', $other->id)->whereNull('ended_at')->count());
        $this->assertSame(2, Assignment::where('driver_id', $other->id)->count());
        $this->post('/choferes/'.$other->id.'/asignacion', ['truck_id' => null])->assertSessionHasNoErrors();
        $this->assertSame(0, Assignment::where('driver_id', $other->id)->whereNull('ended_at')->count());
        $this->assertSame(0, Assignment::where('truck_id', $second->id)->whereNull('ended_at')->count());
    }

    public function test_assigning_the_same_driver_to_the_same_truck_does_not_duplicate(): void
    {
        $this->operator();
        $truck = Truck::factory()->create();
        $driver = Driver::factory()->create();
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => $truck->id])->assertSessionHasNoErrors();
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => $truck->id])->assertSessionHasNoErrors();
        $this->assertSame(1, Assignment::where('truck_id', $truck->id)->count());
        $this->assertSame(1, Assignment::where('truck_id', $truck->id)->whereNull('ended_at')->count());
    }

    public function test_assignment_rejects_other_company_driver_and_truck(): void
    {
        $this->operator();
        $foreignTruck = Truck::factory()->create();
        $foreignDriver = Driver::factory()->create();
        $this->operator();
        $truck = Truck::factory()->create();
        $driver = Driver::factory()->create();
        $this->post('/choferes/'.$foreignDriver->id.'/asignacion', ['truck_id' => $truck->id])->assertNotFound();
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => $foreignTruck->id])->assertSessionHasErrors('truck_id');
        $this->assertSame(0, Assignment::count());
    }

    public function test_truck_search_matches_current_driver_name_only(): void
    {
        $this->operator();
        $truck = Truck::factory()->create(['plate' => 'AAA 0001']);
        $idle = Truck::factory()->create(['plate' => 'BBB 0002']);
        $driver = Driver::factory()->create(['name' => 'Juan Pérez']);
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => $truck->id]);
        $this->get('/camiones?q=perez')->assertInertia(fn (Assert $p) => $p->has('trucks.data', 1)->where('trucks.data.0.id', $truck->id)->where('trucks.data.0.current_assignment.driver.name', 'Juan Pérez'));
        $this->get('/camiones?q=JUAN')->assertInertia(fn (Assert $p) => $p->has('trucks.data', 1));
        $this->get('/camiones?q=BBB')->assertInertia(fn (Assert $p) => $p->has('trucks.data', 1)->where('trucks.data.0.id', $idle->id)->where('trucks.data.0.current_assignment', null));
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => null]);
        $this->get('/camiones?q=Juan')->assertInertia(fn (Assert $p) => $p->has('trucks.data', 0));
    }

    public function test_truck_search_never_matches_other_company_driver_names(): void
    {
        $this->operator();
        $foreignTruck = Truck::factory()->create(['plate' => 'ZZZ 9999']);
        $foreignDriver = Driver::factory()->create(['name' => 'Secreto Ajeno']);
        $this->post('/choferes/'.$foreignDriver->id.'/asignacion', ['truck_id' => $foreignTruck->id]);
        $this->operator();
        Truck::factory()->create();
        $this->get('/camiones?q=Secreto')->assertInertia(fn (Assert $p) => $p->has('trucks.data', 0));
    }

    public function test_truck_index_loads_current_driver_without_n_plus_one(): void
    {
        $this->operator();
        foreach (range(1, 4) as $i) {
            $truck = Truck::factory()->create();
            $this->post('/choferes/'.Driver::factory()->create()->id.'/asignacion', ['truck_id' => $truck->id]);
        }
        \DB::enableQueryLog();
        $this->get('/camiones')->assertOk();
        $assignmentQueries = collect(\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `assignments`'))->count();
        $this->assertSame(1, $assignmentQueries);
    }

    public function test_trip_options_expose_each_trucks_current_driver(): void
    {
        $this->operator();
        $truck = Truck::factory()->create();
        $idle = Truck::factory()->create();
        $driver = Driver::factory()->create();
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => $truck->id]);
        $this->get('/viajes/create')->assertInertia(fn (Assert $p) => $p
            ->where('trucks', fn ($trucks) => collect($trucks)->firstWhere('id', $truck->id)['current_assignment']['driver_id'] === $driver->id
                && collect($trucks)->firstWhere('id', $idle->id)['current_assignment'] === null));
    }

    public function test_dead_trip_spreadsheet_route_and_class_are_gone(): void
    {
        $this->assertFalse(Route::has('trips.excel'));
        $this->assertFalse(class_exists(TripSpreadsheet::class));
    }

    public function test_report_pagination_and_pdf_use_completed_totals_only(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 12:00:00', 'America/Montevideo'));
        $this->operator();
        $data = $this->payload();
        $this->post('/viajes', [...$data, 'historical' => true, 'actual_km' => 120, 'tons' => 10]);
        $this->post('/viajes', [...$data, 'historical' => true, 'actual_km' => 80, 'tons' => 5, 'date' => '2026-09-30']);
        for ($i = 0; $i < 21; $i++) {
            $this->post('/viajes', $data);
        }
        $this->post('/viajes/'.Trip::latest('id')->first()->id.'/estado', ['status' => 'anulado', 'cancellation_reason' => 'Cancelado']);
        $this->get('/viajes?page=2')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Trips')->where('totals.km', 200)->where('totals.tons', 15)->where('totals.total', 23));
        $this->get('/camiones')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Fleet')->where('stats.km', 120));
        $this->get('/choferes/'.$data['driver_id'].'?from=2026-10-01&to=2026-10-31')->assertOk()->assertInertia(fn (Assert $p) => $p->where('totals.km', 120)->where('totals.tons', 10));
        $response = $this->get('/choferes/'.$data['driver_id'].'/pdf?from=2026-10-01&to=2026-10-31');
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->get('/viajes?from=2026-10-31&to=2026-10-01')->assertSessionHasErrors('to');
    }

    public function test_operator_cannot_create_historical_trip_and_admin_requires_actual_distance(): void
    {
        $user = $this->operator('operator');
        $data = $this->payload();
        $this->post('/viajes', [...$data, 'historical' => true, 'actual_km' => 100])->assertSessionHasErrors('operation');
        $user->update(['role' => 'admin']);
        $this->post('/viajes', [...$data, 'historical' => true])->assertSessionHasErrors('actual_km');
        $this->assertSame(0, Trip::count());
    }

    public function test_all_fleet_pages_render_with_empty_and_populated_data(): void
    {
        $this->operator();
        foreach (['/camiones', '/choferes', '/viajes', '/camiones/create', '/choferes/create', '/viajes/create'] as $url) {
            $this->get($url)->assertOk();
        }
        $data = $this->payload();
        $this->post('/viajes', $data);
        $trip = Trip::first();
        foreach (['/camiones/'.$data['truck_id'], '/camiones/'.$data['truck_id'].'/edit', '/choferes/'.$data['driver_id'], '/choferes/'.$data['driver_id'].'/edit', '/viajes/'.$trip->id, '/viajes/'.$trip->id.'/edit'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
