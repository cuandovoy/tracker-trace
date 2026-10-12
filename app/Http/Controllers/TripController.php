<?php

namespace App\Http\Controllers;

use App\Actions\ManageTrip;
use App\Http\Requests\FilterRequest;
use App\Http\Requests\TripRequest;
use App\Models\AuditEvent;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Truck;
use App\Support\TripReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    private function options(): array
    {
        return ['trucks' => Truck::whereNull('archived_at')->orderBy('plate')->get(['id', 'plate', 'capacity_tons']), 'drivers' => Driver::whereNull('archived_at')->orderBy('name')->get(['id', 'name'])];
    }

    public function index(FilterRequest $request, TripReport $reports): Response
    {
        Gate::authorize('viewAny', Trip::class);
        $query = $reports->query($request->validated());

        return Inertia::render('Trips', [...$this->options(), 'trips' => $query->orderByDesc('date')->orderByDesc('id')->paginate(20)->withQueryString(), 'totals' => $reports->totals($query), 'filters' => $request->validated()]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Trip::class);

        return Inertia::render('TripForm', [...$this->options(), 'trip' => null, 'today' => now(auth()->user()->company->timezone)->toDateString()]);
    }

    public function store(TripRequest $request, ManageTrip $action): RedirectResponse
    {
        $trip = $action->save($request->validated());

        return redirect('/viajes/'.$trip->id)->with('success', 'Viaje creado.');
    }

    public function show(Trip $trip): Response
    {
        Gate::authorize('view', $trip);

        return Inertia::render('TripDetail', ['trip' => $trip->load('truck', 'driver'), 'events' => AuditEvent::with('user:id,name')->where('subject_type', 'trips')->where('subject_id', $trip->id)->orderByDesc('id')->get()]);
    }

    public function edit(Trip $trip): Response
    {
        Gate::authorize('update', $trip);
        abort_unless($trip->status === 'programado', 403);

        return Inertia::render('TripForm', [...$this->options(), 'trip' => $trip, 'today' => now(auth()->user()->company->timezone)->toDateString()]);
    }

    public function update(TripRequest $request, Trip $trip, ManageTrip $action): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $action->save($request->validated(), $trip);

        return redirect('/viajes/'.$trip->id)->with('success', 'Viaje actualizado.');
    }

    public function transition(Request $request, Trip $trip, ManageTrip $action): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $data = $request->validate(['status' => ['required', Rule::in(['en_viaje', 'completado', 'anulado'])], 'actual_km' => 'nullable|required_if:status,completado|numeric|min:0|max:9999999', 'cancellation_reason' => 'nullable|required_if:status,anulado|string|min:3|max:2000']);
        $action->transition($trip, $data['status'], $data);

        return back()->with('success', 'Estado del viaje actualizado.');
    }
}
