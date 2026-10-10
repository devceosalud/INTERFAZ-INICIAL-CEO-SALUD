# Validación final de Entrega 1 — 10/10/2026

Actualizaci�n posterior a la revisi�n bloqueante del PR #3: [correcciones y nueva validaci�n](CORRECCIONES_PR_3_AGENDA.md). Los resultados de este documento corresponden a la validaci�n anterior (`b83cf57`).

Rama: `codex/auditoria-agenda-adelantos-documentos`.
Base remota comprobada: `0d37dee0a4209fccc6326894f46dccd4a2688d10`.
Entrega inicial local: `c9e43492543da7cfc2d6a224df026130a23a0f26`.

El resultado queda preparado para revisión técnica en PR Draft. No es una autorización ni una ejecución de despliegue. No se modificó main, Hostinger, permisos productivos, pagos reales ni datos reales de pacientes. Todos los fixtures y concesiones usados por QA pertenecen a la base nueva descrita abajo.

## Contrato del endpoint antiguo y consumidores

Búsqueda sobre archivos versionados con `git grep` y revisión de escrituras/URLs/nombres de ruta en app, resources, public, routes, tests y docs:

| Referencia | Uso confirmado |
|---|---|
| `resources/views/scheduling/agenda/index.blade.php:29` | Expone `data-appointment-store`. No existe lectura de `appointmentStore` en JavaScript. Es un atributo de compatibilidad, no un envío. |
| `public/js/scheduling/agenda.js:198` y `agenda-operational-workspace.js` | El cliente operativo toma `board.dataset.registrationStore` y hace POST `/scheduling-mvp/agenda/registrations`. Envía mode RESERVE/CONFIRM, booking_type, UUID y dinero validado. |
| `routes/scheduling.php`, `AgendaAppointmentController::store` | Definición del endpoint antiguo, ahora adaptado al registro operativo. |
| `AgendaAppointmentWriteTest`, `AgendaStabilizationTest`, `AgendaRefinementTest`, `ActiveDoctorServiceCatalogTest`, nuevos tests de integración | Peticiones directas automatizadas. Expectativas adaptadas a reserva pendiente o a rechazo de datos/permisos inválidos. |
| `AgendaBoardTest` | Comprueba el atributo renderizado; no es un cliente de creación. |
| `PILOT_LOCAL_RELEASE_CANDIDATE.md`, diagnóstico previo | Referencias históricas al contrato LEGADO. La entrega y este informe especifican el contrato actual. |

No se encontró cliente interno activo que dependa de crear nuevas citas LEGADO impagas con ocupación inmediata por ese endpoint. La búsqueda se limita al repositorio: no prueba ausencia de integraciones externas, otros repositorios, extensiones o scripts operativos fuera de él.

La alta antigua sin mode/booking_type se guarda como reserva REGULAR pendiente, sin ocupar intervalo. UUID de cliente obligatoria para dinero positivo o confirmación explícita. Campos inyectados de total/estado no constituyen dinero. El 50% y la exoneración autorizada siguen vigentes en servidor. Registros LEGADO existentes conservan datos y ocupación. Las otras escrituras heredadas fuera del MVP mantienen su comportamiento y no se declaran auditadas por esta comprobación de consumidores.

## Hallazgo de concurrencia y corrección

Con cuatro procesos PHP liberados simultáneamente para la misma UUID y confirmación de S/50, la primera ejecución sobre MariaDB devolvió dos 201 y dos 500. El recorder solo registró un pago, pero se capturó SQLSTATE 40001 / error 1213 al obtener el lock de appointment_operations: `INSERT IGNORE` seguido de `SELECT ... FOR UPDATE` podía producir una promoción de locks compartidos incompatible.

En `OperationalRegistrationService` y `ReservationPaymentService`, la inserción de operación usa ahora `upsert` que actualiza exclusivamente la misma request_key. MySQL/InnoDB obtiene el lock exclusivo sobre la clave única desde esa operación. No se actualizan payload_hash, appointment_id, created_at ni el resultado original en duplicados. La comprobación posterior de hash sigue rechazando datos distintos con 409, y el resultado ya confirmado se reutiliza sin otro dinero.

No se añade un reintento ciego de transacciones con archivos externos ni se modifica el recorder financiero. Se verificaron la UUID repetida, la UUID con importes distintos, las claves distintas para un mismo paciente/intervalo, la competencia por un intervalo, el pago repetido, la identidad bancaria repetida y peticiones sin CREATE.

## Entorno aislado y resultados

- MariaDB **10.4.32**, TCP **127.0.0.1:33317**, aislamiento **REPEATABLE-READ**. Datadir nuevo exclusivo: `storage/app/qa-final/mysql-runtime`. No se usó el datadir/servicio existente de XAMPP ni el puerto 3306.
- Schema exclusivo `ceosalud_qa_entrega1`; usuario `agenda_qa` con acceso únicamente a ese schema. Su contraseña del helper es un valor ficticio local conocido, no una credencial real. La instancia solo escucha en loopback.
- `IsolatedAgendaQa` rechaza `.env`, conexiones no locales, otro puerto/schema/usuario/URL/socket y un datadir distinto al runtime del checkout. Es un helper de tests separado; no se relajó `tests/CreatesApplication.php` ni el guard de la suite normal.
- App testing, HTTP externo bloqueado, correo/notificaciones/cola simulados. Sesión de archivo para navegador, array para integración. El servidor visual PHP monohilo no se usa para medir concurrencia: esa prueba usa procesos y conexiones PDO independientes que ejecutan el kernel HTTP real de Laravel con la misma barrera.
- appointments, appointment_operations, payments, vouchers y cashier_shifts se verificaron como InnoDB. La inicialización del runtime nuevo requirió reiniciar y reparar su tabla de sistema mysql.db; se comprobó OK antes de cerrar QA. No fue una reparación de una BD existente ni una modificación al código productivo.

| Prueba final | Evidencia / resultado |
|---|---|
| PHPUnit normal (SQLite en memoria) | **582 tests, 3.234 aserciones, cero fallos/errores, 1 omitido**. 581 pasan. |
| MariaDB/InnoDB | **77 tests, 460 aserciones, cero fallos/errores/omitidos**. 7 concurrencia, 19 reglas Agenda/adelanto, 24 Pacientes, 24 Horarios y 3 Usuarios. |
| JavaScript completo | **191 tests, 191 pasan, cero fallos/omitidos**. |
| Navegador integrado, tres perfiles ficticios | Sin CREATE: reserva/confirmación bloqueadas con mensaje; CREATE sin pago: reserva pendiente; ambos permisos + caja ficticia: adelanto y confirmación separados con umbral exacto. |
| Navegador y verificación SQL | Doble clic: una reserva. S/49.99 + S/0.01: dos pagos que suman S/50, un ticket; confirmar no crea otro pago. Cuatro citas en total: una LEGADO conservada, dos pendientes sin dinero y una CONFIRMADA con S/50. Seis operaciones. Solo histórica y confirmada ocupan intervalo. |
| Regresión visual | Agenda Día/Semana, listado y ficha maestra de Pacientes, listado/editor de Usuarios y calendario con horario 08:00–12:00. No se guardaron cambios en esos maestros desde el navegador. |
| Preservación | Fila histórica comparada completa antes/después, idéntica. Conteos/valores críticos de pacientes, usuarios/roles/permisos y horario ficticios conservados por las comprobaciones SQL. |
| Sintaxis y diff | PHP/JS modificados/nuevos y whitespace del diff comprobados. |

La omisión normal es preexistente: rollback nativo DROP COLUMN de `AppointmentAgendaSchemaTest` requiere SQLite >=3.35; el runtime instalado es anterior. No se marca ese test como aprobado. No hay nuevas migraciones en la entrega.

Las dos primeras discrepancias al ampliar regresiones MariaDB provenían de las aserciones de tests: comillas de identificadores SQLite frente a backticks MySQL y TIME HH:mm frente a HH:mm:ss. Se hicieron portables `OperationalPatientModuleTest` y `DoctorScheduleWorkspaceTest` sin cambiar consultas ni comportamiento de esos módulos. Ambas suites finales pasan con la misma intención de validación.

## Revisión de los 30 archivos originales

| Archivo | Resultado de revisión |
|---|---|
| `app/Console/Commands/DiagnoseCommercialPilot.php` | Enumera requisitos ausentes sin conceder permisos o crear contexto financiero. |
| `app/Console/Commands/PlanAgendaPermissions.php` | Plan nominativo de solo lectura, sin apply ni sync de permisos. |
| `app/Http/Controllers/Scheduling/AgendaAppointmentController.php` | Reutiliza registro operativo y valida reserva regular/UUID financiera; no crea LEGADO nuevo. |
| `app/Http/Controllers/Scheduling/OperationalRegistrationController.php` | Validación compatible con Laravel 9; proofs prohibido, proof singular intacto. |
| `app/Http/Requests/Scheduling/OperationalRegistrationRequest.php` | Validación compatible con Laravel 9; proofs prohibido, proof singular intacto. |
| `app/Http/Requests/Scheduling/StoreRegularAgendaAppointmentRequest.php` | Reutiliza registro operativo y valida reserva regular/UUID financiera; no crea LEGADO nuevo. |
| `app/Services/Scheduling/OperationalRegistrationService.php` | Permiso financiero conservado; lock de UUID corregido y verificado en InnoDB, hash/resultados inmutables. |
| `app/Services/Scheduling/ReservationPaymentService.php` | Permiso financiero conservado; lock de UUID corregido y verificado en InnoDB, hash/resultados inmutables. |
| `app/Support/Scheduling/AgendaPermissionPlan.php` | Plan nominativo de solo lectura, sin apply ni sync de permisos. |
| `docs/AUDITORIA_AGENDA_ADELANTOS_DOCUMENTOS.md` | Diagnóstico previo y entrega inicial diferenciados; OCR y batch se describen como trabajo futuro. |
| `docs/ENTREGA_1_AGENDA_PERMISOS.md` | Diagnóstico previo y entrega inicial diferenciados; OCR y batch se describen como trabajo futuro. |
| `phpunit.xml` | Solo aísla el flag de caja piloto; guard SQLite original intacto. |
| `public/js/scheduling/agenda-guidance.js` | Acciones, requisitos y UUID de reintento; pruebas JS y navegador confirman el flujo. |
| `public/js/scheduling/agenda-operational-form.js` | Acciones, requisitos y UUID de reintento; pruebas JS y navegador confirman el flujo. |
| `public/js/scheduling/agenda-operational-workspace.js` | Acciones, requisitos y UUID de reintento; pruebas JS y navegador confirman el flujo. |
| `public/js/scheduling/agenda.js` | Acciones, requisitos y UUID de reintento; pruebas JS y navegador confirman el flujo. |
| `resources/views/scheduling/agenda/partials/operational-registration.blade.php` | Textos/acciones de reserva, adelanto y confirmación; archivo singular conservado. |
| `resources/views/scheduling/agenda/partials/patient-modal.blade.php` | Textos/acciones de reserva, adelanto y confirmación; archivo singular conservado. |
| `resources/views/scheduling/agenda/partials/quick-registration.blade.php` | Textos/acciones de reserva, adelanto y confirmación; archivo singular conservado. |
| `routes/scheduling.php` | Alta antigua con VIEW/CREATE; resto de rutas conserva reglas. |
| `tests/Feature/Scheduling/AgendaAppointmentWriteTest.php` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |
| `tests/Feature/Scheduling/AgendaBoardTest.php` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |
| `tests/Feature/Scheduling/AgendaPermissionPlanTest.php` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |
| `tests/Feature/Scheduling/AgendaRefinementTest.php` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |
| `tests/Feature/Scheduling/AgendaStabilizationTest.php` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |
| `tests/Feature/Scheduling/PilotAppointmentOperationsTest.php` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |
| `tests/Feature/Scheduling/PilotDiagnosisTest.php` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |
| `tests/JavaScript/agenda-guidance.test.js` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |
| `tests/JavaScript/agenda-operational-form.test.js` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |
| `tests/JavaScript/agenda-permission-flow.test.js` | Cobertura de reglas/contrato e históricos; resultados PHP/JS repetidos en esta validación. |

Se añaden solo infraestructura/fixtures de QA en `tests/Support`, clases de integración en `tests/Integration`, configuración `phpunit.agenda-mariadb.xml`, este informe y las dos adaptaciones de aserciones indicadas. La única ampliación del código de aplicación durante validación es el lock de idempotencia en los dos servicios.

Comparación contra la base confirma que no hay cambios en database/migrations, modelos, controladores administrativos de Usuarios/Pacientes/Horarios, storage de proofs o AppointmentDocumentController. No hay helper batch activo, OCR, carga múltiple, migration descartada, configuración de proveedor financiero externo ni regla prohibited_with en app/tests. Los campos múltiples se siguen rechazando.

Borradores completos previos permanecen en `storage/app/delivery-drafts/pre-entrega1`, ignorados por Git y ajenos a rutas/autoload/migraciones. Capturas, logs, datadir y resultados QA también están ignorados: no se publican como datos del repositorio.

## Repetición local

Requisitos: checkout de esta rama sin `.env`, vendor disponible, PHP con pdo_mysql/pdo_sqlite, Node y MariaDB local. Preparar bootstrap/cache y storage/framework/sessions/views/cache como directorios locales escribibles.

Para la suite habitual:

```powershell
php vendor/phpunit/phpunit/phpunit
$qaJsTests = @(rg --files tests/JavaScript -g '*.test.js')
node --test @qaJsTests
```

Para una instancia NUEVA de QA (ejemplo XAMPP Windows), comprobar que 33317 está libre y que el directorio aún no existe. Nunca apuntar al servicio/base existente. En el directorio del checkout:

```powershell
$qaDataDir = Join-Path (Get-Location) 'storage/app/qa-final/mysql-runtime'
if (Test-Path -LiteralPath $qaDataDir) { throw 'Revisar el runtime existente antes de inicializar.' }
& C:/xampp/mysql/bin/mysql_install_db.exe --datadir=$qaDataDir --port=33317
& C:/xampp/mysql/bin/mysqld.exe --no-defaults --basedir=C:/xampp/mysql --datadir=$qaDataDir --port=33317 --bind-address=127.0.0.1 --skip-name-resolve --console
```

Con la instancia propia corriendo, desde otra terminal, crear únicamente el schema/usuario QA con el cliente del puerto 33317:

```sql
CREATE DATABASE ceosalud_qa_entrega1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'agenda_qa'@'127.0.0.1' IDENTIFIED BY 'local-qa-only';
GRANT ALL PRIVILEGES ON ceosalud_qa_entrega1.* TO 'agenda_qa'@'127.0.0.1';
```

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.agenda-mariadb.xml
php tests/Support/agenda-qa-browser-seed.php
php -S 127.0.0.1:8021 -t public tests/Support/agenda-qa-browser-router.php
```

Seed e integración resetean SOLO el schema QA validado. Para revisar después de la suite, sembrar de nuevo; no correr integración mientras se prueba navegador. Entrar en http://127.0.0.1:8021 con qa-nocreate, qa-reserve, qa-payment o qa-admin @example.invalid y contraseña ficticia `AgendaQaOnly-2026!`. El seed imprime la fecha, IDs y documento ficticios. El router solo acepta cli-server y cliente loopback; no se añade ninguna ruta QA al app.

Secuencia visual: perfiles nocreate/reserve en 08:00; payment guarda 08:30 sin dinero, registra 49.99 y 0.01 por separado, confirma y guarda 09:00 con doble clic. Abrir Pacientes y ficha, Usuarios/editor (cancelar), y Horarios. Después de esa secuencia:

```powershell
php tests/Support/agenda-qa-browser-verify.php
```

Los servidores QA de esta ejecución quedaron detenidos al terminar. Al repetir, detener solo esos servidores QA. No hay rollback de esquema de esta entrega ni instrucción de migración productiva.

## Evidencia local y límites

`storage/app/qa-final/`: php-tests.txt/php-results.xml, mariadb-tests.txt/mariadb-results.xml, javascript-tests.txt, browser-results.json, browser-db-results.json, browser-console-errors.json y capturas browser-*.jpg. Son artefactos locales; no están en el PR. Los scripts y la configuración para reproducir están versionados.

- Se validó MariaDB 10.4.32, no MySQL 8 ni el engine/configuración exactos de Hostinger. Tampoco se hizo carga sostenida, benchmark, prueba multihost o una auditoría de todo writer fuera del MVP.
- Se validó un navegador integrado y su viewport disponible; no se declara una matriz completa de navegadores/resoluciones. El modo browser de QA usa APP_ENV=testing; CSRF de Agenda no se presenta como validado contra configuración productiva. Los tests del editor de Usuarios ejercitan CSRF real con middleware explícito.
- Se observaron TypeError de selectpicker en assets/js/custom.min.js del shell compartido. Ese archivo y sus layouts están iguales a la base. No bloquearon los flujos comprobados. Requieren revisión del shell aparte y quedan como riesgo conocido, no como consola limpia.
- Permisos/configuración productivos siguen intactos. La sesión de prueba reportada como ID 8 no se corroboró contra producción. Cualquier asignación requiere identidad confirmada, aprobación nominativa y Caja.
- Clientes externos al repositorio deben revisarse antes del despliegue. El contrato ya no permite ocupación regular impaga; no restaurar el bypass por compatibilidad.
- Concurrencia ejercitada hasta cuatro solicitudes del mismo actor/UUID; no implica garantía de ausencia de cualquier deadlock posible. Mantener monitoreo técnico e idempotencia de reintentos.
- Antes de un despliegue autorizado, preparar respaldo/reversión, verificar migraciones operativas ya existentes y coordinar caché de assets; estas acciones no se ejecutaron aquí.

Revertir código mediante git revert de los commits de entrega/validación en una rama revisable, sin borrar pagos/citas ni cambiar permisos reales. Revertir c9e4349 reabriría el bypass impago; evaluar ese riesgo. El SHA definitivo y enlace Draft se entregan al usuario tras publicar únicamente la rama autorizada.
