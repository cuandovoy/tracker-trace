import { Layout, AddLink, FilterBar, TripsTable, ReportStats, tripOptions } from '../components/ui';
import type { Trip, PageData, Filters, Totals } from '../types';
export default function Trips({
  trips,
  totals,
  filters,
  trucks,
  drivers,
}: {
  trips: PageData<Trip>;
  totals: Totals;
  filters: Filters;
  trucks: { id: number; plate: string }[];
  drivers: { id: number; name: string }[];
}) {
  return (
    <Layout
      title="Viajes"
      description="De la planificación a la entrega. Toda la operación en un lugar."
      actions={<AddLink href="/viajes/create">Nuevo viaje</AddLink>}
    >
      <ReportStats totals={totals} />
      <FilterBar
        filters={filters}
        period
        statusOptions={tripOptions}
        extra={[
          {
            name: 'truck_id',
            label: 'Todos los camiones',
            options: trucks.map((t) => ({ value: String(t.id), label: t.plate })),
          },
          {
            name: 'driver_id',
            label: 'Todos los choferes',
            options: drivers.map((d) => ({ value: String(d.id), label: d.name })),
          },
        ]}
      />
      <p className="report-note">Los totales realizados incluyen únicamente viajes completados.</p>
      <TripsTable trips={trips} />
    </Layout>
  );
}
