# Ideas para evolucionar Truck Tracker

Lista de posibilidades para evaluar. No representa un compromiso de implementación ni un cronograma. Las prioridades son sugeridas y conviene ajustarlas con el uso real de la empresa.

## Solicitudes en desarrollo

- [ ] **Datos de prueba desde Configuración.** Cargar ejemplos en la empresa actual, con confirmación y protección contra duplicados.
- [ ] **Exportar viajes a Excel.** Descargar los resultados filtrados desde Viajes y los historiales de camiones y choferes.

## Prioridad alta · Operación diaria

- [x] **Clientes y destinos frecuentes.** Guardar contactos, direcciones y lugares de carga y descarga para evitar escribirlos en cada viaje.
- [ ] **Planificación semanal.** Ver viajes, camiones y choferes en un calendario, con horarios previstos y advertencias de superposición.
- [ ] **Alertas de vencimientos.** Avisar sobre licencias, seguros, inspecciones y servicios próximos a vencer.
- [ ] **Mantenimiento básico.** Registrar tareas, taller, costos, odómetro y próxima intervención por fecha o kilometraje.
- [x] **Gastos por viaje.** Cargar combustible, peajes, viáticos y otros gastos para conocer el costo de cada operación.
- [ ] **Comprobantes y documentos.** Adjuntar remitos, fotos y comprobantes al viaje; documentos técnicos al camión y al chofer.
- [ ] **Duplicar un viaje.** Crear una nueva planificación a partir de un recorrido habitual, revisando fecha, camión y chofer.
- [ ] **Importación desde Excel.** Cargar flota, choferes o viajes con una vista previa, validación por fila y detección de duplicados.

## Prioridad media · Control y análisis

- [ ] **Panel de indicadores.** Comparar viajes, kilómetros y toneladas por período, camión y chofer.
- [ ] **Rentabilidad por viaje.** Incorporar tarifa e ingresos para contrastarlos con los gastos registrados.
- [ ] **Consumo de combustible.** Registrar cargas y calcular consumo por unidad y desvíos respecto a su historial.
- [ ] **Disponibilidad de flota.** Mostrar unidades disponibles, asignadas, en ruta o en mantenimiento para cada fecha.
- [ ] **Reporte de puntualidad.** Comparar horarios previstos y reales de salida, llegada y entrega.
- [ ] **Filtros guardados.** Recuperar consultas frecuentes sin volver a configurar todas las opciones.
- [ ] **Reportes programados.** Enviar resúmenes diarios, semanales o mensuales a destinatarios definidos por la empresa.
- [ ] **Auditoría consultable.** Buscar quién modificó una asignación, un viaje o sus estados y cuándo lo hizo.
- [ ] **Incidentes en ruta.** Registrar demoras, averías, rechazos de carga y su resolución.
- [ ] **Carga y descarga con varias paradas.** Representar recorridos con entregas parciales y cantidades por destino.

## Prioridad media · Usuarios y experiencia

- [ ] **Centro de notificaciones.** Reunir vencimientos, cambios de viajes e incidencias con estado leído/pendiente.
- [ ] **Invitaciones con seguimiento.** Mostrar si el acceso está pendiente, venció o ya fue utilizado y facilitar su reenvío.
- [ ] **Ayuda de configuración de correo.** Permitir al administrador comprobar el envío y ver un resultado comprensible.
- [ ] **Inicio guiado para una empresa nueva.** Acompañar la carga del primer camión, chofer y viaje.
- [ ] **Permisos más específicos.** Separar consulta, operación y administración cuando el tamaño de la empresa lo requiera.
- [ ] **Autenticación en dos pasos.** Agregar protección para administradores y superadministradores.
- [ ] **Personalización empresarial.** Incorporar logo y datos de contacto en reportes y comunicaciones.
- [ ] **Mejoras de accesibilidad.** Revisar teclado, contraste, foco visible y uso en pantallas táctiles con usuarios reales.

## A explorar · Ampliación del producto

- [ ] **Acceso para choferes.** Consultar viajes asignados, actualizar estados y cargar comprobantes desde el celular.
- [ ] **Notificaciones por WhatsApp.** Evaluar proveedores, consentimiento, costos y qué eventos justifican un mensaje.
- [ ] **Integración GPS.** Conectar proveedores existentes para consultar ubicación y recorrido de las unidades.
- [ ] **Modo sin conexión.** Permitir registrar novedades en ruta y sincronizarlas cuando vuelva la conectividad.
- [ ] **Portal de clientes.** Consultar entregas y descargar comprobantes con acceso limitado a sus propias cargas.
- [ ] **Facturación e integraciones contables.** Evaluar necesidades locales antes de definir el alcance.
- [ ] **Remolques y equipos.** Administrar semirremolques, capacidades y combinaciones con cada camión.
- [ ] **Sucursales y depósitos.** Organizar recursos y operaciones dentro de una misma empresa.
- [ ] **API e integraciones.** Intercambiar viajes y estados con sistemas de clientes o herramientas internas.

## Base técnica y operación del servicio

- [ ] **Monitoreo y alertas.** Detectar errores de aplicación, fallas de correo y problemas de conexión con MySQL.
- [ ] **Restauraciones verificadas.** Probar periódicamente que los backups recuperen usuarios, viajes y documentos.
- [ ] **Entorno de staging.** Validar cambios con una configuración semejante a producción antes de publicarlos.
- [ ] **Despliegue automatizado.** Coordinar pruebas, compilación y migraciones antes de habilitar una nueva versión.
- [ ] **Pruebas de carga.** Medir búsquedas, reportes y operaciones concurrentes con volúmenes representativos.
- [ ] **Exportaciones grandes en segundo plano.** Generar archivos mediante una cola y avisar cuando estén listos.
- [ ] **Gestión de datos de demostración.** Evaluar un entorno de práctica separado y una limpieza identificable de ejemplos, preservando datos reales.
- [ ] **Política de conservación.** Definir archivo, retención y eliminación de datos y documentos por empresa.

## Cómo elegir la próxima tarea

Antes de pasar una idea a desarrollo, definir quién la necesita, qué problema resuelve, cómo se usa hoy, qué datos requiere y cómo comprobar que quedó bien. Priorizar lo que reduzca trabajo repetido o errores operativos; revisar dependencias, como gastos e ingresos antes de calcular rentabilidad.
