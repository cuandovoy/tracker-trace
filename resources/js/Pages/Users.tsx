import { Link, useForm } from '@inertiajs/react';
import { Layout, AddLink, Badge, Pagination } from '../components/ui';
import type { Member, PageData } from '../types';
function Invite({ url }: { url: string }) {
  const form = useForm({});
  return (
    <button
      className="btn text"
      disabled={form.processing}
      onClick={() => form.post(url, { preserveScroll: true })}
    >
      {form.processing ? 'Enviando…' : 'Enviar acceso'}
    </button>
  );
}
export function MembersTable({
  members,
  base = '/usuarios',
}: {
  members: Member[];
  base?: string;
}) {
  return (
    <div className="table-wrap">
      <table className="data-table">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          {members.map((m) => (
            <tr key={m.id}>
              <td data-label="Usuario">
                <strong>{m.name}</strong>
                <small>{m.email}</small>
              </td>
              <td data-label="Rol">{m.role === 'admin' ? 'Administrador' : 'Operador'}</td>
              <td data-label="Estado">
                <Badge tone={m.active ? 'completado' : 'neutral'}>
                  {m.active ? 'Activo' : 'Desactivado'}
                </Badge>
              </td>
              <td>
                <div className="actions">
                  <Link className="btn quiet" href={`${base}/${m.id}/edit`}>
                    Editar
                  </Link>
                  {m.active && <Invite url={`${base}/${m.id}/invitacion`} />}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
export default function Users({ members }: { members: PageData<Member> }) {
  return (
    <Layout
      title="Usuarios"
      description="Controlá quién puede acceder a la operación de tu empresa."
      actions={<AddLink href="/usuarios/create">Nuevo usuario</AddLink>}
    >
      <MembersTable members={members.data} />
      <Pagination page={members} />
    </Layout>
  );
}
