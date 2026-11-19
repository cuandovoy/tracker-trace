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

class SingleDriverMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'database/migrations/2026_11_19_195917_drop_principal_from_assignments_table.php';

    private function openFor(int $companyId, int $userId, Truck $truck, Driver $driver, string $startedAt): int
    {
        return DB::table('assignments')->insertGetId(['company_id' => $companyId, 'truck_id' => $truck->id, 'driver_id' => $driver->id, 'principal' => false, 'created_by' => $userId, 'started_at' => $startedAt, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function open(int $companyId, int $userId, Truck $truck, bool $principal, string $startedAt): int
    {
        $driver = Driver::factory()->create();

        return DB::table('assignments')->insertGetId(['company_id' => $companyId, 'truck_id' => $truck->id, 'driver_id' => $driver->id, 'principal' => $principal, 'created_by' => $userId, 'started_at' => $startedAt, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_migration_keeps_principal_else_most_recent_open_assignment_and_preserves_history(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id, 'role' => 'admin']);
        $this->actingAs($user);
        $companyId = $user->company_id;
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION, '--realpath' => false])->assertExitCode(0);
        $this->assertTrue(Schema::hasColumn('assignments', 'principal'));

        $a = Truck::factory()->create();
        $s1 = $this->open($companyId, $user->id, $a, false, '2026-01-01 10:00:00');
        $p = $this->open($companyId, $user->id, $a, true, '2026-02-01 10:00:00');
        $s2 = $this->open($companyId, $user->id, $a, false, '2026-03-01 10:00:00');
        $b = Truck::factory()->create();
        $x = $this->open($companyId, $user->id, $b, false, '2026-01-01 10:00:00');
        $y = $this->open($companyId, $user->id, $b, false, '2026-03-01 10:00:00');
        $c = Truck::factory()->create();
        $single = $this->open($companyId, $user->id, $c, false, '2026-01-01 10:00:00');
        $history = DB::table('assignments')->insertGetId(['company_id' => $companyId, 'truck_id' => $c->id, 'driver_id' => Driver::factory()->create()->id, 'principal' => true, 'created_by' => $user->id, 'started_at' => '2025-01-01 10:00:00', 'ended_at' => '2025-06-01 10:00:00', 'created_at' => now(), 'updated_at' => now()]);

        $d = Driver::factory()->create();
        $t1 = Truck::factory()->create();
        $t2 = Truck::factory()->create();
        $older = $this->openFor($companyId, $user->id, $t1, $d, '2026-04-01 10:00:00');
        $newer = $this->openFor($companyId, $user->id, $t2, $d, '2026-05-01 10:00:00');

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertFalse(Schema::hasColumn('assignments', 'principal'));
        $openIds = DB::table('assignments')->whereNull('ended_at')->orderBy('id')->pluck('id')->all();
        $this->assertSame([$p, $y, $single, $newer], $openIds);
        $this->assertNotNull(DB::table('assignments')->find($older)->ended_at);
        $this->assertNotNull(DB::table('assignments')->find($s1)->ended_at);
        $this->assertNotNull(DB::table('assignments')->find($s2)->ended_at);
        $this->assertNotNull(DB::table('assignments')->find($x)->ended_at);
        $this->assertSame('2025-06-01 10:00:00', DB::table('assignments')->find($history)->ended_at);
        $this->assertSame(9, DB::table('assignments')->count());
    }
}
