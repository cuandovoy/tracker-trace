import { Layout, Editor, Section, Back, AddLink, type Field } from '../components/ui';
import { MembersTable } from './Users';
import type { Company, Member } from '../types';
export default function CompanyForm({
  company: c,
  platform,
  members = [],
}: {
  company: Company | null;
  platform: boolean;
  members?: Member[];
}) {
  const fields: Field[] = [
    { name: 'name', label: 'Nombre de la empresa', required: true, wide: true },
    { name: 'tax_id', label: 'Identificación fiscal / RUT' },
    { name: 'phone', label: 'Teléfono', type: 'tel' },
    { name: 'address', label: 'Dirección', wide: true },
    {
      name: 'timezone',
      label: 'Zona horaria',
      required: true,
      hint: 'Identificador IANA, por ejemplo America/Montevideo.',
    },
    ...(platform ? [{ name: 'active', label: 'Empresa activa', type: 'checkbox' }] : []),
    ...(!c
      ? [
          { name: 'admin_name', label: 'Nombre del administrador', required: true },
          {
            name: 'admin_email',
            label: 'Correo del administrador',
            type: 'email',
            required: true,
            hint: 'Recibirá un enlace temporal para establecer su contraseña.',
          },
        ]
      : []),
  ];
  return (
    <Layout
      title={platform ? (c ? 'Administrar empresa' : 'Nueva empresa') : 'Ajustes de la empresa'}
      description={
        platform
          ? 'Datos del espacio y sus administradores.'
          : 'Esta información aparecerá en las planillas de viajes.'
      }
    >
      {platform && <Back href="/admin/empresas" />}
      <Editor
        url={platform ? (c ? `/admin/empresas/${c.id}` : '/admin/empresas') : '/ajustes'}
        method={c ? 'put' : 'post'}
        cancel={platform ? '/admin/empresas' : '/camiones'}
        fields={fields}
        initial={{
          name: c?.name ?? '',
          tax_id: c?.tax_id ?? '',
          phone: c?.phone ?? '',
          address: c?.address ?? '',
          timezone: c?.timezone ?? 'America/Montevideo',
          active: c?.active ?? true,
          admin_name: '',
          admin_email: '',
        }}
      />
      {platform && c && (
        <Section
          title="Administradores"
          actions={
            <AddLink href={`/admin/empresas/${c.id}/usuarios/create`}>Nuevo administrador</AddLink>
          }
        >
          <MembersTable members={members} base={`/admin/empresas/${c.id}/usuarios`} />
        </Section>
      )}
    </Layout>
  );
}
