<?php

namespace App\Http\Controllers;

use App\Actions\ManageAssignment;
use App\Actions\ManageFleet;
use App\Http\Requests\DriverRequest;
use App\Http\Requests\FilterRequest;
use App\Models\Driver;
use App\Models\Truck;
use App\Support\TripReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function index(FilterRequest $request): Response
    {
        Gate::authorize('viewAny', Driver::class);
        $query = Driver::with('currentAssignment.truck')->when($request->boolean('archived'), fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($request->q, fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'like', "%$v%")->orWhere('license', 'like', "%$v%")));

        return Inertia::render('Drivers', ['drivers' => $query->orderBy('name')->paginate(12)->withQueryString(), 'filters' => $request->validated()]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Driver::class);

        return Inertia::render('DriverForm', ['driver' => null]);
    }

    public function store(DriverRequest $request, ManageFleet $action): RedirectResponse
    {
        $driver = $action->save(Driver::class, $request->validated());

        return redirect('/choferes/'.$driver->id)->with('success', 'Chofer creado.');
    }

    public function edit(Driver $driver): Response
    {
        Gate::authorize('update', $driver);

        return Inertia::render('DriverForm', ['driver' => $driver]);
    }

    public function update(DriverRequest $request, Driver $driver, ManageFleet $action): RedirectResponse
    {
        Gate::authorize('update', $driver);
        $action->save(Driver::class, $request->validated(), $driver);

        return redirect('/choferes/'.$driver->id)->with('success', 'Chofer actualizado.');
    }

    public function show(FilterRequest $request, Driver $driver, TripReport $reports): Response
    {
        Gate::authorize('view', $driver);
        $query = $reports->query($request->validated(), $driver->id);

        return Inertia::render('DriverDetail', ['driver' => $driver->load(['currentAssignment.truck', 'assignments' => fn ($q) => $q->with('truck')->orderByDesc('id')->limit(30)]),
            'trips' => $query->orderByDesc('date')->orderByDesc('id')->paginate(15)->withQueryString(), 'totals' => $reports->totals($query), 'filters' => $request->validated(),
            'trucks' => Truck::whereNull('archived_at')->orderBy('plate')->get(['id', 'plate'])]);
    }

    public function archive(Driver $driver, ManageFleet $action): RedirectResponse
    {
        Gate::authorize('archive', $driver);
        $action->archive($driver);

        return back()->with('success', 'Estado del chofer actualizado.');
    }

    public function assign(Request $request, Driver $driver, ManageAssignment $action): RedirectResponse
    {
        Gate::authorize('update', $driver);
        $data = $request->validate(['truck_id' => ['nullable', 'integer', Rule::exists('trucks', 'id')->where('company_id', $request->user()->company_id)->whereNull('archived_at')], 'principal' => 'required|boolean']);
        $action->assign($driver, $data['truck_id'] ?? null, $data['principal']);

        return back()->with('success', 'Asignación actualizada.');
    }

    public function pdf(FilterRequest $request, Driver $driver, TripReport $reports): \Illuminate\Http\Response
    {
        Gate::authorize('view', $driver);
        $filters = $request->validated();
        $query = $reports->query($filters, $driver->id);

        return Pdf::loadView('reports.driver', ['company' => $request->user()->company, 'driver' => $driver, 'trips' => $query->orderBy('date')->orderBy('id')->get(), 'totals' => $reports->totals($query), 'filters' => $filters])->setPaper('a4', 'landscape')->download('viajes-chofer-'.$driver->id.'.pdf');
    }
}
