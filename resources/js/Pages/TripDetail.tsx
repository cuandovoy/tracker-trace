import { Link, useForm } from '@inertiajs/react';
import { ArrowRight, Check, Play } from 'lucide-react';
import { Layout, Back, Badge, Details, Section } from '../components/ui';
import { type Trip, date, number, statuses } from '../types';
export default function TripDetail({
  trip: t,
  events,
}: {
  trip: Trip;
  events: {
    id: number;
    action: string;
    created_at: string;
    user: { name: string };
    data: { from?: string; to?: string };
  }[];
}) {
  const form = useForm({ status: '', actual_km: '', cancellation_reason: '' });
  function transition(status: string) {
    if (
      !window.confirm(
        status === 'en_viaje'
          ? '¿Iniciar este viaje?'
          : status === 'completado'
            ? '¿Completar y cerrar este viaje?'
            : '¿Anular este viaje? Esta acción no se puede deshacer.',
      )
    )
      return;
    form.transform((data) => ({ ...data, status }));
    form.post(`/viajes/${t.id}/estado`, { preserveScroll: true });
  }
  return (
    <Layout
      title={`Viaje #${t.id}`}
      description={date(t.date)}
      actions={
        t.status === 'programado' ? (
          <Link className="btn quiet" href={`/viajes/${t.id}/edit`}>
            Editar viaje
          </Link>
        ) : undefined
      }
    >
      <Back href="/viajes">Todos los viajes</Back>
      <div className="record-panel">
        <div className="route-title">
          <h2>
            {t.origin}
            <ArrowRight size={25} />
            {t.destination}
          </h2>
          <Badge tone={t.status}>{statuses[t.status]}</Badge>
        </div>
        {t.historical && (
          <p className="report-note">Registro histórico cargado por un administrador.</p>
        )}
        <Details
          items={[
            {
              label: 'Camión',
              value: <Link href={`/camiones/${t.truck_id}`}>{t.truck.plate}</Link>,
            },
            {
              label: 'Chofer',
              value: <Link href={`/choferes/${t.driver_id}`}>{t.driver.name}</Link>,
            },
            { label: 'Carga', value: `${t.cargo} · ${number(t.tons)} t` },
            { label: 'Distancia prevista', value: `${number(t.planned_km)} km` },
            {
              label: 'Distancia realizada',
              value: t.actual_km !== null ? `${number(t.actual_km)} km` : 'Pendiente',
            },
            { label: 'Observaciones', value: t.notes || 'Sin observaciones' },
          ]}
        />
        {t.cancellation_reason && (
          <div className="notice warning">Motivo de anulación: {t.cancellation_reason}</div>
        )}
      </div>
      {['programado', 'en_viaje'].includes(t.status) && (
        <Section title="Operación del viaje">
          <div className="operation-panel">
            {t.status === 'programado' ? (
              <div>
                <h3>Todo listo para salir</h3>
                <p>Al iniciar, verificamos que el camión y el chofer estén disponibles.</p>
                <button
                  className="btn primary"
                  disabled={form.processing}
                  onClick={() => transition('en_viaje')}
                >
                  <Play size={17} />
                  Iniciar viaje
                </button>
              </div>
            ) : (
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  transition('completado');
                }}
              >
                <h3>Registrar la llegada</h3>
                <label htmlFor="actual_km">Distancia realizada (km)</label>
                <input
                  id="actual_km"
                  type="number"
                  min="0"
                  step="0.1"
                  required
                  value={form.data.actual_km}
                  onChange={(e) => form.setData('actual_km', e.target.value)}
                />
                {form.errors.actual_km && <p className="field-error">{form.errors.actual_km}</p>}
                <button className="btn primary" disabled={form.processing}>
                  <Check size={17} />
                  Completar viaje
                </button>
              </form>
            )}
            <details className="cancel-trip">
              <summary>Anular viaje</summary>
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  transition('anulado');
                }}
              >
                <label htmlFor="cancellation_reason">Motivo de anulación</label>
                <textarea
                  id="cancellation_reason"
                  required
                  minLength={3}
                  value={form.data.cancellation_reason}
                  onChange={(e) => form.setData('cancellation_reason', e.target.value)}
                />
                {form.errors.cancellation_reason && (
                  <p className="field-error">{form.errors.cancellation_reason}</p>
                )}
                <button className="btn danger" disabled={form.processing}>
                  Confirmar anulación
                </button>
              </form>
            </details>
          </div>
        </Section>
      )}
      <Section title="Actividad">
        <ol className="activity">
          {events.map((e) => (
            <li key={e.id}>
              <span className="activity-dot" />
              <div>
                <strong>
                  {e.action === 'viaje_creado'
                    ? 'Viaje creado'
                    : e.action === 'viaje_editado'
                      ? 'Datos actualizados'
                      : e.action === 'estado_viaje'
                        ? `Estado: ${statuses[e.data.to as keyof typeof statuses] ?? e.data.to}`
                        : e.action}
                </strong>
                <p>
                  {e.user.name} · {date(e.created_at)}
                </p>
              </div>
            </li>
          ))}
        </ol>
      </Section>
    </Layout>
  );
}
