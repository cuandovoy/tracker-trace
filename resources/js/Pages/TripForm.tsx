import { usePage } from '@inertiajs/react';
import { Layout, Editor, Back, type Field } from '../components/ui';
import type { Trip, Shared } from '../types';
export default function TripForm({
  trip: t,
  trucks,
  drivers,
  today,
}: {
  trip: Trip | null;
  trucks: { id: number; plate: string; capacity_tons: string }[];
  drivers: { id: number; name: string }[];
  today: string;
}) {
  const isAdmin = usePage<Shared>().props.auth.user?.role === 'admin';
  const cancel = t ? `/viajes/${t.id}` : '/viajes';
  const fields: Field[] = [
    { name: 'date', label: 'Fecha del viaje', type: 'date', required: true },
    {
      name: 'truck_id',
      label: 'Camión',
      required: true,
      options: trucks.map((t) => ({
        value: String(t.id),
        label: `${t.plate} · ${t.capacity_tons} t`,
      })),
    },
    {
      name: 'driver_id',
      label: 'Chofer',
      required: true,
      options: drivers.map((d) => ({ value: String(d.id), label: d.name })),
    },
    { name: 'cargo', label: 'Tipo de carga', required: true },
    { name: 'origin', label: 'Origen', required: true },
    { name: 'destination', label: 'Destino', required: true },
    {
      name: 'tons',
      label: 'Carga (toneladas)',
      type: 'number',
      min: 0,
      step: '0.01',
      required: true,
    },
    {
      name: 'planned_km',
      label: 'Distancia prevista (km)',
      type: 'number',
      min: 0,
      step: '0.1',
      required: true,
    },
    { name: 'notes', label: 'Observaciones', type: 'textarea', wide: true },
    ...(!t && isAdmin
      ? [
          {
            name: 'historical',
            label: 'Registrar como viaje histórico completado',
            type: 'checkbox',
            wide: true,
          },
          {
            name: 'actual_km',
            label: 'Distancia realizada (km)',
            type: 'number',
            step: '0.1',
            min: 0,
            required: true,
            showWhen: 'historical',
          },
        ]
      : []),
  ];
  return (
    <Layout
      title={t ? `Editar viaje #${t.id}` : 'Programar un viaje'}
      description="Asigná la unidad, el chofer y los datos del recorrido."
    >
      <Back href={cancel} />
      {(!trucks.length || !drivers.length) && (
        <div className="notice warning">
          Necesitás al menos un camión y un chofer activos para crear un viaje.
        </div>
      )}
      <Editor
        fields={fields}
        url={t ? `/viajes/${t.id}` : '/viajes'}
        method={t ? 'put' : 'post'}
        cancel={cancel}
        submit={t ? 'Guardar cambios' : 'Crear viaje'}
        initial={{
          date: t?.date ?? today,
          truck_id: String(t?.truck_id ?? ''),
          driver_id: String(t?.driver_id ?? ''),
          cargo: t?.cargo ?? '',
          origin: t?.origin ?? '',
          destination: t?.destination ?? '',
          tons: t?.tons ?? '',
          planned_km: t?.planned_km ?? '',
          notes: t?.notes ?? '',
          historical: false,
          actual_km: '',
        }}
      />
    </Layout>
  );
}
