<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DemoAndExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_load_requires_admin_confirmation_and_is_idempotent(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $this->actingAs($user);
        $this->post('/ajustes/datos-prueba', ['confirmed' => true])->assertForbidden();
        $user->update(['role' => 'admin']);
        $this->post('/ajustes/datos-prueba')->assertSessionHasErrors('confirmed');
        $this->post('/ajustes/datos-prueba', ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame(3, Truck::count());
        $this->assertSame(3, Driver::count());
        $this->assertSame(6, Trip::count());
        $this->assertNotNull($company->fresh()->demo_loaded_at);
        $this->post('/ajustes/datos-prueba', ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame(6, Trip::count());
    }
}
