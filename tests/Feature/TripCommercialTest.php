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
}
