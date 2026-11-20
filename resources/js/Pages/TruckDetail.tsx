import { Link } from '@inertiajs/react';
import {
  Layout,
  Back,
  Badge,
  Details,
  Section,
  ArchiveButton,
  FilterBar,
  TripsTable,
  tripOptions,
  ReportStats,
  Empty,
} from '../components/ui';
import {
  type Truck,
  type Trip,
  type PageData,
  type Filters,
  type Totals,
  number,
  date,
  truckStatus,
} from '../types';
export default function TruckDetail({
  truck: t,
  trips,
  totals,
  filters,
}: {
  truck: Truck;
  trips: PageData<Trip>;
  totals: Totals;
  filters: Filters;
}) {
  return (
    <Layout
      title={t.plate}
      description={`${t.brand} ${t.model}`}
      actions={
        <>
          <ArchiveButton url={`/camiones/${t.id}/archivo`} archived={!!t.archived_at} />
          {!t.archived_at && (
            <Link className="btn primary" href={`/camiones/${t.id}/edit`}>
              Editar camión
            </Link>
          )}
        </>
      }
    >
      <Back href="/camiones">Toda la flota</Back>
      <div className="record-panel">
        <Badge tone={t.on_route ? 'en_viaje' : t.maintenance ? 'warning' : 'completado'}>
          {truckStatus(t)}
        </Badge>
        <Details
          items={[
            { label: 'Modelo / año', value: `${t.brand} ${t.model} · ${t.year}` },
            { label: 'Capacidad', value: `${number(t.capacity_tons)} t` },
            { label: 'Odómetro', value: `${number(t.odometer)} km` },
            { label: 'Combustible', value: t.fuel === 'diesel' ? 'Diésel' : 'GNC' },
            { label: 'Último servicio', value: date(t.last_service) },
            { label: 'Próximo servicio', value: date(t.next_service) },
          ]}
        />
      </div>
      <Section title="Chofer asignado">
        {t.current_assignment ? (
          <div className="people-list">
            <Link href={`/choferes/${t.current_assignment.driver.id}`}>
              <span className="avatar">{t.current_assignment.driver.name[0]}</span>
              <strong>{t.current_assignment.driver.name}</strong>
            </Link>
          </div>
        ) : (
          <Empty
            title="Sin chofer asignado"
            text="Podés asignar esta unidad desde la ficha de un chofer."
          />
        )}
      </Section>
      <Section title="Viajes de la unidad">
        <FilterBar filters={filters} period statusOptions={tripOptions} />
        <ReportStats totals={totals} />
        <TripsTable trips={trips} />
      </Section>
    </Layout>
  );
}
