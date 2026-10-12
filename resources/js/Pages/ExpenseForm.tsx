import { Layout, Editor, Back } from '../components/ui';
import { type Trip, type TripExpense, expenseCategories } from '../types';
export default function ExpenseForm({
  trip,
  expense: e,
  today,
}: {
  trip: Trip;
  expense: TripExpense | null;
  today: string;
}) {
  return (
    <Layout
      title={e ? 'Editar gasto' : 'Registrar gasto'}
      description={`Viaje #${trip.id} · ${trip.origin} → ${trip.destination}`}
    >
      <Back href={`/viajes/${trip.id}`} />
      <Editor
        url={`/viajes/${trip.id}/gastos${e ? `/${e.id}` : ''}`}
        method={e ? 'put' : 'post'}
        cancel={`/viajes/${trip.id}`}
        fields={[
          { name: 'date', label: 'Fecha del gasto', type: 'date', required: true },
          {
            name: 'category',
            label: 'Categoría',
            required: true,
            options: Object.entries(expenseCategories).map(([value, label]) => ({ value, label })),
          },
          { name: 'description', label: 'Detalle del gasto', required: true, wide: true },
          {
            name: 'amount',
            label: 'Importe',
            type: 'number',
            min: '0.01',
            max: '9999999999.99',
            step: '0.01',
            required: true,
          },
          {
            name: 'currency',
            label: 'Moneda',
            required: true,
            options: [
              { value: 'UYU', label: 'UYU · Pesos uruguayos' },
              { value: 'USD', label: 'USD · Dólares' },
            ],
          },
        ]}
        initial={{
          date: e?.date ?? today,
          category: e?.category ?? '',
          description: e?.description ?? '',
          amount: e?.amount ?? '',
          currency: e?.currency ?? 'UYU',
        }}
      />
    </Layout>
  );
}
