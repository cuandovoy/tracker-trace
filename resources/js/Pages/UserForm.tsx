import { Layout, Back, Editor, type Field } from '../components/ui';
import type { Company, Member } from '../types';
export default function UserForm({
  member: m,
  company,
  platform,
}: {
  member: Member | null;
  company: Company;
  platform: boolean;
}) {
  const base = platform ? `/admin/empresas/${company.id}/usuarios` : '/usuarios';
  const cancel = platform ? `/admin/empresas/${company.id}/edit` : '/usuarios';
  const fields: Field[] = [
    { name: 'name', label: 'Nombre completo', required: true },
    {
      name: 'email',
      label: 'Correo electrónico',
      type: 'email',
      required: true,
      hint: 'Si cambiás el correo, el usuario deberá establecer una nueva contraseña.',
    },
    {
      name: 'role',
      label: 'Rol',
      required: true,
      options: [
        { value: 'admin', label: 'Administrador' },
        ...(!platform ? [{ value: 'operator', label: 'Operador' }] : []),
      ],
    },
    { name: 'active', label: 'Usuario activo', type: 'checkbox' },
  ];
  return (
    <Layout title={m ? 'Editar usuario' : 'Nuevo usuario'} description={company.name}>
      <Back href={cancel} />
      <Editor
        url={m ? `${base}/${m.id}` : base}
        method={m ? 'put' : 'post'}
        cancel={cancel}
        fields={fields}
        initial={{
          name: m?.name ?? '',
          email: m?.email ?? '',
          role: m?.role ?? (platform ? 'admin' : 'operator'),
          active: m?.active ?? true,
        }}
      />
    </Layout>
  );
}
