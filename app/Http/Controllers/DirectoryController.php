<?php

namespace App\Http\Controllers;

use App\Http\Requests\DirectoryRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Trip;
use App\Support\Audit;
use App\Support\CompanyTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryController extends Controller
{
    private function model(Request $request): Model
    {
        return $request->route('kind') === 'clientes' ? new Customer : new Location;
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Trip::class);
        $filters = $request->validate(['q' => 'nullable|string|max:100']);

        return Inertia::render('Directory', ['kind' => $request->route('kind'), 'filters' => $filters,
            'records' => $this->model($request)->newQuery()->when($filters['q'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%$v%"))->orderBy('name')->orderBy('id')->paginate(20)->withQueryString()]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Trip::class);

        return $this->form($request);
    }

    public function edit(Request $request): Response
    {
        Gate::authorize('create', Trip::class);

        return $this->form($request, $this->model($request)->newQuery()->findOrFail($request->route('record')));
    }

    private function form(Request $request, ?Model $record = null): Response
    {
        return Inertia::render('DirectoryForm', ['kind' => $request->route('kind'), 'record' => $record,
            'customers' => Customer::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(DirectoryRequest $request, CompanyTransaction $transaction): RedirectResponse
    {
        $transaction->run(function () use ($request): void {
            $record = $this->model($request)->newQuery()->create($request->validated());
            Audit::record($record, 'datos_guardados', $request->validated());
        });

        return redirect('/'.$request->route('kind'))->with('success', 'Registro creado. Ya está disponible al programar un viaje.');
    }

    public function update(DirectoryRequest $request, CompanyTransaction $transaction): RedirectResponse
    {
        $transaction->run(function () use ($request): void {
            $record = $this->model($request)->newQuery()->findOrFail($request->route('record'));
            $record->update($request->validated());
            Audit::record($record, 'datos_guardados', $request->validated());
        });

        return redirect('/'.$request->route('kind'))->with('success', 'Registro actualizado. Los viajes existentes conservan sus datos.');
    }
}
