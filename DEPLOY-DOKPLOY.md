# Despliegue en Dokploy

Esta guía despliega la aplicación usando el `Dockerfile` de la raíz y MySQL como servicio separado. El servidor no necesita PHP, Composer ni Node instalados. El build instala dependencias y compila la interfaz. No usar el Compose de desarrollo en Dokploy.

## 1. Código y base de datos

Subir el proyecto a un repositorio privado y conectarlo a Dokploy. Incluir `composer.lock`, `package-lock.json`, `Dockerfile`, `docker/`, `app/`, `resources/` y el resto del código versionado. No subir `.env`, `vendor`, `node_modules`, backups ni credenciales. La imagen se compila en el servidor con su arquitectura; no hace falta subir la imagen local de tu Mac.

En un proyecto de Dokploy, crear un servicio **MySQL**, imagen `mysql:8.4`, base `truck_tracker`, usuario `truck_tracker` y contraseñas nuevas. Iniciar el servicio y copiar el **host interno** que muestra su conexión. La aplicación y MySQL deben poder comunicarse por la red interna. No usar `localhost` como `DB_HOST` ni publicar el puerto de MySQL. Mantener el volumen persistente de la base.

## 2. Aplicación y variables

Crear un servicio **Application**, conectar repositorio y rama, y configurar:

| Campo | Valor |
| --- | --- |
| Build Type | Dockerfile |
| Dockerfile Path | `Dockerfile` |
| Docker Context Path | `.` |
| Docker Build Stage | `production` |
| Réplicas iniciales | `1` |
| Comando de arranque | Dejar el predeterminado de la imagen |
| Build Arguments / Secrets | No se necesitan |

En **Environment**, pegar [docker/dokploy.env.example](docker/dokploy.env.example). Reemplazar cada `REEMPLAZAR`, el nombre de base y usuario si elegiste otros. Los valores son de runtime. Configurar `APP_URL` con el dominio HTTPS definitivo, sin barra al final.

Generar una clave nueva de producción desde tu Mac con Docker iniciado:

```sh
docker run --rm --entrypoint php php:8.4-cli -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Copiar la línea completa en `APP_KEY`. Guardar esa clave y conservarla en todos los despliegues. No usar la clave local ni generar otra al reiniciar.

Completar SMTP con un remitente autorizado por el proveedor. La plantilla usa puerto 587 y STARTTLS (`MAIL_SCHEME=smtp`); si tu proveedor exige TLS implícito en 465, usar `MAIL_SCHEME=smtps` y ese puerto. SMTP es necesario para invitaciones y recuperación de contraseña.

No montar volúmenes sobre `/var/www/html` o `bootstrap/cache`. Actualmente todos los datos persistentes, sesiones y cache están en MySQL; los PDF se generan al descargar. No se necesita Redis ni worker.

## 3. Primer despliegue, todavía sin dominio público

Presionar **Deploy** y esperar a que termine el build. La imagen arranca incluso con la base vacía; el endpoint `/up` comprueba el framework. Las pantallas necesitan las tablas del siguiente paso.

Abrir la terminal del contenedor de la aplicación (directorio `/var/www/html`) y ejecutar, en orden:

```sh
php artisan migrate --force
php artisan migrate:status
php artisan app:superadmin tu-correo@empresa.com --name="Tu nombre"
```

El último comando solicita una contraseña oculta de al menos 12 caracteres, con letras y números. Requiere terminal interactiva. Si tu panel solo ofrece comandos sin entrada interactiva, usar SSH al servidor:

```sh
docker ps --format 'table {{.ID}}\t{{.Names}}\t{{.Image}}'
docker exec -it ID_DEL_CONTENEDOR_APP php artisan app:superadmin tu-correo@empresa.com --name="Tu nombre"
```

Elegir el contenedor de esta aplicación, no MySQL. Las migraciones también pueden ejecutarse con `docker exec ID_DEL_CONTENEDOR_APP php artisan migrate --force`.

No ejecutar seeders demo en producción. La instalación empieza vacía y las cuentas locales de demostración no existen en ella. Las migraciones son un paso explícito: nunca se ejecutan al arrancar cada réplica.

## 4. Dominio y primer acceso

Apuntar el DNS del dominio al servidor. En **Domains** de la aplicación:

| Campo | Valor |
| --- | --- |
| Host | Tu dominio, sin `https://` |
| Path | `/` |
| Internal Path | `/` |
| Strip Path | Desactivado |
| Container Port | **8080** |
| HTTPS | Activado |
| Certificate | Let's Encrypt |

No publicar 8080 o 9000 mediante **Advanced → Ports**; Traefik accede por la red interna. Laravel ya reconoce las cabeceras del proxy, por eso el contenedor debe quedar detrás de Traefik.

Abrir el dominio e ingresar con el superadministrador creado. Crear una empresa y su administrador. Comprobar que llegue la invitación, establecer la contraseña y entrar como administrador empresarial. Cargar un camión, un chofer y un viaje para verificar el circuito de operación.

Verificar `/up`, login, una pantalla con datos y la descarga PDF. Un `/up` exitoso por sí solo no verifica la conexión MySQL ni SMTP.

## 5. Actualizaciones y copias de seguridad

Antes de actualizar, realizar backup MySQL y guardar la versión de código o imagen anterior. Mantener `APP_KEY`. Las migraciones de las actualizaciones deben ser compatibles con la versión anterior.

Si una versión cambia el esquema, detener temporalmente el tráfico de usuarios (por ejemplo, deshabilitar la ruta del dominio en Traefik), desplegar y ejecutar **una vez** desde el nuevo contenedor:

```sh
php artisan migrate --force --isolated
php artisan migrate:status
```

Comprobar que todas las migraciones estén aplicadas antes de habilitar el tráfico. No usar `--isolated` en una base vacía: requiere la tabla de cache que crea la primera migración. No habilitar despliegues automáticos para versiones con cambios de esquema hasta tener este paso de release coordinado. Para despliegue sin interrupción, un pipeline debe ejecutar las migraciones compatibles con la imagen nueva antes de promoverla.

**Run Command** de Dokploy se ejecuta después de que la aplicación está construida y corriendo; no equivale a una barrera previa al tráfico. No usarlo como garantía de que las migraciones finalizaron antes de publicar la versión.

Programar backups de MySQL hacia almacenamiento externo, con retención, y probar una restauración en otra base. Respaldar también `APP_KEY` y configuración SMTP. El [README](README.md#backups-y-restauración) incluye comandos manuales. Nunca usar `migrate:fresh`, `db:wipe` ni rollback automático sobre producción. Volver a una imagen anterior solo si sigue siendo compatible con el esquema.

## Diagnóstico rápido

| Síntoma | Revisar |
| --- | --- |
| 502 / gateway | Puerto de dominio 8080, estado del contenedor y logs de Nginx/PHP |
| Error de conexión MySQL | Host interno, red, credenciales y servicio MySQL activo |
| Tabla inexistente | `php artisan migrate:status` y migraciones pendientes |
| Error 419 / no mantiene sesión | Dominio HTTPS, `APP_URL`, misma `APP_KEY` y tabla `sessions` |
| No llega invitación | SMTP/remitente y logs; corregir y reenviar desde Usuarios |
| Variables anteriores | Guardar variables y redeploy; el arranque vuelve a generar las cachés |

Los logs están disponibles en Dokploy; `APP_DEBUG` debe permanecer en `false`.

## Ensayo local de la imagen de producción

Este ensayo usa otra base MySQL efímera y **no toca los datos de desarrollo**. Ejecutar desde la raíz, con `.env` local y `APP_KEY` ya configurada:

```sh
docker compose -p truck-tracker-smoke -f compose.production-check.yaml up -d --build
docker compose -p truck-tracker-smoke -f compose.production-check.yaml exec production php artisan migrate --force
docker compose -p truck-tracker-smoke -f compose.production-check.yaml exec production php artisan migrate --force --isolated
docker compose -p truck-tracker-smoke -f compose.production-check.yaml exec production php artisan app:superadmin prueba@example.test
```

Abrir `http://localhost:8089`. Solo este ensayo local usa HTTP y cookies sin `secure`. Para terminar:

```sh
docker compose -p truck-tracker-smoke -f compose.production-check.yaml down
```

La base de este ensayo vive en `tmpfs`: se pierde al detener su contenedor. No reutilizar este Compose en producción.

Referencias oficiales: [build Dockerfile](https://docs.dokploy.com/docs/core/applications/build-type), [aplicaciones y Run Command](https://docs.dokploy.com/docs/core/applications), [dominios](https://docs.dokploy.com/docs/core/domains), [bases de datos](https://docs.dokploy.com/docs/core/databases).
