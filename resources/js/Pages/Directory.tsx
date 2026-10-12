import { Link } from '@inertiajs/react';
import { Layout, AddLink, FilterBar, Pagination, Empty } from '../components/ui';
import type { DirectoryRecord, Filters, PageData } from '../types';
export default function Directory({
  kind,
  records,
  filters,
}: {
  kind: 'clientes' | 'destinos';
  records: PageData<DirectoryRecord>;
  filters: Filters;
}) {
  const customer = kind === 'clientes';
  return (
    <Layout
      title={customer ? 'Clientes' : 'Lugares frecuentes'}
      description={
        customer
          ? 'Contactos y datos comerciales para tus viajes.'
          : 'Direcciones de carga y descarga listas para reutilizar.'
      }
      actions={
        <AddLink href={`/${kind}/create`}>{customer ? 'Nuevo cliente' : 'Nuevo lugar'}</AddLink>
      }
    >
      <nav className="actions" aria-label="Directorio de viajes">
        <Link className={`btn ${customer ? 'primary' : 'quiet'}`} href="/clientes">
          Clientes
        </Link>
        <Link className={`btn ${!customer ? 'primary' : 'quiet'}`} href="/destinos">
          Lugares frecuentes
        </Link>
      </nav>
      <FilterBar filters={filters} />
      {records.data.length ? (
        <div className="people-list">
          {records.data.map((r) => (
            <Link key={r.id} href={`/${kind}/${r.id}/edit`}>
              <span className="avatar">{r.name.slice(0, 1)}</span>
              <div>
                <strong>{r.name}</strong>
                <p>
                  {r.address || 'Sin dirección'}
                  {r.contact_name ? ` · ${r.contact_name}` : ''}
                  {r.phone ? ` · ${r.phone}` : ''}
                </p>
              </div>
              <span>Editar</span>
            </Link>
          ))}
        </div>
      ) : (
        <Empty
          title={
            filters.q
              ? 'No encontramos coincidencias'
              : customer
                ? 'Tu agenda de clientes empieza acá'
                : 'Guardá tus recorridos habituales'
          }
          text={
            filters.q
              ? 'Probá con otro nombre.'
              : 'Creá el primer registro para seleccionarlo al programar un viaje.'
          }
        />
      )}
      <Pagination page={records} />
    </Layout>
  );
}
