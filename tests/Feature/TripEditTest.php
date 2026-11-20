<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Location;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use App\Support\TripReport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TripEditTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id, 'role' => 'admin']);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Truck $truck, Driver $driver, array $extra = []): array
    {
        $customer = Customer::create(['name' => 'Cliente']);
        $origin = Location::create(['name' => 'Palmira', 'address' => 'Nueva Palmira', 'customer_id' => $customer->id]);
        $destination = Location::create(['name' => 'Montevideo', 'address' => 'Montevideo', 'customer_id' => $customer->id]);

        return [...[
            'customer_id' => (string) $customer->id, 'origin_location_id' => (string) $origin->id, 'destination_location_id' => (string) $destination->id,
            'date' => '2026-11-17', 'truck_id' => (string) $truck->id, 'driver_id' => (string) $driver->id, 'cargo' => 'Cemento',
            'origin' => 'Nueva Palmira', 'destination' => 'Montevideo', 'tons' => '14.55', 'planned_km' => '501.0', 'notes' => '',
            'value_amount' => '88000', 'collected' => true, 'viaticos_qty' => '2', 'historical' => false, 'actual_km' => '',
        ], ...$extra];
    }

    private function scheduledTrip(Truck $truck, Driver $driver, array $extra = []): Trip
    {
        return Trip::create([...[
            'truck_id' => $truck->id, 'driver_id' => $driver->id, 'date' => '2026-11-10', 'origin' => 'A', 'destination' => 'B', 'cargo' => 'Cemento',
            'tons' => 10, 'planned_km' => 100, 'status' => 'programado', 'created_by' => auth()->id(),
        ], ...$extra]);
    }

    public function test_admin_can_edit_a_scheduled_trip_with_the_browser_payload(): void
    {
        $this->admin();
        $truck = Truck::factory()->create(['capacity_tons' => 30]);
        $driver = Driver::factory()->create();
        $trip = $this->scheduledTrip($truck, $driver);

        $this->withHeaders(['X-Inertia' => 'true'])->putJson('/viajes/'.$trip->id, $this->payload($truck, $driver))->assertRedirect('/viajes/'.$trip->id);

        $trip->refresh();
        $this->assertSame('14.55', $trip->tons);
        $this->assertSame('88000.00', $trip->value_amount);
        $this->assertTrue($trip->collected);
        $this->assertSame(2, $trip->viaticos_qty);
    }

    public function test_admin_can_edit_a_trip_created_before_commercial_fields_had_values(): void
    {
        $this->admin();
        $truck = Truck::factory()->create(['capacity_tons' => 30]);
        $driver = Driver::factory()->create();
        $trip = $this->scheduledTrip($truck, $driver, ['customer_id' => null, 'customer_name' => null]);
        $trip->forceFill(['value_amount' => null, 'collected' => false, 'viaticos_qty' => 0])->save();

        $this->put('/viajes/'.$trip->id, $this->payload($truck, $driver))->assertSessionHasNoErrors()->assertRedirect('/viajes/'.$trip->id);
    }

    public function test_tons_are_optional_when_creating_and_updating_a_trip(): void
    {
        $this->admin();
        $truck = Truck::factory()->create(['capacity_tons' => 30]);
        $driver = Driver::factory()->create();

        $this->post('/viajes', $this->payload($truck, $driver, ['tons' => '']))->assertSessionHasNoErrors();
        $trip = Trip::latest('id')->firstOrFail();
        $this->assertNull($trip->tons);

        $this->put('/viajes/'.$trip->id, $this->payload($truck, $driver, ['tons' => null]))->assertSessionHasNoErrors()->assertRedirect('/viajes/'.$trip->id);
        $this->assertNull($trip->fresh()->tons);
    }

    public function test_capacity_check_still_applies_when_tons_are_provided(): void
    {
        $this->admin();
        $truck = Truck::factory()->create(['capacity_tons' => 10]);
        $driver = Driver::factory()->create();

        $this->post('/viajes', $this->payload($truck, $driver, ['tons' => '14.55']))->assertSessionHasErrors('operation');
        $this->assertSame(0, Trip::count());
    }

    public function test_trip_without_tons_can_start(): void
    {
        $this->admin();
        $truck = Truck::factory()->create(['capacity_tons' => 10]);
        $driver = Driver::factory()->create();
        $trip = $this->scheduledTrip($truck, $driver, ['tons' => null]);

        $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'en_viaje'])->assertSessionHasNoErrors();
        $this->assertSame('en_viaje', $trip->fresh()->status);
    }

    public function test_report_totals_ignore_trips_without_tons(): void
    {
        $this->admin();
        $truck = Truck::factory()->create();
        $driver = Driver::factory()->create();
        $this->scheduledTrip($truck, $driver, ['tons' => null, 'status' => 'completado', 'actual_km' => 90, 'completed_at' => now()]);
        $this->scheduledTrip($truck, $driver, ['tons' => 5, 'status' => 'completado', 'actual_km' => 10, 'completed_at' => now()]);

        $this->assertSame(5.0, app(TripReport::class)->totals(Trip::query())['tons']);
    }
}
