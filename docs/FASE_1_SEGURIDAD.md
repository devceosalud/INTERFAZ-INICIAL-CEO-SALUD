# Fase 1 — Contención de seguridad

## Base y alcance

- Branch: `stabilization/phase-1-security`.
- Base: commit local de Fase 0 `9519043` (`test: establish isolated ERP baseline environment`).
- Alcance: autenticación, autorización, exposición de PII, efectos externos, sesiones, logging y secretos presentes en el árbol actual.
- Fuera de alcance: reglas financieras, concurrencia de correlativos/caja, slots de agenda, refactor de controladores/Livewire, cambios de esquema y producción.

## Inventario previo a cambios

### Carga de rutas

`RouteServiceProvider` carga `web.php`, `administrador.php`, `recepcion.php` y `admision.php`; a su vez `web.php` vuelve a incluir los tres últimos. Por tanto existen declaraciones duplicadas en el router. Se documenta como deuda técnica y no se reorganiza en esta fase para evitar un cambio arquitectónico lateral.

### Rutas web públicas efectivas

| Rutas | Estado original | Exposición |
|---|---|---|
| `GET /`, `POST /admin/SingIn` | Públicas, sin middleware `guest` ni throttle de login | Login y sesión |
| `POST /admin/logout` | Pública a nivel de ruta | Estado de sesión |
| `GET /sent` | Pública | Llamada HTTP real de SMS, destinatario fijo y `dd()` |
| `GET /admissionist/reservation/list-calendar` | Pública porque `ScheduleController` no tiene constructor `auth` | Citas y datos asociados |
| `POST /admissionist/schedule/update` | Pública | Modifica estado de una cita |
| rutas `/admissionist/doctor-schedule*` | Públicas | Consulta, creación, edición y desactivación de horarios médicos |

El resto de páginas/controladores de admisión, recepción y administración exige al menos `auth` desde el constructor del controlador. El dashboard también exige `auth` desde su controlador.

### Autorización real original

- `GET /api/user` usa `auth:sanctum`.
- No existen policies ni reglas Gate del dominio.
- No hay alias de middleware Spatie `role`/`permission` registrados.
- Los controladores de usuarios, roles, permisos y maestros solo exigen `auth`.
- Los `can:` de usuarios/roles están comentados.
- En consecuencia, cualquier usuario autenticado puede administrar usuarios, roles, permisos y maestros.

### Rutas API públicas originales

Todas las siguientes rutas eran públicas y solo tenían el throttle general de API:

- Pacientes/PII: `POST /api/patient/show`, `POST /api/patient/show/search`.
- Ruta muerta: `GET /api/patient/reniec-api/search`; no tiene consumidor y referencia un método inexistente.
- Citas: doctor por especialidad, servicios por doctor y cálculo de tarifa.
- Agenda: horas disponibles y detalle de horario médico.
- Administración: búsquedas de usuarios, canales, especialidades, medios de interacción, tarifas, médicos y servicios.
- Responsables/PII: búsqueda de responsable.

### Consumidores internos encontrados

| Endpoint/grupo | Consumidores |
|---|---|
| Paciente `show` | `public/js/admissionist/patient/patient.js`, `appointment/appointment.js` |
| Paciente `show/search` | `public/js/admissionist/patient/patient.js` |
| Doctor por especialidad | alta/edición de citas, disponibilidad y filtros de calendario en `public/js/admissionist/` |
| Servicio por doctor | alta/edición de citas y disponibilidad |
| Cálculo de cita | `appointment/appointment.js` |
| Horas disponibles | alta/edición de citas y disponibilidad |
| Búsqueda de horario | `schedule/schedule.js` |
| Búsquedas administrativas | archivos homónimos bajo `public/js/admin/master/` y `public/js/admin/master/user/` |
| Responsable | `public/js/admissionist/responsible/responsible.js` |
| Feed de calendario | `calendario/calendario.js` y el inicializador FullCalendar legado |
| CRUD de horario | formularios Blade de schedule y `schedule/schedule.js` |
| `/sent` | Ningún consumidor en Blade, JS, Livewire, controladores o rutas |

Todos los consumidores API demostrados son same-origin y se ejecutan desde vistas del ERP. No se encontró un cliente con token Sanctum para estas rutas.

### Roles e intención observable

No existen seeders de roles/permisos. Los nombres exactos usados por `User::roleUser()` son:

- `ADMINISTRADOR`
- `ADMISION`
- `RECEPCION`
- `CAJA`
- `FACTURACION`

El sidebar añade además `COMERCIAL`. La navegación aporta la siguiente matriz mínima demostrable:

| Área | ADMINISTRADOR | ADMISION | COMERCIAL | RECEPCION |
|---|---:|---:|---:|---:|
| Dashboard | visible | visible | visible | visible |
| Usuarios, roles, permisos, maestros | sí | no | no | no |
| Pacientes, responsables, citas, horarios | sí | sí | sí | sí |
| Caja/ventas | no visible en menú admin | apertura | apertura | apertura, ventas, movimientos |

La evidencia es suficiente para reservar administración de usuarios/roles/permisos/maestros a `ADMINISTRADOR`. El inventario por sí solo no definía una matriz fina; la política provisional indicada posteriormente establece la contención que sigue, sin convertirla en diseño definitivo.

## Matriz provisional de seguridad, pendiente de Blueprint funcional

Esta matriz cierra únicamente la contención de Fase 1. No representa el modelo definitivo de roles/permisos ni sustituye el futuro Blueprint AS-IS / TO-BE.

| Dominio | ADMISION | RECEPCION | ADMINISTRADOR | COMERCIAL |
|---|---|---|---|---|
| Pacientes | leer, crear, modificar/inactivar | solo lectura | solo lectura | solo lectura |
| Responsables | leer y modificar/inactivar | solo lectura | solo lectura | solo lectura |
| Citas | leer, crear y modificar | solo lectura | solo lectura | solo lectura |
| Horarios médicos | leer, crear, modificar/inactivar | solo lectura | solo lectura | solo lectura |
| Usuarios, roles/permisos y maestros | sin acceso | sin acceso | administración completa | sin acceso |
| Apertura/cierre de caja, ventas, movimientos e impresión | sin acceso provisional | funciones actuales | sin acceso automático | sin acceso provisional |

Implementación deliberadamente simple:

- Los grupos de rutas concentran los roles de lectura y escritura.
- Las API internas de lectura admiten los cuatro roles provisionales.
- Las API y rutas administrativas exigen `ADMINISTRADOR`.
- Los componentes Livewire financieros verifican `RECEPCION` tanto en `mount()` como en cada rehidratación, evitando invocaciones directas por el endpoint genérico de Livewire.
- Usuarios autenticados sin uno de los cuatro roles provisionales no acceden al dashboard ni a datos operativos.
- Los enlaces de apertura de caja fueron retirados de los menús ADMISION y COMERCIAL; RECEPCION conserva sus rutas propias.

### Endpoints con efectos externos o PII

- `/sent`: SMS real y salida `dd()`.
- `/api/patient/show`: devuelve PII local o consulta DNI externamente.
- `/api/patient/show/search`: devuelve el registro completo del paciente.
- `/api/admin/responsible/search`: datos del responsable.
- `/api/admin/user/search`: datos de usuario.
- Feed de calendario: citas con paciente, médico y servicio.
- `ReniecService`: registraba la respuesta completa del proveedor, incluida PII.
- `Sales::buscarPorRuc`: consulta RUC desde una página autenticada; no se modifica el flujo financiero.

## CORS

La configuración actual permite cualquier origen para `api/*`, sin credenciales. No se modifica en Fase 1: el repositorio actual solo demuestra consumidores same-origin, pero no permite descartar dependencias del repositorio `LLAMADOR-PACIENTE-CEO`. Se requiere revisar ese repositorio antes de cerrar orígenes.

## Registro de correcciones

### 1. SMS público con efecto externo

- Evidencia original: `GET /sent` llamaba `MessageController::enviarSms()`, enviaba a un destinatario fijo y terminaba con `dd()`; no existían consumidores internos.
- Cambio: retirada de la ruta y eliminación del controlador de prueba.
- Archivos: `routes/web.php`, `app/Http/Controllers/message/MessageController.php`.
- Regresión: `ExternalEffectsAndLoggingTest::test_sms_debug_endpoint_does_not_exist_and_sends_no_http_request`.
- Compatibilidad: no se encontró consumidor legítimo. Un cache de rutas antiguo debe limpiarse durante un futuro despliegue controlado.

### 2. APIs internas públicas y exposición de PII

- Evidencia original: todas las API de pacientes, responsables, citas, agenda y maestros eran públicas salvo `/api/user`.
- Cambio: nuevo grupo `internal-api` con cookies cifradas, sesión, autenticación antes de CSRF y bindings. Las lecturas operativas exigen un rol provisional y las búsquedas administrativas añaden `role:ADMINISTRADOR`.
- Compatibilidad: los consumidores demostrados son `fetch` same-origin. `layouts/app.blade.php` añade automáticamente `X-CSRF-TOKEN` solo a solicitudes same-origin con métodos no seguros.
- Archivos: `app/Http/Kernel.php`, `routes/api.php`, `resources/views/layouts/app.blade.php`.
- Regresiones: `InternalApiAuthorizationTest` cubre los 15 endpoints sensibles; `AccessAndApiSmokeTest` comprueba visitante, usuario ERP y administrador.
- Riesgo: un consumidor externo no inventariado deja de acceder anónimamente. CORS permanece sin cambios hasta revisar el llamador.

La ruta muerta `/api/patient/reniec-api/search`, sin consumidor y asociada a un método inexistente, fue retirada. `ExternalEffectsAndLoggingTest` comprueba su ausencia.

### 3. Agenda anónima

- Evidencia original: `admissionist\schedule\ScheduleController` no aplicaba `auth`, permitiendo leer calendario y crear/editar/desactivar horarios o actualizar citas.
- Cambio: `routes/admision.php` separa lectura para los cuatro roles y escritura exclusiva de `ADMISION`; `routes/recepcion.php` exige `RECEPCION`.
- Archivos: `routes/admision.php`, `routes/recepcion.php`.
- Regresión: `ScheduleAuthorizationTest` verifica las siete rutas como visitante y conserva lectura/edición/desactivación para un usuario autenticado.
- Compatibilidad: las vistas ya dependían de `auth()->user()` y estaban enlazadas desde menús autenticados.

### 3.1. Menor privilegio para flujos financieros

- Evidencia original: ADMISION y COMERCIAL mostraban un enlace a apertura de caja; las acciones Livewire financieras solo comprobaban que hubiera un usuario autenticado.
- Cambio: retirada de esos enlaces y autorización `RECEPCION` en `CashierShifts`, `CashMovements` y `Sales`, incluida cada rehidratación Livewire.
- Archivos: menús `admision.blade.php`/`comercial.blade.php`, trait `RequiresRole` y los tres componentes Livewire.
- Regresiones: `ProvisionalRoleMatrixTest` prueba montaje positivo para RECEPCION y 403 directo para ADMISION, ADMINISTRADOR y COMERCIAL. El smoke financiero completo se ejecuta como RECEPCION.

### 4. Escalamiento de privilegios

- Evidencia original: los menús ocultaban administración, pero cualquier usuario autenticado podía asignarse `ADMINISTRADOR`, crear permisos o operar maestros.
- Cambio: registro de middleware Spatie `role`/`permission`; todas las rutas de `administrador.php` y las API administrativas requieren `ADMINISTRADOR`.
- Archivos: `app/Http/Kernel.php`, `routes/administrador.php`, `routes/api.php`.
- Regresión: `AccessAndApiSmokeTest` comprueba 403 y ausencia de mutación para usuario normal, además del flujo autorizado del administrador.
- Riesgo productivo: antes de desplegar debe verificarse que al menos la cuenta administrativa legítima tenga exactamente el rol `ADMINISTRADOR`. No se crean ni asignan roles automáticamente.

### 5. Login y sesiones

- Evidencia original: login sin throttle, sin regenerar sesión; logout no invalidaba sesión ni regeneraba CSRF; usuarios autenticados podían abrir el login.
- Cambio: middleware `guest`, limiter nominal de cinco intentos por minuto por email normalizado + IP, regeneración tras login, logout autenticado con invalidación y nuevo token, y redirección de autenticados a `/dashboard`.
- Archivos: `app/Providers/RouteServiceProvider.php`, `routes/web.php`, `AuthController.php`.
- Regresiones: `ApplicationAndAuthenticationSmokeTest` cubre login, sesión, throttle, redirección, logout y CSRF.
- Compatibilidad: los intentos repetidos legítimos pueden recibir 429 durante la ventana de un minuto.

### 6. PII en logs y secretos históricos

- Evidencia original: `ReniecService` registraba la respuesta completa del proveedor y conservaba dos credenciales históricas como literales comentados.
- Cambio: retirada del logging de la respuesta y eliminación de los bloques históricos. No se reescribió Git ni se intentó rotar credenciales.
- Archivo: `app/Services/ReniecService.php`.
- Regresiones: `ExternalEffectsAndLoggingTest` verifica que una respuesta exitosa con PII no genera logs y que los patrones de literales históricos no siguen en el servicio actual.
- Pendiente productivo: los propietarios de las cuentas de los dos proveedores históricos deben revocar/rotar esas credenciales fuera de este repositorio.

## Pruebas y aislamiento

- Las pruebas continúan usando SQLite `:memory:` y las guardas de Fase 0.
- `Http::preventStrayRequests()` hace fallar cualquier tráfico HTTP no simulado; cada prueba de proveedor declara un fake específico.
- Mail, Queue y Notification continúan faked globalmente.
- No se efectuaron llamadas externas, migrations productivas ni cambios de esquema.
- `ProvisionalRoleMatrixTest` aporta casos positivos y negativos para ADMISION, RECEPCION, ADMINISTRADOR y COMERCIAL, además de usuario sin rol y acceso directo a Livewire.
- Resultado completo después de aplicar la matriz: **61 tests, 162 assertions, 0 fallos**.

## Flujos existentes bloqueados por la matriz provisional

Las vistas de RECEPCION reutilizan plantillas y JavaScript de ADMISION, por lo que actualmente muestran controles que intentan estas escrituras, ahora rechazadas con 403:

- pacientes: `admissionit.patient.store`, `admissionit.patient.update`, `admissionit.patient.delete`;
- citas: `admissionit.appointment.store`, `admissionit.appointment.update` y `admissionit.schedule.update`;
- responsables: `admissionit.responsible.update`, `admissionit.responsible.delete`;
- horarios: `admissionit.doctor.schedule.store`, `.update` y `.delete`.

COMERCIAL navega por páginas de admisión y puede encontrar los mismos controles, pero tampoco puede ejecutar escrituras. No se concedieron excepciones. La futura definición funcional debe decidir si se eliminan/ocultan esos controles, se crean vistas de solo lectura o se autoriza algún flujo concreto.

## Pendientes de producción

1. Validar asignaciones reales de `ADMINISTRADOR` antes de desplegar.
2. Limpiar de forma controlada caches de rutas/configuración en despliegue para evitar definiciones obsoletas.
3. Rotar las credenciales históricas con los proveedores correspondientes.
4. Revisar `LLAMADOR-PACIENTE-CEO` antes de cerrar CORS o decidir autenticación entre aplicaciones.
5. Resolver en una fase posterior la carga duplicada de archivos de rutas en `RouteServiceProvider`/`web.php`.
6. Alinear las vistas compartidas de RECEPCION y COMERCIAL con su acceso provisional de solo lectura.
