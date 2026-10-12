import { Link } from '@inertiajs/react';
import { Layout, AddLink, FilterBar, Badge, Pagination, Empty } from '../components/ui';
import type { Company, PageData, Filters } from '../types';
export default function Companies({
  companies,
  filters,
}: {
  companies: PageData<Company>;
  filters: Filters;
}) {
  return (
    <Layout
      title="Empresas"
      description="Administrá los espacios y accesos de tus clientes."
      actions={<AddLink href="/admin/empresas/create">Nueva empresa</AddLink>}
    >
      <FilterBar filters={filters} />
      {companies.data.length ? (
        <>
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Empresa</th>
                  <th>Identificación fiscal</th>
                  <th>Usuarios</th>
                  <th>Estado</th>
                  <th>Gestión</th>
                </tr>
              </thead>
              <tbody>
                {companies.data.map((c) => (
                  <tr key={c.id}>
                    <td data-label="Empresa">
                      <strong>{c.name}</strong>
                      <small>{c.address || 'Sin dirección'}</small>
                    </td>
                    <td data-label="Identificación">{c.tax_id || '—'}</td>
                    <td data-label="Usuarios">{c.users_count}</td>
                    <td data-label="Estado">
                      <Badge tone={c.active ? 'completado' : 'neutral'}>
                        {c.active ? 'Activa' : 'Desactivada'}
                      </Badge>
                    </td>
                    <td>
                      <Link className="btn quiet" href={`/admin/empresas/${c.id}/edit`}>
                        Administrar
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={companies} />
        </>
      ) : (
        <Empty
          title="Creá tu primera empresa"
          text="Cada empresa tendrá su propio administrador y datos independientes."
          action={<AddLink href="/admin/empresas/create">Nueva empresa</AddLink>}
        />
      )}
    </Layout>
  );
}
