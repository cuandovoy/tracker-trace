import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Layout, AddLink, FilterBar, Pagination, Empty, Badge } from '../components/ui';
import { type Driver, type PageData, type Filters } from '../types';
export default function Drivers({
  drivers,
  filters,
}: {
  drivers: PageData<Driver>;
  filters: Filters;
}) {
  return (
    <Layout
      title="Choferes"
      description="Tu equipo, sus asignaciones y su historial de viajes."
      actions={<AddLink href="/choferes/create">Nuevo chofer</AddLink>}
    >
      <FilterBar filters={filters} archive />
      {drivers.data.length ? (
        <>
          <div className="driver-grid">
            {drivers.data.map((d) => (
              <Link key={d.id} className="driver-card" href={`/choferes/${d.id}`}>
                <span className="avatar large">
                  {d.name
                    .split(' ')
                    .map((n) => n[0])
                    .slice(0, 2)
                    .join('')}
                </span>
                <div>
                  <h3>{d.name}</h3>
                  <p>
                    {d.category} · Lic. {d.license}
                  </p>
                  <span>{d.current_assignment?.truck.plate ?? 'Sin camión asignado'}</span>
                </div>
                <div className="driver-card-end">
                  {d.current_assignment?.principal && <Badge tone="completado">Titular</Badge>}
                  <ArrowRight size={18} />
                </div>
              </Link>
            ))}
          </div>
          <Pagination page={drivers} />
        </>
      ) : (
        <Empty
          title="No encontramos choferes"
          text="Cargá tu equipo para comenzar a planificar viajes."
          action={<AddLink href="/choferes/create">Nuevo chofer</AddLink>}
        />
      )}
    </Layout>
  );
}
