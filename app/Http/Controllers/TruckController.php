<?php

namespace App\Http\Controllers;

use App\Actions\ManageFleet;
use App\Http\Requests\FilterRequest;
use App\Http\Requests\TruckRequest;
use App\Models\Truck;
use App\Support\TripReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TruckController extends Controller
{
    public function index(FilterRequest $request, TripReport $reports): Response
    {
        Gate::authorize('viewAny', Truck::class);
        $filters = $request->validated();
        $query = Truck::with(['currentAssignments.driver'])->withExists(['trips as on_route' => fn ($q) => $q->where('status', 'en_viaje')])
            ->when($request->boolean('archived'), fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('plate', 'like', "%$v%")->orWhere('brand', 'like', "%$v%")->orWhere('model', 'like', "%$v%")));
        match ($filters['status'] ?? '') {
            'maintenance' => $query->where('maintenance', true),
            'on_route' => $query->whereHas('trips', fn ($q) => $q->where('status', 'en_viaje')),
            'available' => $query->where('maintenance', false)->whereDoesntHave('trips', fn ($q) => $q->where('status', 'en_viaje')),
            default => null
        };
        $month = now($request->user()->company->timezone);

        return Inertia::render('Fleet', ['trucks' => $query->orderBy('plate')->paginate(12)->withQueryString(), 'filters' => $filters,
            'stats' => ['total' => Truck::whereNull('archived_at')->count(), 'running' => Truck::whereHas('trips', fn ($q) => $q->where('status', 'en_viaje'))->count(), 'maintenance' => Truck::whereNull('archived_at')->where('maintenance', true)->count(),
                'km' => $reports->totals($reports->query(['from' => $month->copy()->startOfMonth()->toDateString(), 'to' => $month->copy()->endOfMonth()->toDateString()]))['km']]]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Truck::class);

        return Inertia::render('TruckForm', ['truck' => null]);
    }

    public function store(TruckRequest $request, ManageFleet $action): RedirectResponse
    {
        $truck = $action->save(Truck::class, $request->validated());

        return redirect('/camiones/'.$truck->id)->with('success', 'Camión creado.');
    }

    public function show(FilterRequest $request, Truck $truck, TripReport $reports): Response
    {
        Gate::authorize('view', $truck);
        $query = $reports->query($request->validated(), truckId: $truck->id);

        return Inertia::render('TruckDetail', ['truck' => $truck->load('currentAssignments.driver')->loadExists(['trips as on_route' => fn ($q) => $q->where('status', 'en_viaje')]), 'trips' => $query->orderByDesc('date')->orderByDesc('id')->paginate(15)->withQueryString(), 'totals' => $reports->totals($query), 'filters' => $request->validated()]);
    }

    public function edit(Truck $truck): Response
    {
        Gate::authorize('update', $truck);

        return Inertia::render('TruckForm', ['truck' => $truck]);
    }

    public function update(TruckRequest $request, Truck $truck, ManageFleet $action): RedirectResponse
    {
        Gate::authorize('update', $truck);
        $action->save(Truck::class, $request->validated(), $truck);

        return redirect('/camiones/'.$truck->id)->with('success', 'Camión actualizado.');
    }

    public function archive(Truck $truck, ManageFleet $action): RedirectResponse
    {
        Gate::authorize('archive', $truck);
        $action->archive($truck);

        return back()->with('success', 'Estado del camión actualizado.');
    }
}
