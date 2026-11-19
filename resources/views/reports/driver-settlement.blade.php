<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#16343b}h1{font-size:22px;margin:0 0 8px}h2{font-size:16px}p{line-height:1.6}table{border-collapse:collapse;width:100%;margin-top:20px}th{background:#16343b;color:#fff;padding:8px;text-align:left}td{padding:8px;border-bottom:1px solid #ddd}tfoot td{font-weight:bold;background:#ecebe4}.muted{color:#5c626b}.number{text-align:right}tr{page-break-inside:avoid}
</style></head><body>
<h1>{{ $company->name }}</h1><p>{{ $company->tax_id }} · {{ $company->address }} · {{ $company->phone }}</p>
<h2>Liquidación mensual · {{ $driver->name }}</h2>
<p>Licencia {{ $driver->license }} · {{ $driver->category }} · {{ $driver->phone }}<br>
Período: {{ $month->format('m/Y') }}<br>
Emitido: {{ now($company->timezone)->format('d/m/Y H:i') }} ({{ $company->timezone }})</p>
<table><thead><tr><th>Fecha</th><th>Origen</th><th>Destino</th><th>Producto</th><th>Matrícula</th><th>Cliente</th><th class="number">Viáticos</th><th class="number">Facturado</th><th>Cobrado</th><th class="number">Km</th><th>Estado</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $row['date'] }}</td><td>{{ $row['origin'] }}</td><td>{{ $row['destination'] }}</td><td>{{ $row['cargo'] }}</td><td>{{ $row['plate'] }}</td><td>{{ $row['customer'] }}</td><td class="number">{{ $row['viaticos_qty'] }}</td><td class="number">{{ $row['value'] === null ? '—' : \App\Support\Money::format($row['value']) }}</td><td>{{ $row['collected'] ? 'Sí' : 'No' }}</td><td class="number">{{ $row['km'] }}</td><td>{{ $row['status'] }}</td></tr>
@empty<tr><td colspan="11">Sin viajes en el mes seleccionado.</td></tr>@endforelse
</tbody><tfoot>
<tr><td colspan="6">Totales · {{ $totals['trips'] }} viajes</td><td class="number">{{ $totals['viaticos_qty'] }}</td><td class="number">{{ \App\Support\Money::format($totals['value']) }}</td><td colspan="3"></td></tr>
<tr><td colspan="7">Comisión</td><td class="number">{{ \App\Support\Money::format($totals['commission']) }}</td><td colspan="3"></td></tr>
<tr><td colspan="7">Viáticos ({{ $totals['viaticos_qty'] }})</td><td class="number">{{ \App\Support\Money::format($totals['viaticos']) }}</td><td colspan="3"></td></tr>
<tr><td colspan="7">TOTAL A PAGAR</td><td class="number">{{ \App\Support\Money::format($totals['payable']) }}</td><td colspan="3"></td></tr>
</tfoot></table>
<p class="muted">Incluye todos los viajes del mes del chofer, cualquiera sea su estado. La comisión y los viáticos usan los valores vigentes al crear cada viaje. Importes en UYU.</p>
</body></html>
