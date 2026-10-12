import { Link, usePage } from '@inertiajs/react';
import { Layout, Editor, Back, type Field } from '../components/ui';
import type { Trip, Shared } from '../types';
export default function TripForm({
  trip: t,
  trucks,
  drivers,
  today,
  customers,
  locations,
}: {
  trip: Trip | null;
  trucks: { id: number; plate: string; capacity_tons: string }[];
  drivers: { id: number; name: string }[];
  today: string;
  customers: { id: number; name: string }[];
  locations: { id: number; name: string; address: string; customer_id: number | null }[];
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
    {
      name: 'customer_id',
      label: 'Cliente (opcional)',
      options: customers.map((c) => ({ value: String(c.id), label: c.name })),
    },
    {
      name: 'origin_location_id',
      label: 'Lugar de carga guardado (opcional)',
      options: locations.map((l) => ({ value: String(l.id), label: `${l.name} · ${l.address}` })),
      hint: 'Al seleccionar se completa el origen.',
    },
    {
      name: 'destination_location_id',
      label: 'Lugar de descarga guardado (opcional)',
      options: locations.map((l) => ({ value: String(l.id), label: `${l.name} · ${l.address}` })),
      hint: 'Al seleccionar se completa el destino.',
    },
    { name: 'origin', label: 'Origen', required: true },
    { name: 'destination', label: 'Destino', required: true },
    { name: 'cargo', label: 'Tipo de carga', required: true },
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
      <p className="report-note">
        <Link href="/clientes">Administrar clientes</Link> ·{' '}
        <Link href="/destinos">Administrar lugares frecuentes</Link>
      </p>
      <Editor
        onFieldChange={(name, value) => {
          const field =
            name === 'origin_location_id'
              ? 'origin'
              : name === 'destination_location_id'
                ? 'destination'
                : null;
          const location = locations.find((l) => String(l.id) === value);
          return field && location ? { [field]: location.address } : {};
        }}
        fields={fields}
        url={t ? `/viajes/${t.id}` : '/viajes'}
        method={t ? 'put' : 'post'}
        cancel={cancel}
        submit={t ? 'Guardar cambios' : 'Crear viaje'}
        initial={{
          customer_id: String(t?.customer_id ?? ''),
          origin_location_id: String(t?.origin_location_id ?? ''),
          destination_location_id: String(t?.destination_location_id ?? ''),
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
