import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
  ArrowLeft,
  ArrowRight,
  Building2,
  Check,
  LogOut,
  Plus,
  Route,
  Search,
  Settings,
  Truck as TruckIcon,
  Users,
  UserCog,
} from 'lucide-react';
import { useState, type ReactNode, type FormEvent } from 'react';
import {
  type Shared,
  type Filters,
  type Option,
  type PageData,
  type Trip,
  type Totals,
  date,
  number,
  statuses,
} from '../types';

export function Layout({
  title,
  description,
  actions,
  children,
}: {
  title: string;
  description?: string;
  actions?: ReactNode;
  children: ReactNode;
}) {
  const { auth, flash, errors } = usePage<Shared>().props;
  const currentPath = usePage().url.split('?')[0];
  const path = currentPath.startsWith('/destinos') ? '/clientes' : currentPath;
  const nav =
    auth.user?.role === 'superadmin'
      ? [{ href: '/admin/empresas', label: 'Empresas', icon: Building2 }]
      : [
          { href: '/camiones', label: 'Flota', icon: TruckIcon },
          { href: '/choferes', label: 'Choferes', icon: Users },
          { href: '/viajes', label: 'Viajes', icon: Route },
          { href: '/clientes', label: 'Clientes', icon: Building2 },
          ...(auth.user?.role === 'admin'
            ? [
                { href: '/usuarios', label: 'Usuarios', icon: UserCog },
                { href: '/ajustes', label: 'Ajustes', icon: Settings },
              ]
            : []),
        ];
  return (
    <div className="app-shell">
      <Head title={title} />
      <a href="#contenido" className="skip-link">
        Ir al contenido
      </a>
      <aside className="sidebar">
        <Link href="/" className="brand">
          <span className="brand-icon">
            <TruckIcon size={25} />
          </span>
          <span>
            Consola
            <br />
            <b>de flota</b>
          </span>
        </Link>
        <div className="company-name">{auth.company?.name ?? 'Administración de plataforma'}</div>
        <nav aria-label="Navegación principal">
          {nav.map(({ href, label, icon: Icon }) => (
            <Link
              key={href}
              href={href}
              className={path.startsWith(href) ? 'nav-item active' : 'nav-item'}
              aria-current={path.startsWith(href) ? 'page' : undefined}
            >
              <Icon size={21} />
              <span>{label}</span>
            </Link>
          ))}
        </nav>
        <div className="sidebar-footer">
          <span className="avatar">{auth.user?.name.slice(0, 1)}</span>
          <div>
            <strong>{auth.user?.name}</strong>
            <small>
              {auth.user?.role === 'operator'
                ? 'Operador'
                : auth.user?.role === 'superadmin'
                  ? 'Superadministrador'
                  : 'Administrador'}
            </small>
          </div>
          <Link
            href="/logout"
            method="post"
            as="button"
            aria-label="Cerrar sesión"
            className="logout"
          >
            <LogOut size={19} />
          </Link>
        </div>
      </aside>
      <div className="workspace">
        <div className="topbar">
          <span>{auth.company?.name ?? 'Gestión de empresas'}</span>
          <span className="topbar-user">
            {auth.user?.name}
            <Link href="/logout" method="post" as="button" aria-label="Cerrar sesión">
              <LogOut size={18} />
            </Link>
          </span>
        </div>
        <main id="contenido" className="content">
          <div className="page-heading">
            <div>
              <h1>{title}</h1>
              {description && <p>{description}</p>}
            </div>
            <div className="actions">{actions}</div>
          </div>
          {flash.success && (
            <div role="status" className="notice success">
              <Check size={18} />
              {flash.success}
            </div>
          )}
          {flash.warning && (
            <div role="status" className="notice warning">
              {flash.warning}
            </div>
          )}
          {errors.operation && (
            <div role="alert" className="notice error">
              {errors.operation}
            </div>
          )}
          {children}
        </main>
        <footer className="page-footer">
          Consola de flota <span>Gestión de transporte</span>
        </footer>
      </div>
    </div>
  );
}
export function AddLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <Link className="btn primary" href={href}>
      <Plus size={18} />
      {children}
    </Link>
  );
}
export function Back({ href, children = 'Volver' }: { href: string; children?: ReactNode }) {
  return (
    <Link className="back" href={href}>
      <ArrowLeft size={16} />
      {children}
    </Link>
  );
}
export function Empty({
  title = 'No hay registros todavía',
  text = 'Los registros que agregues aparecerán aquí.',
  action,
}: {
  title?: string;
  text?: string;
  action?: ReactNode;
}) {
  return (
    <div className="empty">
      <Route size={32} />
      <h3>{title}</h3>
      <p>{text}</p>
      {action}
    </div>
  );
}
export function Badge({ children, tone = 'neutral' }: { children: ReactNode; tone?: string }) {
  return (
    <span className={`badge ${tone}`}>
      <i />
      {children}
    </span>
  );
}
export function Stats({
  items,
}: {
  items: { label: string; value: string | number; unit?: string }[];
}) {
  return (
    <div className="stats">
      {items.map((item) => (
        <div key={item.label}>
          <span>{item.label}</span>
          <strong>
            {typeof item.value === 'number' ? number(item.value) : item.value}
            <small>{item.unit}</small>
          </strong>
        </div>
      ))}
    </div>
  );
}
export function ReportStats({ totals }: { totals: Totals }) {
  return (
    <Stats
      items={[
        { label: 'Viajes registrados', value: totals.total },
        { label: 'En viaje', value: totals.running },
        { label: 'Km realizados', value: totals.km, unit: 'km' },
        { label: 'Carga entregada', value: totals.tons, unit: 't' },
      ]}
    />
  );
}
export function Pagination<T>({ page }: { page: PageData<T> }) {
  return (
    <div className="pagination">
      <span>{page.total ? `${page.from}–${page.to} de ${number(page.total)}` : '0 registros'}</span>
      <div>
        {page.prev_page_url ? (
          <Link className="btn quiet" href={page.prev_page_url} preserveScroll>
            <ArrowLeft size={16} />
            Anterior
          </Link>
        ) : (
          <button className="btn quiet" disabled>
            Anterior
          </button>
        )}
        <span>
          {page.current_page} / {page.last_page}
        </span>
        {page.next_page_url ? (
          <Link className="btn quiet" href={page.next_page_url} preserveScroll>
            Siguiente
            <ArrowRight size={16} />
          </Link>
        ) : (
          <button className="btn quiet" disabled>
            Siguiente
          </button>
        )}
      </div>
    </div>
  );
}
export function FilterBar({
  filters,
  period = false,
  statusOptions,
  extra = [],
  archive = false,
}: {
  filters: Filters;
  period?: boolean;
  statusOptions?: Option[];
  extra?: { name: string; label: string; options: Option[] }[];
  archive?: boolean;
}) {
  const currentPath = usePage().url.split('?')[0];
  const path = currentPath.startsWith('/destinos') ? '/clientes' : currentPath;
  const [busy, setBusy] = useState(false);
  const { errors } = usePage<Shared>().props;
  function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.currentTarget)) as Filters;
    setBusy(true);
    router.get(path, data, {
      preserveState: false,
      preserveScroll: true,
      onFinish: () => setBusy(false),
    });
  }
  return (
    <form className="filter-bar" onSubmit={submit} key={JSON.stringify(filters)}>
      <label className="search-field">
        <span className="sr-only">Buscar</span>
        <Search size={18} />
        <input name="q" defaultValue={filters.q ?? ''} placeholder="Buscar…" maxLength={100} />
      </label>
      {statusOptions && (
        <label>
          <span className="sr-only">Estado</span>
          <select name="status" defaultValue={filters.status ?? ''}>
            <option value="">Todos los estados</option>
            {statusOptions.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </label>
      )}
      {period && (
        <>
          <label>
            <span>Desde</span>
            <input type="date" name="from" defaultValue={filters.from ?? ''} />
            {errors.from && <small className="field-error">{errors.from}</small>}
          </label>
          <label>
            <span>Hasta</span>
            <input type="date" name="to" defaultValue={filters.to ?? ''} />
            {errors.to && <small className="field-error">{errors.to}</small>}
          </label>
        </>
      )}
      {extra.map((f) => (
        <label key={f.name}>
          <span className="sr-only">{f.label}</span>
          <select name={f.name} defaultValue={filters[f.name] ?? ''}>
            <option value="">{f.label}</option>
            {f.options.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </label>
      ))}
      {archive && (
        <label>
          <span className="sr-only">Archivo</span>
          <select name="archived" defaultValue={filters.archived ?? '0'}>
            <option value="0">Activos</option>
            <option value="1">Archivados</option>
          </select>
        </label>
      )}
      <button className="btn dark" disabled={busy}>
        {busy ? 'Buscando…' : 'Filtrar'}
      </button>
      <Link href={path} className="btn text">
        Limpiar
      </Link>
    </form>
  );
}
export const tripOptions = Object.entries(statuses).map(([value, label]) => ({ value, label }));
export function TripsTable({ trips }: { trips: PageData<Trip> }) {
  if (!trips.data.length)
    return (
      <Empty
        title="Sin viajes para mostrar"
        text="Probá otros filtros o registrá un nuevo viaje."
      />
    );
  return (
    <>
      <div className="table-wrap">
        <table className="data-table">
          <thead>
            <tr>
              <th>Fecha / viaje</th>
              <th>Recorrido</th>
              <th>Camión / chofer</th>
              <th>Carga</th>
              <th className="numeric">Distancia</th>
              <th>Estado</th>
              <th>
                <span className="sr-only">Detalle</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {trips.data.map((t) => (
              <tr key={t.id}>
                <td data-label="Fecha">
                  <Link className="table-link" href={`/viajes/${t.id}`}>
                    {date(t.date)}
                  </Link>
                  <small>Viaje #{t.id}</small>
                </td>
                <td data-label="Recorrido">
                  <strong>{t.origin}</strong>
                  <small>→ {t.destination}</small>
                </td>
                <td data-label="Unidad">
                  <span>{t.truck.plate}</span>
                  <small>{t.driver.name}</small>
                </td>
                <td data-label="Carga">
                  {t.cargo}
                  <small>{number(t.tons)} t</small>
                </td>
                <td data-label="Distancia" className="numeric">
                  {number(t.status === 'completado' ? (t.actual_km ?? 0) : t.planned_km)} km
                  <small>{t.status === 'completado' ? 'Realizados' : 'Previstos'}</small>
                </td>
                <td data-label="Estado">
                  <Badge tone={t.status}>{statuses[t.status]}</Badge>
                </td>
                <td>
                  <Link
                    className="icon-link"
                    href={`/viajes/${t.id}`}
                    aria-label={`Abrir viaje ${t.id}`}
                  >
                    <ArrowRight size={18} />
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pagination page={trips} />
    </>
  );
}
export function ArchiveButton({ url, archived }: { url: string; archived: boolean }) {
  const form = useForm({});
  return (
    <button
      className="btn quiet"
      disabled={form.processing}
      onClick={() => {
        if (
          window.confirm(
            archived
              ? '¿Restaurar este registro?'
              : '¿Archivar este registro? El historial se conservará.',
          )
        )
          form.post(url, { preserveScroll: true });
      }}
    >
      {form.processing ? 'Guardando…' : archived ? 'Restaurar' : 'Archivar'}
    </button>
  );
}
export type Field = {
  name: string;
  label: string;
  type?: string;
  required?: boolean;
  options?: Option[];
  hint?: string;
  min?: number | string;
  max?: number | string;
  step?: string;
  wide?: boolean;
  showWhen?: string;
};
export function Editor({
  url,
  method = 'post',
  initial,
  fields,
  submit = 'Guardar cambios',
  cancel,
  children,
  onFieldChange,
}: {
  url: string;
  method?: 'post' | 'put';
  initial: Record<string, string | boolean>;
  fields: Field[];
  submit?: string;
  cancel: string;
  children?: ReactNode;
  onFieldChange?: (name: string, value: string) => Record<string, string>;
}) {
  const form = useForm(initial);
  function save(e: FormEvent) {
    e.preventDefault();
    form.submit(method, url, { preserveScroll: true });
  }
  return (
    <form onSubmit={save} className="editor">
      <div className="form-grid">
        {fields
          .filter((f) => !f.showWhen || form.data[f.showWhen])
          .map((f) => (
            <div key={f.name} className={f.wide ? 'field wide' : 'field'}>
              {f.type === 'checkbox' ? (
                <label className="check-label">
                  <input
                    type="checkbox"
                    name={f.name}
                    checked={Boolean(form.data[f.name])}
                    onChange={(e) => form.setData(f.name, e.target.checked)}
                  />
                  {f.label}
                </label>
              ) : (
                <>
                  <label htmlFor={f.name}>
                    {f.label}
                    {f.required && <span aria-hidden="true"> *</span>}
                  </label>
                  {f.options ? (
                    <select
                      id={f.name}
                      name={f.name}
                      value={String(form.data[f.name] ?? '')}
                      onChange={(e) =>
                        form.setData({
                          ...form.data,
                          [f.name]: e.target.value,
                          ...onFieldChange?.(f.name, e.target.value),
                        })
                      }
                      required={f.required}
                      aria-invalid={!!form.errors[f.name]}
                      aria-describedby={form.errors[f.name] ? `${f.name}-error` : undefined}
                    >
                      <option value="">Seleccionar…</option>
                      {f.options.map((o) => (
                        <option key={o.value} value={o.value}>
                          {o.label}
                        </option>
                      ))}
                    </select>
                  ) : f.type === 'textarea' ? (
                    <textarea
                      id={f.name}
                      name={f.name}
                      value={String(form.data[f.name] ?? '')}
                      onChange={(e) =>
                        form.setData({
                          ...form.data,
                          [f.name]: e.target.value,
                          ...onFieldChange?.(f.name, e.target.value),
                        })
                      }
                      rows={4}
                      aria-invalid={!!form.errors[f.name]}
                    />
                  ) : (
                    <input
                      id={f.name}
                      name={f.name}
                      type={f.type ?? 'text'}
                      value={String(form.data[f.name] ?? '')}
                      onChange={(e) =>
                        form.setData({
                          ...form.data,
                          [f.name]: e.target.value,
                          ...onFieldChange?.(f.name, e.target.value),
                        })
                      }
                      required={f.required}
                      min={f.min}
                      max={f.max}
                      step={f.step}
                      aria-invalid={!!form.errors[f.name]}
                      aria-describedby={form.errors[f.name] ? `${f.name}-error` : undefined}
                    />
                  )}
                </>
              )}
              {f.hint && <small>{f.hint}</small>}
              {form.errors[f.name] && (
                <p role="alert" id={`${f.name}-error`} className="field-error">
                  {form.errors[f.name]}
                </p>
              )}
            </div>
          ))}
      </div>
      {children}
      <div className="form-footer">
        <p>* Campos obligatorios</p>
        <div className="actions">
          <Link className="btn quiet" href={cancel}>
            Cancelar
          </Link>
          <button className="btn primary" disabled={form.processing}>
            {form.processing ? 'Guardando…' : submit}
            <Check size={17} />
          </button>
        </div>
      </div>
    </form>
  );
}
export function Details({ items }: { items: { label: string; value: ReactNode }[] }) {
  return (
    <dl className="details">
      {items.map((i) => (
        <div key={i.label}>
          <dt>{i.label}</dt>
          <dd>{i.value ?? 'Sin registrar'}</dd>
        </div>
      ))}
    </dl>
  );
}
export function Section({
  title,
  actions,
  children,
}: {
  title: string;
  actions?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="section">
      <div className="section-heading">
        <h2>{title}</h2>
        {actions}
      </div>
      {children}
    </section>
  );
}
