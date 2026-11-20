<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Location;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TripCommercialTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function operator(): User
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id, 'role' => 'operator']);
        $this->actingAs($user);

        return $user;
    }

    private function trip(array $extra = []): Trip
    {
        $this->post('/viajes', [...['truck_id' => Truck::factory()->create()->id, 'driver_id' => Driver::factory()->create()->id, 'date' => '2026-10-12', 'origin' => 'Montevideo', 'destination' => 'Rivera', 'cargo' => 'Granos', 'tons' => 10, 'planned_km' => 500], ...$extra])->assertSessionHasNoErrors();

        return Trip::latest('id')->firstOrFail();
    }

    private function expense(array $extra = []): array
    {
        return [...['date' => '2026-10-12', 'category' => 'peajes', 'description' => 'Peaje ruta 5', 'amount' => '125.35', 'currency' => 'UYU'], ...$extra];
    }

    public function test_customer_and_locations_fill_a_trip_and_keep_historical_names(): void
    {
        $this->operator();
        $this->post('/clientes', ['name' => 'Acopio Sur', 'contact_name' => 'Ana', 'email' => 'ana@example.test'])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();
        $this->post('/destinos', ['name' => 'Depósito Sur', 'address' => 'Ruta 5 km 40', 'customer_id' => $customer->id])->assertSessionHasNoErrors();
        $location = Location::firstOrFail();
        $trip = $this->trip(['customer_id' => $customer->id, 'origin_location_id' => $location->id, 'origin' => null]);
        $this->assertSame('Acopio Sur', $trip->customer_name);
        $this->assertSame('Ruta 5 km 40', $trip->origin);
        $this->put('/clientes/'.$customer->id, ['name' => 'Nuevo nombre'])->assertSessionHasNoErrors();
        $this->put('/destinos/'.$location->id, ['name' => 'Depósito nuevo', 'address' => 'Nueva dirección'])->assertSessionHasNoErrors();
        $this->assertSame('Acopio Sur', $trip->fresh()->customer_name);
        $this->assertSame('Ruta 5 km 40', $trip->fresh()->origin);
        foreach (['/clientes', '/clientes/create', '/clientes/'.$customer->id.'/edit', '/destinos', '/destinos/create', '/destinos/'.$location->id.'/edit', '/viajes/create', '/viajes/'.$trip->id.'/edit'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_other_company_records_cannot_be_read_updated_or_assigned(): void
    {
        $other = $this->operator();
        $this->post('/clientes', ['name' => 'Privado']);
        $customer = Customer::firstOrFail();
        $this->post('/destinos', ['name' => 'Privado', 'address' => 'Dirección privada']);
        $location = Location::firstOrFail();
        $trip = $this->trip();
        $this->post('/viajes/'.$trip->id.'/gastos', $this->expense());
        $expense = TripExpense::firstOrFail();
        $this->operator();
        $this->get('/clientes/'.$customer->id.'/edit')->assertNotFound();
        $this->put('/clientes/'.$customer->id, ['name' => 'Intruso'])->assertNotFound();
        $this->get('/destinos/'.$location->id.'/edit')->assertNotFound();
        $this->post('/destinos', ['name' => 'Lugar', 'address' => 'Dirección', 'customer_id' => $customer->id])->assertSessionHasErrors('customer_id');
        $this->post('/viajes', ['customer_id' => $customer->id, 'origin_location_id' => $location->id])->assertSessionHasErrors(['customer_id', 'origin_location_id']);
        $this->post('/viajes/'.$trip->id.'/gastos', $this->expense())->assertNotFound();
        $own = $this->trip();
        $this->put('/viajes/'.$own->id.'/gastos/'.$expense->id, $this->expense())->assertNotFound();
        $this->post('/clientes', ['name' => 'Propio', 'company_id' => $other->company_id])->assertSessionHasNoErrors();
        $this->assertSame(1, Customer::count());
    }

    public function test_expenses_have_exact_separate_currency_totals_and_audit(): void
    {
        $this->operator();
        $trip = $this->trip();
        $trip->update(['status' => 'completado', 'actual_km' => 505]);
        $this->post('/viajes/'.$trip->id.'/gastos', $this->expense())->assertSessionHasNoErrors();
        $expense = TripExpense::firstOrFail();
        $this->post('/viajes/'.$trip->id.'/gastos', $this->expense(['amount' => '0.65']))->assertSessionHasNoErrors();
        $this->post('/viajes/'.$trip->id.'/gastos', $this->expense(['currency' => 'USD', 'amount' => '12.50']))->assertSessionHasNoErrors();
        $this->get('/viajes/'.$trip->id)->assertInertia(fn (Assert $p) => $p->where('expenseTotals.0.currency', 'USD')->where('expenseTotals.0.total', '12.50')->where('expenseTotals.1.total', '126.00')->has('trip.expenses', 3));
        $this->get('/viajes/'.$trip->id.'/gastos/create')->assertOk();
        $this->get('/viajes/'.$trip->id.'/gastos/'.$expense->id.'/edit')->assertOk();
        $this->put('/viajes/'.$trip->id.'/gastos/'.$expense->id, $this->expense(['amount' => '130.00', 'trip_id' => 999]))->assertSessionHasNoErrors();
        $this->assertSame('130.00', $expense->fresh()->amount);
        $this->assertSame($trip->id, $expense->fresh()->trip_id);
        $this->delete('/viajes/'.$trip->id.'/gastos/'.$expense->id)->assertRedirect('/viajes/'.$trip->id);
        $this->assertDatabaseMissing('trip_expenses', ['id' => $expense->id]);
        $this->assertSame(5, AuditEvent::where('subject_id', $trip->id)->whereIn('action', ['gasto_creado', 'gasto_editado', 'gasto_eliminado'])->count());
        $this->assertSame('completado', $trip->fresh()->status);
    }

    public function test_expense_validation_and_trip_binding_protect_records(): void
    {
        $this->operator();
        $first = $this->trip();
        $second = $this->trip();
        $this->post('/viajes/'.$first->id.'/gastos', $this->expense(['amount' => '-1', 'currency' => 'EUR', 'category' => 'invalid']))->assertSessionHasErrors(['amount', 'currency', 'category']);
        $this->post('/viajes/'.$first->id.'/gastos', $this->expense(['amount' => '1.234']))->assertSessionHasErrors('amount');
        $this->post('/viajes/'.$first->id.'/gastos', $this->expense())->assertSessionHasNoErrors();
        $expense = TripExpense::firstOrFail();
        $this->delete('/viajes/'.$second->id.'/gastos/'.$expense->id)->assertNotFound();
        $this->get('/viajes/'.$second->id.'/gastos/'.$expense->id.'/edit')->assertNotFound();
        $this->assertSame(1, TripExpense::count());
    }

    public function test_trips_can_be_filtered_by_customer_and_reject_foreign_customer_filters(): void
    {
        $this->operator();
        $this->post('/clientes', ['name' => 'Cliente filtrado']);
        $customer = Customer::firstOrFail();
        $trip = $this->trip(['customer_id' => $customer->id]);
        $this->trip();
        $this->get('/viajes?customer_id='.$customer->id)->assertInertia(fn (Assert $p) => $p->has('trips.data', 1)->where('trips.data.0.id', $trip->id)->where('totals.total', 1));
        $this->operator();
        $this->get('/viajes?customer_id='.$customer->id)->assertSessionHasErrors('customer_id');
    }

    public function test_guests_and_platform_users_cannot_manage_company_catalogs(): void
    {
        $this->get('/clientes')->assertRedirect('/login');
        $this->post('/destinos', ['name' => 'Lugar'])->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'company_id' => null]));
        $this->get('/clientes')->assertForbidden();
        $this->post('/clientes', ['name' => 'No autorizado'])->assertForbidden();
    }

    public function test_trip_stores_commercial_fields_and_snapshots_from_config(): void
    {
        $this->operator();
        $trip = $this->trip(['value_amount' => '1000', 'collected' => true, 'viaticos_qty' => 2]);
        $this->assertSame('1000.00', $trip->value_amount);
        $this->assertTrue($trip->collected);
        $this->assertSame(2, $trip->viaticos_qty);
        $this->assertSame('8.00', $trip->commission_rate);
        $this->assertSame('800.00', $trip->viatico_unit_amount);
        $defaults = $this->trip();
        $this->assertNull($defaults->value_amount);
        $this->assertFalse($defaults->collected);
        $this->assertSame(0, $defaults->viaticos_qty);
    }

    public function test_config_change_keeps_old_snapshots_and_new_trips_get_new_values(): void
    {
        $this->operator();
        $old = $this->trip();
        config(['fleet.commission_rate' => '10.00', 'fleet.viatico_unit_amount' => '1000.00']);
        $new = $this->trip();
        $this->put('/viajes/'.$old->id, ['truck_id' => $old->truck_id, 'driver_id' => $old->driver_id, 'date' => '2026-10-13', 'origin' => 'Montevideo', 'destination' => 'Rivera', 'cargo' => 'Granos', 'tons' => 10, 'planned_km' => 500, 'commission_rate' => '50.00'])->assertSessionHasNoErrors();
        $this->assertSame('8.00', $old->fresh()->commission_rate);
        $this->assertSame('800.00', $old->fresh()->viatico_unit_amount);
        $this->assertSame('10.00', $new->commission_rate);
        $this->assertSame('1000.00', $new->viatico_unit_amount);
    }

    public function test_snapshots_are_not_mass_assignable_through_the_request(): void
    {
        $this->operator();
        $trip = $this->trip(['commission_rate' => '99.00', 'viatico_unit_amount' => '1.00']);
        $this->assertSame('8.00', $trip->commission_rate);
        $this->assertSame('800.00', $trip->viatico_unit_amount);
    }

    public function test_trip_created_directly_gets_snapshots(): void
    {
        $user = $this->operator();
        $trip = Trip::create(['truck_id' => Truck::factory()->create()->id, 'driver_id' => Driver::factory()->create()->id, 'date' => '2026-10-12', 'origin' => 'A', 'destination' => 'B', 'cargo' => 'C', 'tons' => 1, 'planned_km' => 1, 'status' => 'programado', 'created_by' => $user->id]);
        $this->assertSame('8.00', $trip->fresh()->commission_rate);
        $this->assertSame('800.00', $trip->fresh()->viatico_unit_amount);
    }

    public function test_commercial_fields_are_validated(): void
    {
        $this->operator();
        $base = ['truck_id' => Truck::factory()->create()->id, 'driver_id' => Driver::factory()->create()->id, 'date' => '2026-10-12', 'origin' => 'A', 'destination' => 'B', 'cargo' => 'C', 'tons' => 1, 'planned_km' => 1];
        $this->post('/viajes', [...$base, 'value_amount' => '-5'])->assertSessionHasErrors('value_amount');
        $this->post('/viajes', [...$base, 'value_amount' => '10.123'])->assertSessionHasErrors('value_amount');
        $this->post('/viajes', [...$base, 'value_amount' => '10000000000'])->assertSessionHasErrors('value_amount');
        $this->post('/viajes', [...$base, 'viaticos_qty' => '-1'])->assertSessionHasErrors('viaticos_qty');
        $this->post('/viajes', [...$base, 'viaticos_qty' => 'abc'])->assertSessionHasErrors('viaticos_qty');
        $this->post('/viajes', [...$base, 'viaticos_qty' => 1000])->assertSessionHasErrors('viaticos_qty');
        $this->assertSame(0, Trip::count());
    }

    public function test_driver_defaults_to_the_trucks_current_driver_when_omitted(): void
    {
        $this->operator();
        $truck = Truck::factory()->create();
        $driver = Driver::factory()->create();
        $other = Driver::factory()->create();
        $this->post('/choferes/'.$driver->id.'/asignacion', ['truck_id' => $truck->id])->assertSessionHasNoErrors();
        $data = ['truck_id' => $truck->id, 'date' => '2026-10-12', 'origin' => 'A', 'destination' => 'B', 'cargo' => 'C', 'tons' => 1, 'planned_km' => 1];
        $this->post('/viajes', $data)->assertSessionHasNoErrors();
        $this->assertSame($driver->id, Trip::latest('id')->first()->driver_id);
        $this->post('/viajes', [...$data, 'driver_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($other->id, Trip::latest('id')->first()->driver_id);
        $archived = Driver::factory()->create(['archived_at' => now()]);
        $this->post('/viajes', [...$data, 'driver_id' => $archived->id])->assertSessionHasErrors('driver_id');
        $this->post('/viajes', [...$data, 'truck_id' => Truck::factory()->create()->id])->assertSessionHasErrors('driver_id');
    }

    public function test_commercial_fields_are_editable_on_closed_trips_with_audit(): void
    {
        $this->operator();
        $trip = $this->trip(['value_amount' => '100']);
        $trip->update(['status' => 'completado', 'actual_km' => 500]);
        $this->put('/viajes/'.$trip->id.'/comercial', ['value_amount' => '1500', 'collected' => true, 'viaticos_qty' => 2])->assertSessionHasNoErrors();
        $fresh = $trip->fresh();
        $this->assertSame('1500.00', $fresh->value_amount);
        $this->assertTrue($fresh->collected);
        $this->assertSame(2, $fresh->viaticos_qty);
        $this->assertSame('8.00', $fresh->commission_rate);
        $event = AuditEvent::where('subject_id', $trip->id)->where('action', 'viaje_comercial')->firstOrFail();
        $this->assertEqualsCanonicalizing(['value_amount', 'collected', 'viaticos_qty'], array_keys($event->data['after']));
        $this->assertEquals(100, $event->data['before']['value_amount']);
        $cancelled = $this->trip();
        $cancelled->update(['status' => 'anulado']);
        $this->put('/viajes/'.$cancelled->id.'/comercial', ['value_amount' => null, 'collected' => false, 'viaticos_qty' => 3])->assertSessionHasNoErrors();
        $this->assertSame(3, $cancelled->fresh()->viaticos_qty);
        $this->put('/viajes/'.$trip->id.'/comercial', ['value_amount' => '-1', 'collected' => false, 'viaticos_qty' => 0])->assertSessionHasErrors('value_amount');
        $this->assertSame('1500.00', $trip->fresh()->value_amount);
    }

    public function test_route_edit_on_completed_trip_is_still_rejected(): void
    {
        $this->operator();
        $trip = $this->trip();
        $trip->update(['status' => 'completado', 'actual_km' => 500]);
        $this->put('/viajes/'.$trip->id, ['truck_id' => $trip->truck_id, 'driver_id' => $trip->driver_id, 'date' => '2026-10-12', 'origin' => 'Otro', 'destination' => 'Rivera', 'cargo' => 'Granos', 'tons' => 10, 'planned_km' => 500])->assertSessionHasErrors('operation');
        $this->assertSame('Montevideo', $trip->fresh()->origin);
    }

    public function test_commercial_update_respects_tenancy_and_authentication(): void
    {
        $this->operator();
        $trip = $this->trip();
        $this->operator();
        $this->put('/viajes/'.$trip->id.'/comercial', ['collected' => true, 'viaticos_qty' => 1])->assertNotFound();
        auth()->logout();
        $this->put('/viajes/'.$trip->id.'/comercial', ['collected' => true, 'viaticos_qty' => 1])->assertRedirect('/login');
    }

    public function test_viaticos_category_is_rejected_for_new_expenses_but_legacy_rows_remain(): void
    {
        $this->operator();
        $trip = $this->trip();
        foreach (['combustible', 'peajes', 'otros'] as $category) {
            $this->post('/viajes/'.$trip->id.'/gastos', $this->expense(['category' => $category]))->assertSessionHasNoErrors();
        }
        $this->post('/viajes/'.$trip->id.'/gastos', $this->expense(['category' => 'viaticos']))->assertSessionHasErrors('category');
        $expense = TripExpense::firstOrFail();
        $this->put('/viajes/'.$trip->id.'/gastos/'.$expense->id, $this->expense(['category' => 'viaticos']))->assertSessionHasErrors('category');
        $legacy = new TripExpense(['date' => '2026-10-12', 'category' => 'viaticos', 'description' => 'Viático', 'amount' => '800.00', 'currency' => 'UYU']);
        $legacy->trip_id = $trip->id;
        $legacy->created_by = auth()->id();
        $legacy->save();
        $this->get('/viajes/'.$trip->id)->assertInertia(fn (Assert $p) => $p->has('trip.expenses', 4)->where('expenseTotals.0.total', '1176.05'));
    }

    public function test_legacy_viaticos_expense_can_be_edited_keeping_its_category(): void
    {
        $this->operator();
        $trip = $this->trip();
        $legacy = new TripExpense(['date' => '2026-10-12', 'category' => 'viaticos', 'description' => 'Viático', 'amount' => '800.00', 'currency' => 'UYU']);
        $legacy->trip_id = $trip->id;
        $legacy->created_by = auth()->id();
        $legacy->save();

        $this->put('/viajes/'.$trip->id.'/gastos/'.$legacy->id, $this->expense(['category' => 'viaticos', 'description' => 'Viático corregido']))->assertSessionHasNoErrors();
        $this->assertSame('Viático corregido', $legacy->fresh()->description);
        $this->assertSame('viaticos', $legacy->fresh()->category);
        $this->put('/viajes/'.$trip->id.'/gastos/'.$legacy->id, $this->expense(['category' => 'peajes']))->assertSessionHasNoErrors();
        $this->post('/viajes/'.$trip->id.'/gastos', $this->expense(['category' => 'viaticos']))->assertSessionHasErrors('category');
    }

    public function test_trip_detail_exposes_commercial_data_and_computed_viaticos_amount(): void
    {
        $this->operator();
        $trip = $this->trip(['value_amount' => '1250.5', 'collected' => true, 'viaticos_qty' => 2]);
        $this->get('/viajes/'.$trip->id)->assertInertia(fn (Assert $p) => $p->where('trip.value_amount', '1250.50')->where('trip.collected', true)->where('trip.viaticos_qty', 2)->where('commercial.viaticos_amount', '1.600,00'));
    }
}
