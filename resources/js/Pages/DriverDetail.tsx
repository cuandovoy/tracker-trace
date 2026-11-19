import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Download, FileSpreadsheet } from 'lucide-react';
import {
  Layout,
  Back,
  Details,
  Section,
  ArchiveButton,
  FilterBar,
  TripsTable,
  tripOptions,
  ReportStats,
  Editor,
} from '../components/ui';
import { type Driver, type Trip, type PageData, type Filters, type Totals, date } from '../types';
export default function DriverDetail({
  driver: d,
  trips,
  totals,
  filters,
  trucks,
  currentMonth,
}: {
  driver: Driver;
  trips: PageData<Trip>;
  totals: Totals;
  filters: Filters;
  trucks: { id: number; plate: string }[];
  currentMonth: string;
}) {
  const [month, setMonth] = useState(currentMonth);
  const query = new URLSearchParams(filters);
  query.delete('page');
  return (
    <Layout
      title={d.name}
      description={d.archived_at ? 'Chofer archivado' : 'Ficha del chofer'}
      actions={
        <>
          <ArchiveButton url={`/choferes/${d.id}/archivo`} archived={!!d.archived_at} />
          {!d.archived_at && (
            <Link className="btn primary" href={`/choferes/${d.id}/edit`}>
              Editar chofer
            </Link>
          )}
        </>
      }
    >
      <Back href="/choferes">Todos los choferes</Back>
      <div className="record-panel">
        <Details
          items={[
            { label: 'Licencia', value: d.license },
            { label: 'Categoría', value: d.category },
            { label: 'Teléfono', value: d.phone || 'Sin registrar' },
            { label: 'Ingreso', value: date(d.hired_on) },
            { label: 'Camión habitual', value: d.current_assignment?.truck.plate ?? 'Sin asignar' },
          ]}
        />
      </div>
      {!d.archived_at && (
        <Section title="Asignación habitual">
          <Editor
            key={d.current_assignment?.id ?? 'none'}
            url={`/choferes/${d.id}/asignacion`}
            cancel={`/choferes/${d.id}`}
            submit="Guardar asignación"
            initial={{
              truck_id: String(d.current_assignment?.truck.id ?? ''),
            }}
            fields={[
              {
                name: 'truck_id',
                label: 'Camión habitual',
                options: [
                  { value: '', label: 'Sin asignar' },
                  ...trucks.map((t) => ({ value: String(t.id), label: t.plate })),
                ],
                hint: 'Dejá sin seleccionar para finalizar la asignación actual.',
              },
            ]}
          />
        </Section>
      )}
      <Section
        title="Liquidación mensual"
        actions={
          <>
            <a href={`/choferes/${d.id}/liquidacion/pdf?month=${month}`} className="btn quiet">
              <Download size={17} />
              PDF
            </a>
            <a href={`/choferes/${d.id}/liquidacion/csv?month=${month}`} className="btn quiet">
              <FileSpreadsheet size={17} />
              Excel (CSV)
            </a>
          </>
        }
      >
        <div className="field">
          <label htmlFor="settlement-month">Mes a liquidar</label>
          <input
            id="settlement-month"
            type="month"
            value={month}
            onChange={(e) => e.target.value && setMonth(e.target.value)}
          />
        </div>
        <p className="report-note">
          Incluye todos los viajes del mes, cualquiera sea su estado. Total a pagar = comisión +
          viáticos.
        </p>
      </Section>
      <Section
        title="Planilla de viajes"
        actions={
          <a href={`/choferes/${d.id}/pdf?${query}`} className="btn quiet">
            <Download size={17} />
            Exportar PDF
          </a>
        }
      >
        <FilterBar filters={filters} period statusOptions={tripOptions} />
        <ReportStats totals={totals} />
        <p className="report-note">
          Kilómetros y carga entregada: solo viajes completados dentro del período filtrado.
        </p>
        <TripsTable trips={trips} />
      </Section>
      {!!d.assignments?.length && (
        <Section title="Historial de asignaciones">
          <div className="history-list">
            {d.assignments.map((a) => (
              <div key={a.id}>
                <strong>{a.truck.plate}</strong>
                <span>
                  {date(a.started_at)} — {a.ended_at ? date(a.ended_at) : 'Actual'}
                </span>
              </div>
            ))}
          </div>
        </Section>
      )}
    </Layout>
  );
}
