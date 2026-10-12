import { Layout, Editor, Back, type Field } from '../components/ui';
import type { DirectoryRecord } from '../types';
export default function DirectoryForm({
  kind,
  record: r,
  customers,
}: {
  kind: 'clientes' | 'destinos';
  record: DirectoryRecord | null;
  customers: { id: number; name: string }[];
}) {
  const customer = kind === 'clientes';
  const fields: Field[] = [
    {
      name: 'name',
      label: customer ? 'Nombre / razón social' : 'Nombre del lugar',
      required: true,
      wide: true,
    },
    ...(customer
      ? [
          { name: 'tax_id', label: 'Identificación fiscal / RUT' },
          { name: 'email', label: 'Correo', type: 'email' },
        ]
      : [
          {
            name: 'customer_id',
            label: 'Cliente asociado (opcional)',
            options: customers.map((c) => ({ value: String(c.id), label: c.name })),
          },
        ]),
    { name: 'address', label: 'Dirección', required: !customer, wide: true },
    { name: 'contact_name', label: 'Persona de contacto' },
    { name: 'phone', label: 'Teléfono', type: 'tel' },
    { name: 'notes', label: 'Indicaciones / observaciones', type: 'textarea', wide: true },
  ];
  return (
    <Layout
      title={`${r ? 'Editar' : 'Nuevo'} ${customer ? 'cliente' : 'lugar'}`}
      description="Los cambios se aplican a futuras selecciones. Los viajes existentes conservan sus datos."
    >
      <Back href={`/${kind}`} />
      <Editor
        url={`/${kind}${r ? `/${r.id}` : ''}`}
        method={r ? 'put' : 'post'}
        cancel={`/${kind}`}
        fields={fields}
        initial={{
          name: r?.name ?? '',
          tax_id: r?.tax_id ?? '',
          email: r?.email ?? '',
          customer_id: String(r?.customer_id ?? ''),
          address: r?.address ?? '',
          contact_name: r?.contact_name ?? '',
          phone: r?.phone ?? '',
          notes: r?.notes ?? '',
        }}
      />
    </Layout>
  );
}
