<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TripCompensationMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'database/migrations/2026_11_19_200140_add_compensation_to_trips_table.php';

    public function test_migration_backfills_existing_trips_with_config_defaults(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id, 'role' => 'admin']);
        $this->actingAs($user);
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION, '--realpath' => false])->assertExitCode(0);
        $this->assertFalse(Schema::hasColumn('trips', 'commission_rate'));
        $tripId = DB::table('trips')->insertGetId(['company_id' => $user->company_id, 'truck_id' => Truck::factory()->create()->id, 'driver_id' => Driver::factory()->create()->id, 'date' => '2026-10-01', 'origin' => 'A', 'destination' => 'B', 'cargo' => 'C', 'tons' => 1, 'planned_km' => 10, 'status' => 'programado', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('migrate')->assertExitCode(0);

        $trip = DB::table('trips')->find($tripId);
        $this->assertSame('8.00', $trip->commission_rate);
        $this->assertSame('800.00', $trip->viatico_unit_amount);
        $this->assertSame(0, (int) $trip->collected);
        $this->assertSame(0, (int) $trip->viaticos_qty);
        $this->assertNull($trip->value_amount);
    }
}
