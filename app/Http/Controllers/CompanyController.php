<?php

namespace App\Http\Controllers;

use App\Actions\ManageUsers;
use App\Actions\LoadCompanyDemo;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-platform');
        $data = $request->validate(['q' => 'nullable|string|max:100']);

        return Inertia::render('Companies', ['companies' => Company::withCount('users')->when($data['q'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%$v%"))->orderBy('name')->paginate(15)->withQueryString(), 'filters' => $data]);
    }

    public function create(): Response
    {
        Gate::authorize('manage-platform');

        return Inertia::render('CompanyForm', ['company' => null, 'platform' => true]);
    }

    public function store(CompanyRequest $request, ManageUsers $action): RedirectResponse
    {
        Gate::authorize('manage-platform');
        $data = $request->validated();
        [$company,$admin] = DB::transaction(function () use ($data, $action) {
            $company = Company::create(collect($data)->except('admin_name', 'admin_email')->all());
            $admin = $action->save($company, ['name' => $data['admin_name'], 'email' => $data['admin_email'], 'role' => 'admin', 'active' => true]);

            return [$company, $admin];
        });
        $sent = $action->invite($admin);

        return redirect()->route('companies.edit', $company)->with($sent ? 'success' : 'warning', $sent ? 'Empresa creada. Enviamos el enlace de acceso al administrador.' : 'Empresa creada. No se pudo enviar el enlace; revisá SMTP y reenviá la invitación.');
    }

    public function edit(Company $company): Response
    {
        Gate::authorize('manage-platform');

        return Inertia::render('CompanyForm', ['company' => $company, 'platform' => true, 'members' => $company->users()->where('role', 'admin')->orderBy('name')->get()]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('manage-platform');
        DB::transaction(function () use ($company, $request) {
            $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $company->update($request->validated());
            if (! $company->active) {
                $ids = $company->users()->pluck('id');
                DB::table('sessions')->whereIn('user_id', $ids)->delete();
                $company->users()->update(['remember_token' => null]);
            }
        });

        return back()->with('success', 'Empresa actualizada.');
    }

    public function settings(Request $request): Response
    {
        Gate::authorize('manage-company');

        return Inertia::render('CompanyForm', ['company' => $request->user()->company, 'platform' => false]);
    }

    public function saveSettings(CompanyRequest $request): RedirectResponse
    {
        Gate::authorize('manage-company');
        $request->user()->company->update($request->validated());

        return back()->with('success', 'Ajustes guardados.');
    }

    public function loadDemo(Request $request, LoadCompanyDemo $action): RedirectResponse
    {
        Gate::authorize('manage-company');
        $request->validate(['confirmed' => 'accepted']);
        $loaded = $action->run();

        return redirect()->route('settings')->with('success', $loaded
            ? 'Cargamos 3 camiones, 3 choferes y 6 viajes de prueba en tu empresa.'
            : 'Los datos de prueba ya estaban cargados. No se crearon duplicados.');
    }
}
