# MVP de Agendamiento — plan de pruebas

## 1. Objetivo

Demostrar que cada incremento es seguro, compatible con historia y aislado de producción. Ninguna prueba utilizará credenciales o servicios reales de RENIEC, correo, SMS, SUNAT o llamador.

## 2. Entornos

### 2.1 SQLite aislado

Adecuado para:

- arranque, rutas, validación y policies;
- CRUD no concurrente;
- paciente existente/nuevo y RENIEC fake;
- reglas de autorización;
- serialización de respuestas;
- eventos/auditoría;
- compatibilidad básica de modelos heredados.

### 2.2 MySQL aislado obligatorio

Necesario para:

- `SELECT ... FOR UPDATE` y bloqueos reales;
- dos conexiones/transacciones concurrentes;
- índices/constraints con semántica productiva;
- deadlocks/reintentos;
- precisión temporal y consultas de solapamiento;
- migrations sobre un esquema equivalente al productivo;
- backfill y rollback de `appointments` con volumen representativo.

Debe ser una base desechable creada específicamente para tests. La suite abortará si el entorno/base no coincide con allowlist de testing.

## 3. Controles de seguridad de la suite

1. `APP_ENV=testing` obligatorio.
2. Nombre/base MySQL con patrón de test explícito.
3. Guard que rechace hosts/nombres prohibidos o configuración incompleta.
4. `Http::fake()`, `Mail::fake()`, `Queue::fake()` y `Notification::fake()` por defecto.
5. Filesystem fake para evidencia de pago.
6. Reloj congelado en escenarios de vencimiento.
7. No usar dumps de producción ni PII real en fixtures.
8. Los tests de contrato con llamador usan servidor fake o fixtures sanitizadas.

## 4. Pirámide de pruebas

| Nivel | Cobertura |
|---|---|
| Unitarias | reglas de intervalos, estados, porcentajes, vigencias, mapeos y políticas puras |
| Integración | casos de uso + BD, transacciones, locks, repositorios, storage y auditoría |
| Feature HTTP | middleware, Form Requests, permisos, contratos JSON, idempotencia |
| Contrato | RENIEC y futuro llamador, sin red real |
| Browser/UAT | rapidez, contexto visual, accesibilidad, FullCalendar, atajos y errores recuperables |
| Migración | clean install, upgrade desde fixture heredada, backfill, rollback y compatibilidad |

## 5. Matriz por incremento

### MVP-0 — seguridad y flags

- flag apagado devuelve flujo heredado o 404/403 definido;
- usuario con `appointment.mvp.access` entra; sin capacidad no entra;
- lectura no concede creación/modificación;
- ADMINISTRADOR no recibe escritura operativa automáticamente;
- CAJA/FACTURACION no modifican agenda salvo capacidad expresa;
- roles inexistentes, incluido COMERCIAL, no se crean implícitamente al consultar;
- integraciones y logs permanecen fakes/sin PII.

### MVP-1 — datos

- migrations limpias en SQLite cuando sean compatibles;
- migrations y rollback en MySQL aislado;
- upgrade desde fixture con citas heredadas, incluidas filas sin metadatos nuevos;
- `numero_cita` sigue unique;
- enum productivo no cambia;
- nuevas FK nullable no invalidan historia;
- backfill de sede es idempotente;
- no se inventa responsable/tipo histórico;
- versión anterior de código puede leer citas tras migration aditiva.

### MVP-2 — disponibilidad/concurrencia

- horario recurrente y horario fechado;
- excepción/bloque total y parcial;
- duración exacta al borde del bloque;
- cruce con cita activa;
- cita cancelada no ocupa según regla;
- hold activo ocupa; hold vencido no ocupa;
- filtros médico/especialidad y comparación;
- hora manual dentro/fuera de horario;
- dos usuarios intentan el mismo intervalo: exactamente uno obtiene hold regular;
- intervalos distintos del mismo profesional/día se procesan correctamente;
- profesionales distintos no se bloquean entre sí;
- deadlock/reintento produce resultado controlado.

### MVP-3 — paciente/RENIEC

- paciente local existente evita llamada externa;
- DNI nuevo con RENIEC fake crea identidad mínima;
- timeout, 4xx, 5xx, JSON incompleto y `success=false` habilitan fallback manual;
- doble solicitud concurrente del mismo DNI crea una sola identidad;
- paciente pendiente se completa sobre la misma fila;
- discrepancia de datos no sobrescribe silenciosamente información verificada;
- DNI completo/respuesta RENIEC no aparecen en logs ni consola;
- validación de formato y rate limit.

### MVP-4 — cita rápida/responsable

- creador autenticado se conserva en `user_id`/autoría definida;
- responsable inicial es el usuario autenticado;
- último modificador cambia sin reemplazar creador ni responsable;
- reasignación autorizada registra anterior, nuevo, actor y fecha;
- reasignación no autorizada devuelve 403 y no escribe;
- doble clic con igual request key devuelve una sola cita;
- reprogramación válida actualiza intervalo y auditoría;
- reprogramación con conflicto no deja cambios parciales;
- cita heredada sin detalle se visualiza y puede editarse por flujo compatible.

### MVP-5 — hold, pago y COSTO 0

- hold usa 15 minutos por defecto y configuración alternativa para nuevos registros;
- expiración cambia una sola vez a `EXPIRED` y libera disponibilidad;
- job repetido es idempotente;
- extensión conserva vencimiento anterior/nuevo, actor y motivo;
- usuario sin capacidad no extiende;
- pago verificado 50 % confirma; 49.99 % no confirma cuando el umbral es 50 %;
- carrera pago vs expiración termina en un único estado consistente;
- evidencia duplicada por doble clic no duplica pago;
- archivo privado, tipo/tamaño/checksum válidos;
- COSTO 0 solicitado/aprobado/rechazado;
- usuario con permiso pero fuera del maestro no aprueba;
- aprobador inactivo/fuera de vigencia no aprueba;
- excepción al adelanto no se confunde con COSTO 0;
- proyección financiera legacy cuadra y no crea voucher/pago doble.

### MVP-6 — adicional/sobreagenda

- adicional con agenda no llena se rechaza;
- adicional dentro del bloque y con capacidad se registra;
- fuera de horario o extendiendo jornada se rechaza;
- constancia de información/espera/no garantía es obligatoria;
- tiempo de espera queda como snapshot configurable;
- límite por profesional/bloque/día cuando se defina;
- sobreagenda requiere capacidad y autorización;
- selección no disponible nunca se guarda por bypass frontend;
- adicional, sobreagenda y regular quedan distinguibles;
- `additional_rate_id` no determina tipo de cita;
- resultado final de adicional se registra según catálogo aprobado.

### MVP-7 — UX

- Día/Semana/Mes conservan filtros y fecha;
- comparación de profesionales usa el mismo motor de disponibilidad;
- color siempre se acompaña de texto/icono;
- teclado permite flujo común sin enviar dos veces;
- panel rápido conserva contexto al buscar paciente o fallar RENIEC;
- detalle avanzado no oculta autorizaciones/historial;
- datos sensibles no aparecen en vista pública ni atributos HTML innecesarios;
- feed de calendario no expone más campos que los requeridos;
- rendimiento con volumen representativo.

### MVP-8 — llamador

- contrato versionado con fixture del commit productivo auditado;
- publicación duplicada se procesa una vez;
- mensajes fuera de orden se rechazan/reconcilian;
- llamador caído no revierte una cita ERP confirmada ni pierde evento;
- reintento con backoff y dead-letter/alerta;
- correlación ERP/temporal única;
- estados desconocidos no corrompen la cita;
- no se comparten credenciales ni tablas;
- payload mínimo sin información financiera innecesaria.

## 6. Escenarios MySQL concurrentes obligatorios

| ID | Transacción A | Transacción B | Resultado esperado |
|---|---|---|---|
| CON-01 | crea hold regular 10:00–10:30 | crea mismo hold | una gana; otra recibe conflicto |
| CON-02 | confirma hold | expira mismo hold | solo una transición terminal válida |
| CON-03 | reprograma cita a 11:00 | crea hold 11:00 | lock serializa y evita solapamiento regular |
| CON-04 | crea sobreagenda autorizada | crea cita regular | ambas solo si la regla autoriza la sobreagenda; nunca por carrera |
| CON-05 | crea adicional | cierra bloque/añade excepción | resultado consistente según orden del lock |
| CON-06 | doble click misma request key | mismo payload | misma respuesta/registro, no duplicado |
| CON-07 | mismo DNI nuevo | mismo DNI nuevo | un paciente, segunda operación reutiliza/conflicto controlado |
| CON-08 | verifica pago | verifica misma evidencia | un único efecto financiero/auditoría |

La prueba debe abrir conexiones distintas y coordinar barreras; una única transacción secuencial no demuestra concurrencia.

## 7. Fixtures

Datos sintéticos mínimos:

- roles productivos ADMISION, ADMINISTRADOR, RECEPCION, CAJA y FACTURACION;
- usuarios con capacidades directas y por rol;
- sede inicial;
- dos profesionales, dos especialidades y servicios con duraciones distintas;
- horarios recurrentes, fechado, bloqueo y jornada partida;
- paciente existente, pendiente y nuevo;
- citas heredadas sin estructuras nuevas;
- hold activo/vencido/convertido;
- pago parcial/verificado/rechazado;
- autorizador activo/inactivo/fuera de vigencia.

No usar nombres, DNI, operaciones ni capturas reales.

## 8. Pruebas de compatibilidad heredada

1. Abrir listado/calendario con citas históricas sin `site_id`, detalle ni responsable.
2. Mantener lectura de importes/estado financiero legacy.
3. Editar solo campos permitidos sin recalcular precio histórico.
4. No escribir horas del llamador en ERP.
5. No interpretar `additional_rate_id` o `cita_doble` como adicional.
6. Preservar `numero_cita` y relaciones existentes.
7. Ejecutar suite Fases 0/1 completa en cada incremento.

## 9. Pruebas de autorización

Cada endpoint/caso de uso tendrá al menos:

- visitante → 302/401 según superficie;
- autenticado sin capacidad → 403;
- rol con lectura → lectura permitida, escritura rechazada;
- capacidad positiva → operación permitida dentro de reglas;
- manipulación de ids/actor/responsable en payload → ignorada o rechazada;
- autorización sensible decidida en servidor, no por botón oculto.

## 10. UAT y usabilidad

Guiones medibles, sin fijar todavía un número rígido de clics:

1. localizar disponibilidad de un médico;
2. comparar dos profesionales;
3. registrar paciente existente;
4. registrar paciente nuevo con RENIEC y con fallback;
5. crear cita común sin perder calendario;
6. extender hold;
7. verificar adelanto y confirmar;
8. reasignar responsable;
9. reprogramar;
10. registrar adicional/sobreagenda con explicación clara.

Medir tiempo, errores, retrocesos y pérdida de contexto. La aceptación final requiere usuarios reales del piloto cuando el grupo se defina.

## 11. Criterios de salida

- suite completa verde;
- tests MySQL concurrentes verdes y repetibles;
- ninguna llamada externa real;
- `git diff --check` limpio;
- cobertura explícita de todos los escenarios obligatorios;
- cero regresiones Fases 0/1;
- rollback probado en copia aislada;
- evidencias sin secretos ni PII;
- aceptación del incremento y de sus decisiones empresariales aplicables.
