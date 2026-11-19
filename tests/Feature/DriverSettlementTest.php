<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\Truck;
use App\Models\User;
use App\Support\DriverSettlement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DriverSettlementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['company_id' => Company::factory()->create()->id, 'role' => 'operator']);
        $this->actingAs($this->user);
        $this->driver = Driver::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function trip(array $extra = [], ?Driver $driver = null): Trip
    {
        return Trip::create([...[
            'truck_id' => Truck::factory()->create()->id,
            'driver_id' => ($driver ?? $this->driver)->id,
            'date' => '2026-11-10',
            'origin' => 'Montevideo',
            'destination' => 'Rivera',
            'cargo' => 'Granos',
            'tons' => 10,
            'planned_km' => 300,
            'status' => 'completado',
            'created_by' => $this->user->id,
        ], ...$extra]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settlement(string $month = '2026-11', ?Driver $driver = null): array
    {
        return app(DriverSettlement::class)->for($driver ?? $this->driver, CarbonImmutable::parse($month.'-01'));
    }

    public function test_totals_are_exact_to_the_cent(): void
    {
        $this->trip(['value_amount' => '1000.00', 'viaticos_qty' => 2]);
        $this->trip(['value_amount' => '250.50', 'viaticos_qty' => 0]);
        $totals = $this->settlement()['totals'];

        $this->assertSame(125050, $totals['value']);
        $this->assertSame(10004, $totals['commission']);
        $this->assertSame(2, $totals['viaticos_qty']);
        $this->assertSame(160000, $totals['viaticos']);
        $this->assertSame(170004, $totals['payable']);
        $this->assertSame(2, $totals['trips']);
    }

    public function test_only_trips_dated_inside_the_month_are_included(): void
    {
        foreach (['2026-10-31', '2026-11-01', '2026-11-30', '2026-12-01'] as $date) {
            $this->trip(['date' => $date]);
        }
        $rows = $this->settlement()['rows'];

        $this->assertSame(['01/11/2026', '30/11/2026'], array_column($rows, 'date'));
    }

    public function test_all_statuses_are_included_with_their_status_visible(): void
    {
        foreach (['programado', 'en_viaje', 'completado', 'anulado'] as $status) {
            $this->trip(['status' => $status, 'value_amount' => '100.00']);
        }
        $result = $this->settlement();

        $this->assertSame(['Programado', 'En viaje', 'Completado', 'Anulado'], array_column($result['rows'], 'status'));
        $this->assertSame(40000, $result['totals']['value']);
    }

    public function test_trip_without_value_counts_as_zero_but_keeps_viaticos(): void
    {
        $this->trip(['value_amount' => null, 'viaticos_qty' => 1]);
        $result = $this->settlement();

        $this->assertNull($result['rows'][0]['value']);
        $this->assertSame(0, $result['totals']['commission']);
        $this->assertSame(80000, $result['totals']['viaticos']);
    }

    public function test_km_falls_back_to_planned_km(): void
    {
        $this->trip(['actual_km' => 410, 'planned_km' => 300, 'date' => '2026-11-01']);
        $this->trip(['actual_km' => null, 'planned_km' => 300, 'date' => '2026-11-02']);

        $this->assertSame(['410,0', '300,0'], array_column($this->settlement()['rows'], 'km'));
    }

    public function test_rows_expose_the_mandatory_columns(): void
    {
        $this->trip(['customer_name' => 'Acopio Sur', 'collected' => true, 'viaticos_qty' => 3, 'value_amount' => '10.00']);
        $row = $this->settlement()['rows'][0];

        $this->assertSame('10/11/2026', $row['date']);
        $this->assertSame('Montevideo', $row['origin']);
        $this->assertSame('Rivera', $row['destination']);
        $this->assertSame('Granos', $row['cargo']);
        $this->assertSame(Truck::first()->plate, $row['plate']);
        $this->assertSame('Acopio Sur', $row['customer']);
        $this->assertSame(3, $row['viaticos_qty']);
        $this->assertSame(1000, $row['value']);
        $this->assertTrue($row['collected']);
    }

    public function test_snapshots_protect_old_trips_from_config_changes(): void
    {
        $this->trip(['value_amount' => '1000.00', 'viaticos_qty' => 1]);
        config(['fleet.commission_rate' => '10.00', 'fleet.viatico_unit_amount' => '1000.00']);
        $this->trip(['value_amount' => '1000.00', 'viaticos_qty' => 1]);
        $totals = $this->settlement()['totals'];

        $this->assertSame(8000 + 10000, $totals['commission']);
        $this->assertSame(80000 + 100000, $totals['viaticos']);
    }

    public function test_commission_is_rounded_half_up_per_trip(): void
    {
        config(['fleet.commission_rate' => '12.50']);
        $this->trip(['value_amount' => '0.04']);
        $this->trip(['value_amount' => '0.04']);

        $this->assertSame(2, $this->settlement()['totals']['commission']);
    }

    public function test_empty_month_has_zero_totals(): void
    {
        $result = $this->settlement();

        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['totals']['payable']);
    }

    public function test_other_drivers_and_companies_are_excluded_and_driver_id_is_the_source(): void
    {
        $this->trip();
        $this->trip([], Driver::factory()->create());
        $otherCompany = Company::factory()->create();
        $foreign = User::factory()->create(['company_id' => $otherCompany->id]);
        $this->actingAs($foreign);
        $foreignDriver = Driver::factory()->create();
        $this->trip(['created_by' => $foreign->id], $foreignDriver);
        $this->actingAs($this->user);

        $this->assertCount(1, $this->settlement()['rows']);
        $this->assertCount(0, $this->settlement('2026-11', $foreignDriver)['rows']);
    }

    public function test_legacy_viaticos_expenses_are_ignored(): void
    {
        $trip = $this->trip(['viaticos_qty' => 0]);
        $expense = new TripExpense(['date' => '2026-11-10', 'category' => 'viaticos', 'description' => 'Viático', 'amount' => '800.00', 'currency' => 'UYU']);
        $expense->trip_id = $trip->id;
        $expense->created_by = $this->user->id;
        $expense->save();

        $this->assertSame(0, $this->settlement()['totals']['viaticos']);
    }

    public function test_pdf_is_a_landscape_download_named_after_driver_and_month(): void
    {
        $this->trip(['value_amount' => '100.00']);
        $response = $this->get('/choferes/'.$this->driver->id.'/liquidacion/pdf?month=2026-11');

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('liquidacion-'.$this->driver->id.'-2026-11.pdf', $response->headers->get('content-disposition'));
    }

    public function test_pdf_for_an_empty_month_still_renders(): void
    {
        $this->get('/choferes/'.$this->driver->id.'/liquidacion/pdf?month=2026-01')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_csv_uses_bom_semicolons_decimal_commas_and_totals_last(): void
    {
        $this->trip(['value_amount' => '1250.5', 'viaticos_qty' => 1, 'customer_name' => 'Acopio']);
        $response = $this->get('/choferes/'.$this->driver->id.'/liquidacion/csv?month=2026-11');
        $body = $response->streamedContent();

        $response->assertOk();
        $this->assertStringContainsString('liquidacion-'.$this->driver->id.'-2026-11.csv', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', substr($body, 3)))));
        $this->assertStringStartsWith('Fecha;Origen;Destino;Producto;Matrícula;Cliente;Viáticos;Facturado;Cobrado;Kilómetros;Estado', $lines[0]);
        $this->assertStringContainsString(';1250,50;No;300,0;Completado', $lines[1]);
        $this->assertStringContainsString('Total a pagar', end($lines));
        $this->assertStringContainsString('900,04', end($lines));
    }

    public function test_csv_renders_an_empty_cell_for_a_trip_without_value(): void
    {
        $this->trip(['value_amount' => null, 'viaticos_qty' => 1]);
        $body = $this->get('/choferes/'.$this->driver->id.'/liquidacion/csv?month=2026-11')->streamedContent();
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', substr($body, 3)))));

        $this->assertStringContainsString(';1;;No;300,0;Completado', $lines[1]);
        $this->assertStringNotContainsString('—', $lines[1]);
    }

    public function test_driver_detail_exposes_current_month_in_company_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-30 23:30:00', 'America/Montevideo'));
        $this->get('/choferes/'.$this->driver->id)->assertInertia(fn ($page) => $page->where('currentMonth', '2026-11'));
    }

    public function test_malformed_fleet_config_fails_loudly_on_trip_creation(): void
    {
        config(['fleet.commission_rate' => '8,5']);
        $this->expectException(\InvalidArgumentException::class);
        $this->trip();
    }

    public function test_csv_neutralizes_formula_prefixes(): void
    {
        $this->trip(['origin' => '=SUM(A1)', 'destination' => '+1', 'cargo' => '-2', 'customer_name' => '@cmd']);
        $body = $this->get('/choferes/'.$this->driver->id.'/liquidacion/csv?month=2026-11')->streamedContent();

        foreach (["'=SUM(A1)", "'+1", "'-2", "'@cmd"] as $cell) {
            $this->assertStringContainsString($cell, $body);
        }
    }

    public function test_month_defaults_to_the_current_month_in_company_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-30 23:30:00', 'America/Montevideo'));
        $response = $this->get('/choferes/'.$this->driver->id.'/liquidacion/csv');

        $this->assertStringContainsString('liquidacion-'.$this->driver->id.'-2026-11.csv', $response->headers->get('content-disposition'));
    }

    public function test_malformed_month_is_rejected(): void
    {
        $this->get('/choferes/'.$this->driver->id.'/liquidacion/pdf?month=11-2026')->assertSessionHasErrors('month');
        $this->get('/choferes/'.$this->driver->id.'/liquidacion/csv?month=nope')->assertSessionHasErrors('month');
    }

    public function test_other_company_driver_and_guests_are_blocked(): void
    {
        $foreign = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $this->actingAs($foreign);
        $foreignDriver = Driver::factory()->create();
        $this->actingAs($this->user);

        $this->get('/choferes/'.$foreignDriver->id.'/liquidacion/pdf')->assertNotFound();
        $this->get('/choferes/'.$foreignDriver->id.'/liquidacion/csv')->assertNotFound();

        auth()->logout();
        $this->get('/choferes/'.$this->driver->id.'/liquidacion/pdf')->assertRedirect('/login');
        $this->get('/choferes/'.$this->driver->id.'/liquidacion/csv')->assertRedirect('/login');
    }

    public function test_settlement_routes_are_throttled(): void
    {
        foreach (['drivers.settlement.pdf', 'drivers.settlement.csv'] as $name) {
            $this->assertContains('throttle:20,1', app('router')->getRoutes()->getByName($name)->gatherMiddleware());
        }
    }
}
