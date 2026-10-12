import { Head, Link } from '@inertiajs/react';
export default function Error({ status }: { status: number }) {
  const messages: Record<number, string> = {
    403: 'No tenés permiso para acceder a esta página.',
    404: 'No encontramos este registro.',
    419: 'Tu sesión venció. Volvé a ingresar.',
    429: 'Demasiados intentos. Esperá un momento y probá otra vez.',
    500: 'No pudimos completar la operación.',
    503: 'Estamos realizando tareas de mantenimiento.',
  };
  return (
    <main className="error-page">
      <Head title={`Error ${status}`} />
      <strong>{status}</strong>
      <h1>{messages[status] ?? 'No pudimos abrir esta página.'}</h1>
      <p>Podés volver al inicio e intentarlo nuevamente.</p>
      <Link className="btn primary" href={status === 419 ? '/login' : '/'}>
        Volver al inicio
      </Link>
    </main>
  );
}
