import { Layout, Editor, Back, type Field } from '../components/ui';
import type { Truck } from '../types';
const fields: Field[] = [
  { name: 'plate', label: 'Matrícula', required: true },
  { name: 'brand', label: 'Marca', required: true },
  { name: 'model', label: 'Modelo', required: true },
  { name: 'year', label: 'Año', type: 'number', required: true, min: 1950 },
  {
    name: 'capacity_tons',
    label: 'Capacidad (toneladas)',
    type: 'number',
    step: '0.01',
    min: 0.01,
    required: true,
  },
  {
    name: 'odometer',
    label: 'Odómetro (km)',
    type: 'number',
    step: '0.1',
    min: 0,
    required: true,
    hint: 'Lectura actual. No se modifica automáticamente con los viajes.',
  },
  {
    name: 'fuel',
    label: 'Combustible',
    required: true,
    options: [
      { value: 'diesel', label: 'Diésel' },
      { value: 'gnc', label: 'GNC' },
    ],
  },
  { name: 'maintenance', label: 'En mantenimiento', type: 'checkbox' },
  { name: 'last_service', label: 'Último servicio', type: 'date' },
  { name: 'next_service', label: 'Próximo servicio', type: 'date' },
];
export default function TruckForm({ truck: t }: { truck: Truck | null }) {
  return (
    <Layout
      title={t ? 'Editar camión' : 'Nuevo camión'}
      description="La información de la unidad para organizar tus viajes."
    >
      <Back href={t ? `/camiones/${t.id}` : '/camiones'} />
      <Editor
        url={t ? `/camiones/${t.id}` : '/camiones'}
        method={t ? 'put' : 'post'}
        cancel={t ? `/camiones/${t.id}` : '/camiones'}
        fields={fields}
        initial={{
          plate: t?.plate ?? '',
          brand: t?.brand ?? '',
          model: t?.model ?? '',
          year: String(t?.year ?? new Date().getFullYear()),
          capacity_tons: t?.capacity_tons ?? '',
          odometer: t?.odometer ?? '0',
          fuel: t?.fuel ?? 'diesel',
          maintenance: t?.maintenance ?? false,
          last_service: t?.last_service ?? '',
          next_service: t?.next_service ?? '',
        }}
      />
    </Layout>
  );
}
