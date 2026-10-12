<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#16343b}h1{font-size:22px;margin:0 0 8px}h2{font-size:16px}p{line-height:1.6}table{border-collapse:collapse;width:100%;margin-top:20px}th{background:#16343b;color:#fff;padding:8px;text-align:left}td{padding:8px;border-bottom:1px solid #ddd}tfoot td{font-weight:bold;background:#ecebe4}.muted{color:#5c626b}.number{text-align:right}tr{page-break-inside:avoid}
</style></head><body>
<h1>{{ $company->name }}</h1><p>{{ $company->tax_id }} · {{ $company->address }} · {{ $company->phone }}</p>
<h2>Planilla de viajes · {{ $driver->name }}</h2>
<p>Licencia {{ $driver->license }} · {{ $driver->category }} · {{ $driver->phone }}<br>
Período: {{ $filters['from'] ?? 'Sin límite inicial' }} — {{ $filters['to'] ?? 'Sin límite final' }}
 · Estado: {{ $filters['status'] ?? 'Todos' }}<br>
Emitido: {{ now($company->timezone)->format('d/m/Y H:i') }} ({{ $company->timezone }})
@if(!empty($filters['q']))<br>Búsqueda: {{ $filters['q'] }}@endif</p>
<table><thead><tr><th>Fecha</th><th>Camión</th><th>Origen / destino</th><th>Carga</th><th>Ton.</th><th>Km previstos</th><th>Km realizados</th><th>Estado</th></tr></thead><tbody>
@forelse($trips as $trip)<tr><td>{{ \Carbon\Carbon::parse($trip->date)->format('d/m/Y') }}</td><td>{{ $trip->truck->plate }}</td><td>{{ $trip->origin }} → {{ $trip->destination }}</td><td>{{ $trip->cargo }}</td><td class="number">{{ number_format($trip->tons,2,',','.') }}</td><td class="number">{{ number_format($trip->planned_km,1,',','.') }}</td><td class="number">{{ $trip->actual_km === null ? '—' : number_format($trip->actual_km,1,',','.') }}</td><td>{{ ['programado'=>'Programado','en_viaje'=>'En viaje','completado'=>'Completado','anulado'=>'Anulado'][$trip->status] }}</td></tr>
@empty<tr><td colspan="8">Sin viajes para los filtros seleccionados.</td></tr>@endforelse
</tbody><tfoot><tr><td colspan="4">Realizado · {{ $totals['completed'] }} viajes completados</td><td class="number">{{ number_format($totals['tons'],2,',','.') }}</td><td></td><td class="number">{{ number_format($totals['km'],1,',','.') }}</td><td>{{ $totals['total'] }} registros</td></tr></tfoot></table>
<p class="muted">Los totales realizados incluyen únicamente viajes completados. La matrícula corresponde al camión de cada viaje, independientemente de la asignación habitual actual.</p>
</body></html>
