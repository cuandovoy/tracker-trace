import { Link } from '@inertiajs/react';
import { ArrowRight, Truck as TruckIcon, UserRound } from 'lucide-react';
import { Layout, AddLink, Stats, FilterBar, Badge, Pagination, Empty } from '../components/ui';
import { type Truck, type Filters, type PageData, number, truckStatus } from '../types';
export default function Fleet({
  trucks,
  filters,
  stats,
}: {
  trucks: PageData<Truck>;
  filters: Filters;
  stats: { total: number; running: number; maintenance: number; km: number };
}) {
  return (
    <Layout
      title="Tu flota, bajo control"
      description="Disponibilidad y actividad de cada unidad."
      actions={<AddLink href="/camiones/create">Nuevo camión</AddLink>}
    >
      <Stats
        items={[
          { label: 'Unidades activas', value: stats.total },
          { label: 'En ruta', value: stats.running },
          { label: 'Disponibles', value: stats.total - stats.running - stats.maintenance },
          { label: 'Km realizados este mes', value: stats.km, unit: 'km' },
        ]}
      />
      <FilterBar
        filters={filters}
        archive
        statusOptions={[
          { value: 'available', label: 'Disponibles' },
          { value: 'on_route', label: 'En ruta' },
          { value: 'maintenance', label: 'Mantenimiento' },
        ]}
      />
      <div className="list-heading">
        <h2>Camiones</h2>
        <span>{number(trucks.total)} unidades</span>
      </div>
      {trucks.data.length ? (
        <>
          <div className="truck-grid">
            {trucks.data.map((t) => {
              const principal = t.current_assignments?.find((a) => a.principal);
              return (
                <Link href={`/camiones/${t.id}`} key={t.id} className="truck-card">
                  <div className="truck-card-top">
                    <TruckIcon size={30} strokeWidth={1.5} />
                    <Badge
                      tone={
                        t.on_route
                          ? 'en_viaje'
                          : t.maintenance
                            ? 'warning'
                            : t.archived_at
                              ? 'neutral'
                              : 'completado'
                      }
                    >
                      {truckStatus(t)}
                    </Badge>
                  </div>
                  <h3>{t.plate}</h3>
                  <p>
                    {t.brand} {t.model}
                  </p>
                  <div className="truck-specs">
                    <span>{t.year}</span>
                    <span>{number(t.capacity_tons)} t</span>
                    <span>{number(t.odometer)} km</span>
                  </div>
                  <div className="truck-card-bottom">
                    <UserRound size={17} />
                    <span>{principal?.driver.name ?? 'Sin titular asignado'}</span>
                    <ArrowRight size={18} />
                  </div>
                </Link>
              );
            })}
          </div>
          <Pagination page={trucks} />
        </>
      ) : (
        <Empty
          title="No encontramos camiones"
          text="Agregá tu primera unidad o probá con otros filtros."
          action={<AddLink href="/camiones/create">Nuevo camión</AddLink>}
        />
      )}
    </Layout>
  );
}
