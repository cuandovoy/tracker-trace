export type Company = {
  id: number;
  name: string;
  tax_id: string | null;
  address: string | null;
  phone: string | null;
  timezone: string;
  active: boolean;
  users_count?: number;
};
export type Member = {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'operator' | 'superadmin';
  active: boolean;
};
export type Shared = {
  auth: { user: Member | null; company: Company | null };
  flash: { success?: string; warning?: string };
  errors: Record<string, string>;
  [key: string]: unknown;
};
export type Truck = {
  id: number;
  plate: string;
  brand: string;
  model: string;
  year: number;
  capacity_tons: string;
  odometer: string;
  fuel: string;
  maintenance: boolean;
  last_service: string | null;
  next_service: string | null;
  archived_at: string | null;
  on_route?: boolean;
  current_assignments?: Assignment[];
};
export type Driver = {
  id: number;
  name: string;
  license: string;
  category: string;
  phone: string | null;
  hired_on: string;
  archived_at: string | null;
  current_assignment?: Assignment | null;
  assignments?: Assignment[];
};
export type Assignment = {
  id: number;
  principal: boolean;
  started_at: string;
  ended_at: string | null;
  truck: Truck;
  driver: Driver;
};
export type TripStatus = 'programado' | 'en_viaje' | 'completado' | 'anulado';
export type Trip = {
  id: number;
  truck_id: number;
  driver_id: number;
  truck: Truck;
  driver: Driver;
  date: string;
  origin: string;
  destination: string;
  cargo: string;
  tons: string;
  planned_km: string;
  actual_km: string | null;
  notes: string | null;
  status: TripStatus;
  historical: boolean;
  started_at: string | null;
  completed_at: string | null;
  cancellation_reason: string | null;
};
export type PageData<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  from: number | null;
  to: number | null;
  prev_page_url: string | null;
  next_page_url: string | null;
};
export type Filters = Record<string, string>;
export type Totals = {
  total: number;
  completed: number;
  running: number;
  km: number;
  tons: number;
};
export type Option = { value: string; label: string };
export const statuses: Record<TripStatus, string> = {
  programado: 'Programado',
  en_viaje: 'En viaje',
  completado: 'Completado',
  anulado: 'Anulado',
};
export const number = (value: number | string) =>
  Number(value).toLocaleString('es-UY', { maximumFractionDigits: 2 });
export const date = (value: string | null | undefined) =>
  value
    ? new Intl.DateTimeFormat('es-UY', { day: '2-digit', month: 'short', year: 'numeric' }).format(
        new Date(value.slice(0, 10) + 'T12:00:00'),
      )
    : 'Sin registrar';
export const truckStatus = (t: Truck) =>
  t.archived_at
    ? 'Archivado'
    : t.on_route
      ? 'En ruta'
      : t.maintenance
        ? 'Mantenimiento'
        : 'Disponible';
