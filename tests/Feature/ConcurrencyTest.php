<?php

namespace Tests\Feature;

use App\Actions\ManageTrip;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_starts_allow_only_one_trip_for_a_truck(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Esta prueba requiere pcntl (incluido en Docker de desarrollo).');
        }
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
        $this->actingAs($user);
        $truck = Truck::factory()->create();
        $firstDriver = Driver::factory()->create();
        $secondDriver = Driver::factory()->create();
        $data = ['truck_id' => $truck->id, 'date' => '2026-10-10', 'origin' => 'Montevideo', 'destination' => 'Rivera', 'cargo' => 'Granos', 'tons' => 20, 'planned_km' => 500];
        $first = app(ManageTrip::class)->save([...$data, 'driver_id' => $firstDriver->id]);
        $second = app(ManageTrip::class)->save([...$data, 'driver_id' => $secondDriver->id]);
        DB::disconnect();
        $children = [];
        foreach ([$first->id, $second->id] as $id) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::purge();
                Auth::loginUsingId($user->id);
                try {
                    app(ManageTrip::class)->transition(Trip::findOrFail($id), 'en_viaje');
                    exit(0);
                } catch (ValidationException) {
                    exit(10);
                } catch (\Throwable $exception) {
                    fwrite(STDERR, $exception->getMessage());
                    exit(20);
                }
            }
            $this->assertGreaterThan(0, $pid);
            $children[] = $pid;
        }
        $codes = [];
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $codes[] = pcntl_wexitstatus($status);
        }
        DB::purge();
        sort($codes);
        $this->assertSame([0, 10], $codes);
        $this->assertSame(1, Trip::where('status', 'en_viaje')->count());
    }
}
