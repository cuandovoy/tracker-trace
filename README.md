# Consola de flota

Aplicación multiempresa para gestionar camiones, choferes, asignaciones y viajes. Laravel 13 / PHP 8.4, MySQL 8.4, React 19, TypeScript, Inertia 3 y Tailwind 4. Basada en la identidad visual del prototipo Lovable, con backend y persistencia propios.

## Abrir la instalación local

Con Docker Desktop iniciado, desde la raíz del proyecto:

```sh
docker compose up -d
```

Abrir **http://localhost:8088**. Para detenerla, `docker compose stop`. MySQL conserva los datos en el volumen `truck_mysql`; no usar `down -v` si se quieren conservar.

Si cargaste la demostración:

| Cuenta | Correo | Contraseña local |
| --- | --- | --- |
| Administrador Rodavial | admin@example.test | DemoTransporte2026! |
| Operador Rodavial | operador@example.test | DemoTransporte2026! |
| Administrador segunda empresa | norte@example.test | DemoTransporte2026! |
| Superadministrador | superadmin@example.test | DemoTransporte2026! |

Son cuentas de ejemplo, exclusivamente locales. El seeder de demostración está bloqueado en producción.

## Instalación desde cero

Requisitos: Docker Engine/Desktop con Compose y Node.js 22 con npm. PHP y Composer del equipo no son necesarios: los comandos siguientes se ejecutan en PHP 8.4 dentro de Docker.

```sh
cp .env.example .env
docker compose build app
docker compose run --rm --no-deps app composer install
npm ci
npm run build
docker compose run --rm --no-deps app php artisan key:generate
docker compose up -d mysql
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan db:seed --class=DemoSeeder
docker compose up -d app
```

La carga demo es opcional. En una instalación sin demo, crear el primer superadministrador:

```sh
docker compose exec app php artisan app:superadmin tu-correo@empresa.com --name="Tu nombre"
```

El comando solicita la contraseña sin mostrarla ni incluirla en argumentos del proceso. Desde el panel de empresas se crean los administradores de cada empresa. No hay registro público.

Compose fija la conexión interna `mysql:3306` y credenciales de desarrollo. Esto permite mantener un `.env` con otros valores para el PHP del equipo. El servicio MySQL no publica puertos al host. El servidor PHP usa `--no-reload` para conservar las variables de entorno del contenedor; si cambiás `.env`, reiniciá `app`.

Los correos locales se escriben en el log (`MAIL_MAILER=log`) y no salen a Internet. Para consultar un enlace de acceso durante desarrollo, usar `docker compose logs app` y no compartir el contenido completo de los logs.

Para cambios de interfaz, ejecutar `npm run build` y recargar. Las fuentes están alojadas en la propia aplicación, sin Google Fonts.

## Módulos y reglas

- **Superadministrador:** empresas, activación y administradores. No tiene acceso implícito a operaciones empresariales.
- **Administrador empresarial:** flota, choferes, viajes, PDF, ajustes, usuarios e históricos.
- **Operador:** flota, choferes, viajes y PDF. Sin usuarios, ajustes ni carga histórica.
- Cada usuario empresarial pertenece a una sola empresa. Todas las entidades operativas tienen `company_id`, scope obligatorio, políticas y referencias compuestas que impiden relacionar registros de diferentes empresas.
- Los usuarios nunca eligen `company_id` en formularios. Las referencias a recursos ajenos devuelven 404. Sin contexto empresarial, las consultas operativas no devuelven registros.
- El camión habitual del chofer es independiente del camión de cada viaje. Reasignar cierra la asignación anterior y crea otra; no reescribe viajes. Cada camión admite un titular y suplentes.
- Viajes: `programado → en_viaje → completado`; programados y en curso pueden anularse con motivo. Los cerrados no se editan ni reabren. Solo los programados pueden editarse.
- Para iniciar se comprueba capacidad, mantenimiento, archivo y que ni camión ni chofer estén en otro viaje activo. La planificación no reserva franjas horarias; la exclusión se comprueba al iniciar.
- Las mutaciones de disponibilidad bloquean la fila de la empresa durante la transacción. Evita carreras también entre inicio, edición y archivo. Empresas distintas no compiten por el mismo bloqueo. Si el volumen lo exige, puede evolucionar a bloqueos por recurso conservando el orden de adquisición.
- No se archivan camiones/choferes con viajes programados o en curso. Archivar finaliza asignaciones y conserva viajes e historial; restaurar no recrea asignaciones antiguas.
- Los kilómetros y toneladas realizados incluyen **solo completados**, según la fecha del viaje. El mes de flota se determina en la zona horaria empresarial. Los filtros y totales del PDF usan el mismo servicio `TripReport` que la pantalla, independientemente de la paginación.
- El odómetro es una lectura manual independiente. Completar un viaje no lo incrementa.
- Auditoría de creación/edición de viajes, cambios de estado, archivo y asignaciones, con autor. La actividad del viaje aparece en su detalle.
- Fechas de viaje y servicio son fechas de calendario; timestamps técnicos se guardan en UTC. Zona horaria por empresa, inicialmente `America/Montevideo`.

Estructura: `app/Actions` concentra transacciones de negocio; `app/Http/Requests` valida; `app/Policies` y Gates autorizan; `app/Support` contiene contexto y reportes. Pantallas en `resources/js/Pages`, componentes compartidos en `resources/js/components`. No se expone una API pública separada.

## Pruebas y calidad

```sh
docker compose run --rm tests
npm run build
npm run format:check
docker compose exec app vendor/bin/pint --format agent
```

**Aislamiento del entorno de pruebas:** se usa `truck_tracker_test` y un usuario `truck_test` con permisos solamente sobre esa base. `Tests/TestCase` bloquea el arranque si la configuración no corresponde a ese entorno, antes de ejecutar migraciones. `phpunit.xml` configura tanto variables de proceso como variables de servidor PHP para evitar que las heredadas de Docker tengan precedencia. Nunca apuntar PHPUnit a una base de producción.

El archivo `docker/mysql-init.sql` configura esa base y usuario al crear el volumen. Si el volumen local es anterior a esa configuración, aplicarlo una vez:

```sh
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot' < docker/mysql-init.sql
```

Cobertura: permisos, recursos ajenos, relaciones entre empresas, login/recuperación, último administrador, flujo de viajes, mantenimiento, archivo, asignaciones, filtros, mes, totales, PDF y salidas concurrentes con procesos separados sobre MySQL. Las capturas de revisión del navegador se guardan localmente en `output/playwright/`.

## Desplegar en Dokploy

**Guía paso a paso:** [DEPLOY-DOKPLOY.md](DEPLOY-DOKPLOY.md). Incluye primer despliegue, variables para copiar, dominio, migraciones, primer superadministrador y actualizaciones. Plantilla de producción: [docker/dokploy.env.example](docker/dokploy.env.example).

1. Crear MySQL 8.4 como servicio separado, con volumen persistente y backups. Crear una base y usuario propios para la aplicación.
2. Crear una aplicación Dokploy vinculada al repositorio, seleccionando **Dockerfile**, ruta `Dockerfile`, contexto `.`. Puerto HTTP del contenedor: **8080**.
3. Configurar un dominio HTTPS con Traefik. Mantener el contenedor accesible mediante el proxy interno, sin publicar PHP-FPM ni MySQL a Internet.
4. Configurar las variables siguientes. No copiar credenciales locales ni incorporar `.env` a la imagen.

```dotenv
APP_NAME="Consola de flota"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:CLAVE_GENERADA_UNA_SOLA_VEZ
APP_URL=https://flota.tu-dominio.com
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
DB_CONNECTION=mysql
DB_HOST=NOMBRE_INTERNO_DEL_SERVICIO_MYSQL
DB_PORT=3306
DB_DATABASE=tu_base
DB_USERNAME=tu_usuario
DB_PASSWORD=TU_SECRETO
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
LOG_LEVEL=info
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=tu-servidor-smtp
MAIL_PORT=587
MAIL_USERNAME=tu_usuario_smtp
MAIL_PASSWORD=TU_SECRETO_SMTP
MAIL_FROM_ADDRESS=flota@tu-dominio.com
MAIL_FROM_NAME="Consola de flota"
```

Generar `APP_KEY` una sola vez con `php artisan key:generate --show` en un contenedor de herramientas y guardarla en Dokploy. Conservarla entre despliegues y en el backup seguro de configuración. No se genera en el build ni al arrancar.

El Dockerfile es multietapa; instala dependencias sin paquetes de desarrollo, compila assets y ejecuta Nginx + PHP-FPM como `www-data`. No ejecuta migraciones ni seeders al arrancar. El entrypoint reconstruye cachés con las variables reales de runtime. Los PDFs son descargas temporales, no requieren almacenar archivos de usuario. No montar un volumen sobre todo el código ni sobre `bootstrap/cache`.

### Migraciones y primer acceso

Antes de habilitar una versión, ejecutar **una única tarea de migración** con la imagen nueva, conectada a la misma red y variables de MySQL:

```sh
php artisan migrate --force --isolated
```

En la primera instalación, ejecutar sin `--isolated` porque las tablas del cache todavía no existen:

```sh
php artisan migrate --force
php artisan app:superadmin tu-correo@empresa.com --name="Tu nombre"
```

Usar una consola interactiva para crear el superadministrador. El paso de release debe terminar correctamente antes de enviar tráfico; no poner migraciones en el comando de cada réplica. **Run Command de Dokploy ocurre después del arranque, no antes de publicar tráfico.** Para una primera instalación, iniciar la imagen sin dominio, ejecutar la migración en su consola y recién después habilitar el dominio. Para actualizaciones con cambios de esquema, seguir el procedimiento de la guía.

Ingresar con el superadministrador, crear la empresa y su administrador, y comprobar la entrega del correo. Si SMTP falla, la empresa y usuario se conservan y aparece una advertencia con posibilidad de reenviar. Los enlaces vencen a los 60 minutos.

### Salud, persistencia y actualizaciones

- `/up` devuelve el estado de arranque del framework. Verificar además ingreso y consulta de flota tras el despliegue, porque un contenedor sano no sustituye la prueba del circuito con MySQL.
- Logs en stdout/stderr, visibles en Dokploy. Sesiones y cache en MySQL. No hace falta Redis ni worker para esta primera versión (`QUEUE_CONNECTION=sync`).
- Escalado HTTP: usar la misma `APP_KEY`, base y configuración en todas las réplicas. No usar sesiones en archivos.
- Realizar cambios de esquema aditivos y compatibles con la versión anterior antes de activar la nueva. No ejecutar `migrate:fresh` en producción.
- Ante un despliegue fallido, volver a la imagen previa si el esquema es compatible. No ejecutar `migrate:rollback` a ciegas: algunas migraciones eliminan datos.

### Backups y restauración

Programar backups MySQL en Dokploy hacia almacenamiento fuera del servidor. Conservar de forma segura las variables de configuración y `APP_KEY`. Para un respaldo manual, desde el servicio MySQL con credenciales suministradas por su entorno:

```sh
MYSQL_PWD="$MYSQL_PASSWORD" mysqldump -u "$MYSQL_USER" --single-transaction --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE" > backup.sql
```

Probar la restauración en una base vacía de staging:

```sh
MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE" < backup.sql
```

Luego arrancar la misma versión de la app con su clave, ejecutar `migrate --force` si corresponde y comprobar login, flota, historial y PDF. En una recuperación real, detener escrituras antes de restaurar. La retención de backups se configura en Dokploy según la operación.

## Fuera de esta versión

Sin GPS, acceso de choferes, funcionamiento offline, facturación, clientes, gastos, documentos ni módulo de mantenimiento detallado. Sí incluye fechas básicas de servicio y estado de mantenimiento. La entrega incluye código e instrucciones; no configura ni publica en un servidor Dokploy real.
