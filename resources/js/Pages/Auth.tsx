import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Truck, ShieldCheck } from 'lucide-react';
import type { Shared } from '../types';
export default function Auth({
  mode,
  token = '',
  email = '',
}: {
  mode: 'login' | 'forgot' | 'reset';
  token?: string;
  email?: string;
}) {
  const form = useForm({ email, password: '', password_confirmation: '', remember: false, token });
  const { flash } = usePage<Shared>().props;
  const title =
    mode === 'login'
      ? 'Tu operación, en un lugar.'
      : mode === 'forgot'
        ? 'Recuperá tu acceso'
        : 'Establecé tu contraseña';
  return (
    <div className="auth-page">
      <Head title={mode === 'login' ? 'Ingresar' : 'Recuperar acceso'} />
      <div className="auth-story">
        <div className="brand">
          <span className="brand-icon">
            <Truck size={28} />
          </span>
          <span>
            Consola
            <br />
            <b>de flota</b>
          </span>
        </div>
        <div>
          <h1>
            El próximo viaje
            <br />
            empieza acá.
          </h1>
          <p>
            Camiones, choferes y viajes.
            <br />
            Una visión clara de toda tu operación.
          </p>
          <div className="auth-route">
            <span>Planificar</span>
            <ArrowRight />
            <span>Operar</span>
            <ArrowRight />
            <span>Completar</span>
          </div>
        </div>
        <span className="auth-caption">
          <ShieldCheck size={18} /> Un espacio propio para cada empresa
        </span>
      </div>
      <main className="auth-main">
        <div className="auth-box">
          <h2>{title}</h2>
          <p>
            {mode === 'login'
              ? 'Ingresá con la cuenta que te asignó tu administrador.'
              : mode === 'forgot'
                ? 'Te enviaremos un enlace para volver a ingresar.'
                : 'Usá al menos 12 caracteres, incluyendo letras y números.'}
          </p>
          {flash.success && (
            <div className="notice success" role="status">
              {flash.success}
            </div>
          )}
          <form
            onSubmit={(e) => {
              e.preventDefault();
              form.post(
                mode === 'login'
                  ? '/login'
                  : mode === 'forgot'
                    ? '/forgot-password'
                    : '/reset-password',
              );
            }}
          >
            <label htmlFor="email">Correo electrónico</label>
            <input
              id="email"
              type="email"
              autoComplete="email"
              required
              value={form.data.email}
              onChange={(e) => form.setData('email', e.target.value)}
            />
            {form.errors.email && (
              <p className="field-error" role="alert">
                {form.errors.email}
              </p>
            )}
            {mode !== 'forgot' && (
              <>
                <label htmlFor="password">Contraseña</label>
                <input
                  id="password"
                  type="password"
                  required
                  autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
                  value={form.data.password}
                  onChange={(e) => form.setData('password', e.target.value)}
                />
                {form.errors.password && (
                  <p className="field-error" role="alert">
                    {form.errors.password}
                  </p>
                )}
              </>
            )}
            {mode === 'reset' && (
              <>
                <label htmlFor="confirmation">Repetir contraseña</label>
                <input
                  id="confirmation"
                  type="password"
                  autoComplete="new-password"
                  required
                  value={form.data.password_confirmation}
                  onChange={(e) => form.setData('password_confirmation', e.target.value)}
                />
              </>
            )}
            {mode === 'login' && (
              <div className="auth-options">
                <label className="check-label">
                  <input
                    type="checkbox"
                    checked={form.data.remember}
                    onChange={(e) => form.setData('remember', e.target.checked)}
                  />
                  Recordarme
                </label>
                <Link href="/forgot-password">Olvidé mi contraseña</Link>
              </div>
            )}
            <button className="btn primary" disabled={form.processing}>
              {form.processing
                ? 'Procesando…'
                : mode === 'login'
                  ? 'Ingresar'
                  : mode === 'forgot'
                    ? 'Enviar enlace'
                    : 'Guardar contraseña'}
              <ArrowRight size={18} />
            </button>
            {mode !== 'login' && (
              <Link href="/login" className="btn text">
                Volver al ingreso
              </Link>
            )}
          </form>
          <p className="auth-help">
            ¿Necesitás una cuenta? Contactá al administrador de tu empresa.
          </p>
        </div>
      </main>
    </div>
  );
}
