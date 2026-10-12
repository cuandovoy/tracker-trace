<?php

namespace App\Http\Controllers;

use App\Actions\ManageUsers;
use App\Http\Requests\UserRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private function company(Request $request, ?Company $company): Company
    {
        if ($company) {
            Gate::authorize('manage-platform');

            return $company;
        }
        Gate::authorize('manage-company');

        return $request->user()->company;
    }

    private function member(Company $company, User $member): void
    {
        abort_unless($member->company_id === $company->id && ! $member->isSuper() && (! auth()->user()->isSuper() || $member->isAdmin()), 404);
    }

    public function index(Request $request): Response
    {
        Gate::authorize('manage-company');

        return Inertia::render('Users', ['members' => $request->user()->company->users()->orderBy('name')->paginate(20)]);
    }

    public function create(Request $request, ?Company $company = null): Response
    {
        $company = $this->company($request, $company);

        return Inertia::render('UserForm', ['member' => null, 'company' => $company, 'platform' => $request->user()->isSuper()]);
    }

    public function edit(Request $request, ?Company $company = null, ?User $member = null): Response
    {
        $company = $this->company($request, $company);
        $this->member($company, $member);

        return Inertia::render('UserForm', ['member' => $member, 'company' => $company, 'platform' => $request->user()->isSuper()]);
    }

    public function store(UserRequest $request, ManageUsers $action, ?Company $company = null): RedirectResponse
    {
        $company = $this->company($request, $company);
        $member = $action->save($company, $request->validated());
        $sent = $action->invite($member);

        return redirect($request->user()->isSuper() ? '/admin/empresas/'.$company->id.'/edit' : '/usuarios')->with($sent ? 'success' : 'warning', $sent ? 'Usuario creado. Enlace de acceso enviado.' : 'Usuario creado. No se pudo enviar el enlace; revisá SMTP y reenviá la invitación.');
    }

    public function update(UserRequest $request, ManageUsers $action, ?Company $company = null, ?User $member = null): RedirectResponse
    {
        $company = $this->company($request, $company);
        $this->member($company, $member);
        $emailChanged = $member->email !== $request->email;
        $action->save($company, $request->validated(), $member);
        if ($emailChanged && ! $action->invite($member)) {
            return back()->with('warning', 'Usuario actualizado. Reenviá el enlace cuando SMTP esté disponible.');
        }

        return redirect($request->user()->isSuper() ? '/admin/empresas/'.$company->id.'/edit' : '/usuarios')->with('success', 'Usuario actualizado.');
    }

    public function invite(Request $request, ManageUsers $action, ?Company $company = null, ?User $member = null): RedirectResponse
    {
        $company = $this->company($request, $company);
        $this->member($company, $member);
        abort_unless($member->active && $company->active, 403);
        $sent = $action->invite($member);

        return back()->with($sent ? 'success' : 'warning', $sent ? 'Enlace de acceso enviado.' : 'No se pudo enviar. Esperá un minuto o revisá la configuración SMTP.');
    }
}
