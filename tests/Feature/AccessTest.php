<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['company_id' => Company::factory()->create()->id, 'role' => 'admin']);
    }

    public function test_guest_and_operator_permissions(): void
    {
        $this->get('/camiones')->assertRedirect('/login');
        $this->post('/viajes', [])->assertRedirect('/login');
        $user = $this->admin();
        $user->update(['role' => 'operator']);
        $this->actingAs($user);
        foreach (['/usuarios', '/ajustes', '/admin/empresas'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/usuarios', ['name' => 'Intruso', 'email' => 'intruso@example.test', 'role' => 'admin', 'active' => true])->assertForbidden();
        $this->put('/ajustes', ['name' => 'Cambio'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.test']);
    }

    public function test_tenant_data_cannot_be_read_changed_exported_or_linked(): void
    {
        $a = $this->admin();
        $b = $this->admin();
        $this->actingAs($a);
        $truck = Truck::factory()->create(['plate' => 'PRIVADO']);
        $driver = Driver::factory()->create(['name' => 'Chofer privado']);
        $payload = ['truck_id' => $truck->id, 'driver_id' => $driver->id, 'date' => '2026-10-10', 'origin' => 'Origen privado', 'destination' => 'Rivera', 'cargo' => 'Carga', 'tons' => 10, 'planned_km' => 100];
        $this->post('/viajes', $payload);
        $trip = Trip::first();
        $this->actingAs($b);
        foreach (['/camiones/'.$truck->id, '/choferes/'.$driver->id, '/choferes/'.$driver->id.'/pdf', '/viajes/'.$trip->id, '/usuarios/'.$a->id.'/edit'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post('/camiones/'.$truck->id.'/archivo')->assertNotFound();
        $this->post('/viajes/'.$trip->id.'/estado', ['status' => 'en_viaje'])->assertNotFound();
        $this->post('/viajes', $payload)->assertSessionHasErrors(['truck_id', 'driver_id']);
        $this->get('/camiones')->assertInertia(fn (Assert $p) => $p->has('trucks.data', 0)->where('stats.total', 0));
        $this->get('/viajes?truck_id='.$truck->id)->assertSessionHasErrors('truck_id');
        $this->get('/choferes')->assertInertia(fn (Assert $p) => $p->has('drivers.data', 0));
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'programado', 'company_id' => $a->company_id]);
    }

    public function test_company_id_and_privileged_payload_attributes_are_not_trusted(): void
    {
        $a = $this->admin();
        $b = $this->admin();
        $this->actingAs($a);
        $data = Truck::factory()->make()->only('plate', 'brand', 'model', 'year', 'capacity_tons', 'odometer', 'fuel', 'maintenance');
        $this->post('/camiones', [...$data, 'company_id' => $b->company_id, 'archived_at' => now()])->assertSessionHasNoErrors();
        $truck = Truck::first();
        $this->assertSame($a->company_id, $truck->company_id);
        $this->assertNull($truck->archived_at);
        $this->post('/usuarios', ['name' => 'Nuevo', 'email' => 'nuevo@example.test', 'role' => 'superadmin', 'active' => true, 'company_id' => $b->company_id])->assertSessionHasErrors('role');
    }

    public function test_disabled_company_and_user_cannot_login_or_keep_access(): void
    {
        $admin = $this->admin();
        $admin->update(['password' => 'AValidPassword123']);
        $admin->company->update(['active' => false]);
        $this->post('/login', ['email' => $admin->email, 'password' => 'AValidPassword123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($admin)->get('/camiones')->assertRedirect('/login');
        $this->assertGuest();
        $admin->company->update(['active' => true]);
        $admin->update(['active' => false]);
        $this->post('/login', ['email' => $admin->email, 'password' => 'AValidPassword123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_superadmin_can_provision_company_and_manage_its_admin(): void
    {
        Notification::fake();
        $super = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($super);
        $this->get('/admin/empresas')->assertOk();
        $data = ['name' => 'Empresa nueva', 'timezone' => 'America/Montevideo', 'active' => true, 'admin_name' => 'Ana', 'admin_email' => 'ana@example.test'];
        $this->post('/admin/empresas', $data)->assertRedirect();
        $company = Company::first();
        $admin = $company->users()->first();
        $this->assertSame('admin', $admin->role);
        Notification::assertSentTo($admin, ResetPassword::class);
        $base = '/admin/empresas/'.$company->id;
        $this->get($base.'/edit')->assertOk();
        $this->get($base.'/usuarios/'.$admin->id.'/edit')->assertOk();
        $this->put($base.'/usuarios/'.$admin->id, ['name' => 'Ana editada', 'email' => $admin->email, 'role' => 'admin', 'active' => true])->assertSessionHasNoErrors();
        $this->assertSame('Ana editada', $admin->fresh()->name);
        $this->post($base.'/usuarios/'.$admin->id.'/invitacion')->assertRedirect();
        $this->put($base, ['name' => 'Empresa nueva', 'timezone' => 'America/Montevideo', 'active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($company->fresh()->active);
    }

    public function test_company_admin_manages_users_but_cannot_remove_last_admin(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->get('/usuarios/'.$admin->id.'/edit')->assertOk();
        $this->put('/usuarios/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'operator', 'active' => true])->assertSessionHasErrors('active');
        $this->post('/usuarios', ['name' => 'Operador', 'email' => 'operador@example.test', 'role' => 'operator', 'active' => true])->assertSessionHasNoErrors();
        $member = User::where('email', 'operador@example.test')->first();
        Notification::assertSentTo($member, ResetPassword::class);
        $this->assertSame($admin->company_id, $member->company_id);
        $this->put('/usuarios/'.$member->id, ['name' => 'Operador editado', 'email' => $member->email, 'role' => 'operator', 'active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->active);
    }

    public function test_reset_link_can_set_password_once_and_login(): void
    {
        Notification::fake();
        $user = $this->admin();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success');
        $token = '';
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'NewPassword123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/register')->assertNotFound();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'rate@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'rate@example.test', 'password' => 'wrong'])->assertStatus(429);
    }
}
