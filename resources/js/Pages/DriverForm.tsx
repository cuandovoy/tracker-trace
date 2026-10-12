import { Layout, Back, Editor, type Field } from '../components/ui';
import type { Driver } from '../types';
const fields: Field[] = [
  { name: 'name', label: 'Nombre completo', required: true, wide: true },
  { name: 'license', label: 'Número de licencia', required: true },
  { name: 'category', label: 'Categoría de licencia', required: true },
  { name: 'phone', label: 'Teléfono', type: 'tel' },
  { name: 'hired_on', label: 'Fecha de ingreso', type: 'date', required: true },
];
export default function DriverForm({ driver: d }: { driver: Driver | null }) {
  const cancel = d ? `/choferes/${d.id}` : '/choferes';
  return (
    <Layout
      title={d ? 'Editar chofer' : 'Nuevo chofer'}
      description="Datos de contacto y licencia del personal de ruta."
    >
      <Back href={cancel} />
      <Editor
        url={d ? `/choferes/${d.id}` : '/choferes'}
        method={d ? 'put' : 'post'}
        cancel={cancel}
        fields={fields}
        initial={{
          name: d?.name ?? '',
          license: d?.license ?? '',
          category: d?.category ?? '',
          phone: d?.phone ?? '',
          hired_on: d?.hired_on ?? '',
        }}
      />
    </Layout>
  );
}
