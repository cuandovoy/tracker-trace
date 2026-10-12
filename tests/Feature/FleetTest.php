<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    public function test_assignments_keep_history_and_only_one_principal(): void
    {
        $this->operator();
        $truck = Truck::factory()->create();
        $second = Truck::factory()->create();
        $driver = Driver::factory()->create();
        $other = Driver::factory()->create();
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => $truck->id, 'principal' => true])->assertSessionHasNoErrors();
        $this->post('/choferes/'.$other->id.'/asignacion', ['truck_id' => $truck->id, 'principal' => true])->assertSessionHasErrors('truck_id');
        $this->post('/viajes', $this->payload($truck, $driver));
        $trip = Trip::first();
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => $second->id, 'principal' => true])->assertSessionHasNoErrors();
        $this->assertSame($truck->id, $trip->fresh()->truck_id);
        $this->assertSame(2, Assignment::where('driver_id', $driver->id)->count());
        $this->assertSame(1, Assignment::where('driver_id', $driver->id)->whereNull('ended_at')->count());
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => null, 'principal' => false])->assertSessionHasNoErrors();
        $this->assertSame(0, Assignment::where('driver_id', $driver->id)->whereNull('ended_at')->count());
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
