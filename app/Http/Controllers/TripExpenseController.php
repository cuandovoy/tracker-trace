<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripExpenseRequest;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Support\Audit;
use App\Support\CompanyTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TripExpenseController extends Controller
{
    public function create(Trip $trip): Response
    {
        return $this->form($trip);
    }

    public function edit(Trip $trip, TripExpense $expense): Response
    {
        abort_unless($expense->trip_id === $trip->id, 404);

        return $this->form($trip, $expense);
    }

    private function form(Trip $trip, ?TripExpense $expense = null): Response
    {
        Gate::authorize('update', $trip);

        return Inertia::render('ExpenseForm', ['trip' => $trip, 'expense' => $expense, 'today' => now(auth()->user()->company->timezone)->toDateString()]);
    }

    public function store(TripExpenseRequest $request, Trip $trip, CompanyTransaction $transaction): RedirectResponse
    {
        $transaction->run(function () use ($request, $trip): void {
            $expense = new TripExpense($request->validated());
            $expense->trip_id = $trip->id;
            $expense->created_by = $request->user()->id;
            $expense->save();
            Audit::record($trip, 'gasto_creado', ['expense_id' => $expense->id, 'after' => $expense->getAttributes()]);
        });

        return redirect('/viajes/'.$trip->id)->with('success', 'Gasto registrado.');
    }

    public function update(TripExpenseRequest $request, Trip $trip, TripExpense $expense, CompanyTransaction $transaction): RedirectResponse
    {
        abort_unless($expense->trip_id === $trip->id, 404);
        $transaction->run(function () use ($request, $trip, $expense): void {
            $expense->refresh();
            $before = $expense->getAttributes();
            $expense->update($request->validated());
            Audit::record($trip, 'gasto_editado', ['expense_id' => $expense->id, 'before' => $before, 'after' => $expense->getAttributes()]);
        });

        return redirect('/viajes/'.$trip->id)->with('success', 'Gasto actualizado.');
    }

    public function destroy(Trip $trip, TripExpense $expense, CompanyTransaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $trip);
        abort_unless($expense->trip_id === $trip->id, 404);
        $transaction->run(function () use ($trip, $expense): void {
            $expense->refresh();
            Audit::record($trip, 'gasto_eliminado', $expense->getAttributes());
            $expense->delete();
        });

        return redirect('/viajes/'.$trip->id)->with('success', 'Gasto eliminado.');
    }
}
