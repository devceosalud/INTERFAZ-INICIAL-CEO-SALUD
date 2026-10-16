# Agenda, Etapa A — revisión y QA

## Alcance y baseline

Repositorio `devceosalud/INTERFAZ-INICIAL-CEO-SALUD`; rama `codex/agenda-etapa-a-semaforizacion`, worktree nuevo `agenda-etapa-a`. Baseline comprobado nuevamente por fetch: `2b4f1e8db81b8629aa8508cb41f45b0a263e660c` (PR #24). Código probado: `3cec2579e616e6ed4a765f14283d33c403800b2c`; el commit de cierre agrega solo documentación/evidencias. No se incorporó #21, se conservaron worktrees y cambios de otras ramas. No hubo acceso a Hostinger ni escrituras productivas.

## Comportamiento y límites

- Pago real atribuido, parcial o total: amarillo. `estado_pagado`, créditos y snapshots históricos no lo sustituyen. Un importe sin atribución inequívoca muestra revisión pendiente; nunca acredita dinero.
- Fondo de cita blanco sin pago real acreditado; amarillo con pago real; turquesa por llegada/salida registrada; rojo por cancelación, retiro o solicitud de reembolso, en ese orden de prioridad. HERIDA fucsia y POSTOPERATORIO plomo se presentan **solo como chips junto al nombre**, nunca como fondo del slot, y se conservan simultáneamente. Semana usa chips compactos HERIDA/POSTOP con descripción completa accesible. No se deducen del servicio ni generan gratuidad.
- Llegada registrada mediante Nicol: turquesa, manteniendo etiquetas. CANCELADO/RETIRO efectivo: rojo, prevalece sobre llegada, evento informativo sin ocupar capacidad. NO_ASISTIO no se convierte en cancelación. Una solicitud de reembolso pendiente sí es roja según la decisión posterior; se diferencia mediante etiqueta de solicitud no ejecutada, sin alterar estado, disponibilidad o dinero. Reprogramar por retiro conserva roja la cita original RETIRO, no marca automáticamente la cita de destino.
- ADMIN: seguimiento inicial de citas visibles con pago real, presencia, salida registrada, solicitud de reembolso o cancelación/retiro. Filtra DESPUÉS de autorizar; no recibe reservas privadas ajenas, aunque tengan adelanto o las haya creado él. Los demás roles conservan su visibilidad habitual.
- Creador y responsable son distintos. ADMIN recibe creador en Día/Semana, tooltip y detalle enfocable por teclado; Mes sigue anónimo y permite abrir el día por teclado. Otros perfiles no reciben los campos de creador. Alta e historial comparten restricción; actores del historial que identifiquen al creador se ocultan en la respuesta no ADMIN. Auditoría almacenada intacta. Nicol no atribuye al creador una responsabilidad inexistente.
- **Decisiones de #25 resueltas por negocio:** base ROJO > TURQUESA > AMARILLO > BLANCO y chips secundarios; salida física explícita desde Nicol después de ATENDIDO. La acción SALIDA_REGISTRADA utiliza el endpoint de recorrido existente, permiso VIEW + UPDATE y visibilidad existente, UUID, motivo obligatorio y actor/auditoría. Guarda `hora_salida`, manteniendo `estado_cita=ATENDIDO`, sin reescribir otras marcas ni importes. Turquesa + nombre tachado solo cuando ambas condiciones existen; no determina reevaluación. El backend rechaza estados no atendidos, reservas pendientes, motivo vacío, permisos revocados, intentos de inyectar dinero/timestamps y doble salida concurrente. El mismo UUID conserva la primera actuación.

## Archivos y propósito

| Grupo | Archivos principales / razón |
|---|---|
| Proyección visual | `AgendaAppointmentPresentation`, `AgendaLegend`, `AgendaBoardPresenter`, `OperationalAgendaAppointmentService`: señales centralizadas, consulta de presentación separada de ocupación y resumen mensual anónimo. |
| Privacidad | `AgendaCreatorVisibility`, `AgendaAppointmentController`, `AppointmentWorkflowController`, `AdmissionBoardService`: restricción en servidor, sin alterar auditoría o permisos existentes. |
| Indicador explícito | Migración `2026_10_15_230000_add_explicit_diabetic_wound_flag`, `Appointment`, `AppointmentPriceInput`, `CreateAppointmentService`, `OperationalRegistrationService`, `LegacyAppointmentRegistration`, `OperationalRegistrationController`, `AgendaDetailsController`: booleano validado, auditado, con autorización y mismo motor de registro/idempotencia. |
| Interfaz | `agenda.js`, `agenda-selection.js`, `agenda-operational-workspace.js`, `admission-board.js`, `agenda-layout.css`, parciales `operational-registration`, `quick-registration`, `toolbar`: checkbox, limpieza de contexto, colores/etiquetas, teclado y detalle ADMIN. CSS acotado a Agenda. |
| Salida explícita | `AdmissionCareService`, `AdmissionBoardController`, `AdmissionBoardService`, nueva migración `2026_10_15_231000_add_explicit_appointment_departure`: acción de salida transaccional auditada tras ATENDIDO; sin nuevo estado clínico ni permisos financieros. |
| Pruebas | `AgendaSemaphorizationTest`, `AgendaCreatorPrivacyTest`, `AgendaAppointmentWriteTest`; expectativas de `AgendaBoardTest`, `WorkerAccessTest`, `AgendaRefinementTest`, `PilotAppointmentOperationsTest`; pruebas JS de creador/workspace/cotización/OCR. Integración MariaDB en `tests/Integration/AgendaStageA`. |
| QA aislado | `phpunit.agenda-stage-a.xml`, `IsolatedAgendaStageAQa`, scripts `prepare/seed/router/readback` y `browser-agenda-stage-a.cjs`. Sin credenciales productivas. |

Inventario completo: `git diff --name-only 2b4f1e8db81b8629aa8508cb41f45b0a263e660c...HEAD`. Las modificaciones de pruebas existentes responden a contratos aprobados: rol no ADMIN sin creador; ADMIN filtra seguimiento; ADICIONAL conserva modalidad con amarillo por pago. Las pruebas de cotización incorporan AbortController/timers reales al sandbox y las de OCR verifican ubicación en el panel de adelanto del #24, sin revertir su código.

## Ambiente y aislamiento

Windows local, PHP **8.3.35**, Laravel **9.52.21**, PHPUnit **9.6.34**, Node **26.3.0**, MariaDB **11.8.9**, Chrome **155.0.8059.79** mediante Playwright. SQLite `:memory:` para suite completa. MariaDB loopback `127.0.0.1:33323`, esquema NUEVO `ceosalud_qa_agenda_a`, usuario exclusivo de ese esquema; las otras bases QA se conservaron. Guardas verifican versión, datadir `.rc-suite-mariadb-1189/data`, esquema, usuario, entorno testing y ausencia de `.env` antes de pruebas que recrean tablas. Proveedores HTTP, correo, cola y notificaciones falsificados.

Navegador: cuatro contextos/cookies independientes (ADMIN, dos comerciales y Nicol), pacientes ficticios, HTTP solo loopback 8086/8087, autenticación y CSRF reales. CSP de router QA y bloqueo de egress en navegador impiden conexiones externas, incluida Clarity. La instalación de Clarity en el código permanece intacta. Esta protección local no certifica privacidad productiva.

## Ejecución y evidencias

Resultados finales: **920 PHP / 7.393 aserciones; 207 MariaDB / 1.970 aserciones; 279 JavaScript; 10 escenarios de navegador, todos aprobados, cero errores de página**. Sintaxis: 42 archivos PHP y 9 JavaScript, sin errores. MariaDB cubre diez familias relevantes, no las 920 pruebas generales. Resultados verificables en `docs/qa/agenda-etapa-a/resultados.json`. Logs completos ignorados en `storage/app/qa-stage-a` y `storage/app/qa-rc`; no se versionan contraseñas aleatorias de QA, sesiones, caches ni dumps.

El recorrido visual verifica:

1. Fondos reales en Día; amarillo con S/20 permanece pendiente, blanco sin dinero. HERIDA y POSTOP con colores propios solo junto al nombre, solos y combinados.
2. Segundo comercial sin reservas privadas del primero ni creador en JSON.
3. ADMIN distingue creador de responsable y abre detalle por teclado, dentro de seguimiento autorizado.
4. Cancelada tras llegada roja; el intervalo conserva entrada disponible.
5. Semana conserva señales; Mes sin nombres y apertura por teclado del día.
6. Alta desde UI de reserva HERIDA con S/0, sin confirmación ni pagos nuevos.
7. Llegada real desde Nicol reflejada turquesa en Agenda; ATENDIDO sin salida no tacha.
8. Salida desde Nicol con constancia obligatoria; sello persistido y nombre tachado en Nicol/Día/Semana. Se conserva el dinero. Geometría de Semana verificada: nombre no desaparece y ambos chips dentro del evento.
9. Dos sesiones sobre dos procesos PHP (8086/8087) compiten por la misma salida: un 200, un 409, un evento y una operación; sin duplicación de dinero.
10. Viewport móvil verificado 390×844, ancho del documento 390; navegación y consulta, captura `mobile.png`. No se afirma prueba en dispositivo físico.

BD del circuito: resultados exactos en JSON de evidencias; se conservan los pagos sintéticos originales y la reserva UI nueva no agregó dinero. Persistencia final: 11 citas, 3 pagos sintéticos originales S/90, 10 operaciones y 25 eventos. Las suites automáticas comprueban además privacidad sin responsable, denegación 403/404 al editar HERIDA, idempotencia/reintento discordante, historial sin cambios, disponibilidad, ADICIONAL, doble duración, paridad heredada/operativa, descuentos y permisos.

Fallos encontrados durante la iteración: el detalle ADMIN estaba oculto por una clase CSS heredada (corregido); la apertura de Mes por teclado apuntaba a un evento dentro de «más» (se hizo enfocable el día y se compactaron señales). Al conservar Mes anónimo se eliminó una consulta de retiros: prueba antigua exigía igualdad Día/Mes, ahora comprueba exactamente una consulta menos (13/12), manteniendo la comprobación contra N+1. Se corrigieron expectativas antiguas de color/modalidad y fixtures de seguimiento ADMIN; no se quitaron controles financieros. Ejecuciones intermedias fallidas o interrumpidas no acreditan el commit final.

## Reproducción local

1. Checkout separado de esta rama, sin `.env`, dependencias de `composer.lock`, PHP 8.3.35 con SQLite/MySQL y Node. Nunca ejecutar scripts de QA en un directorio productivo.
2. Suite completa: `php vendor/phpunit/phpunit/phpunit --colors=never`. JavaScript: `powershell -File tools/qa/run-js.ps1 -Report stage-a-js-verified.txt` (crear previamente directorio ignorado `storage/app/qa-rc`).
3. MariaDB: preparar el runtime aislado local de 11.8.9, puerto 33323 y datadir exacto indicado arriba. Crear directorio ignorado `storage/app/qa-stage-a`; `php tests/Support/prepare-agenda-stage-a.php` crea solo el nuevo esquema/usuario, rechaza colisiones sin marcador. `php vendor/phpunit/phpunit/phpunit -c phpunit.agenda-stage-a.xml --colors=never`. **Recrea únicamente tablas del esquema QA nuevo; no correr a la vez que el navegador.** Los valores de conexión de fixtures son exclusivamente locales.
4. Navegador: con la suite MariaDB terminada, definir contraseña aleatoria QA de al menos 24 caracteres en `AGENDA_STAGE_A_PASSWORD` y `AGENDA_STAGE_A_RESET=YES_OWNED_STAGE_A`; ejecutar `php tests/Support/agenda-stage-a-seed.php` (recrea solo esquema anterior). Guardar localmente el mismo password como JSON en `storage/app/qa-stage-a/private.json`, borrar las variables del shell y no versionar ese archivo. Verificar mensaje de semilla completada y `fixtures.json` antes de arrancar.
5. Arrancar PHP con `-S 127.0.0.1:8086 -t public tests/Support/agenda-stage-a-router.php` y, si se necesita, 8087 en procesos ocultos. Ejecutar `node tools/qa/browser-agenda-stage-a.cjs`. El harness usa Chrome/Playwright instalados en las rutas locales explícitas; adaptar solo rutas de herramientas para reproducir en otra máquina. Detener exclusivamente procesos QA propios comprobados. No exponer esos puertos fuera de loopback.
6. Leer persistencia sin modificar: `php tests/Support/agenda-stage-a-readback.php`. Capturar reporte antes de volver a correr MariaDB o reseed. Un segundo circuito necesita reseed explícito del esquema poseído, no pacientes reales.

## Publicación futura y reversión (no ejecutadas)

Dos migraciones **aditivas y nullable**, en orden: `2026_10_15_230000_add_explicit_diabetic_wound_flag` y `2026_10_15_231000_add_explicit_appointment_departure` (`hora_salida`). Sin backfill ni cambios a precios, pagos o estados históricos. No seeders ni grants. Antes de una publicación autorizada: respaldo verificado de BD/código/configuración/adjuntos, comprobación de autodespliegue, ensayo en staging, aplicar únicamente estas migraciones y publicar código/assets de este PR con su SHA revisado. Este documento no autoriza ejecutarlo.

Rollback preferido: revertir código/assets al baseline revisado conservando ambas columnas y datos. No restaurar una BD antigua sobre pagos nuevos. Cada `down()` rechaza eliminar su columna si existe cualquier valor registrado (incluso false para HERIDA); solo se permite borrado en un esquema descartable sin valores, con autorización. Al revertir la interfaz dejarían de mostrarse HERIDA/salida física y el tachado: suspender la operativa que dependa de esa señal hasta resolverla, preservando sus datos.

## Dictamen

**Etapa A implementada y probada en desarrollo; mismo PR Draft para revisión, sin autorización de despliegue.** Las dos decisiones de #25 fueron incorporadas. Esto no certifica el entorno productivo ni supera la revisión de privacidad de Clarity. Ningún dato real utilizado; `AppointmentVisibility`, `DoctorAvailabilityService`, motor económico y `layouts/app.blade.php`/Clarity conservados. Sin merge, despliegue ni permisos productivos. Privacidad/admisibilidad de Clarity y validación del entorno productivo continúan siendo requisitos separados, no superados por este QA local.

## Inventario exacto del árbol candidato

```text
app/Http/Controllers/Scheduling/AdmissionBoardController.php
app/Http/Controllers/Scheduling/AgendaAppointmentController.php
app/Http/Controllers/Scheduling/AgendaDetailsController.php
app/Http/Controllers/Scheduling/AppointmentWorkflowController.php
app/Http/Controllers/Scheduling/OperationalRegistrationController.php
app/Models/Appointment.php
app/Services/Scheduling/AdmissionBoardService.php
app/Services/Scheduling/AdmissionCareService.php
app/Services/Scheduling/AgendaBoardPresenter.php
app/Services/Scheduling/CreateAppointmentService.php
app/Services/Scheduling/LegacyAppointmentRegistration.php
app/Services/Scheduling/OperationalAgendaAppointmentService.php
app/Services/Scheduling/OperationalRegistrationService.php
app/Support/Scheduling/AgendaAppointmentPresentation.php
app/Support/Scheduling/AgendaCreatorVisibility.php
app/Support/Scheduling/AgendaLegend.php
app/Support/Scheduling/AppointmentPriceInput.php
database/migrations/2026_10_15_230000_add_explicit_diabetic_wound_flag.php
database/migrations/2026_10_15_231000_add_explicit_appointment_departure.php
docs/AGENDA_ETAPA_A_QA.md
docs/AGENDA_MEJORAS_25_34_AUDITORIA.md
docs/qa/agenda-etapa-a/resultados.json
phpunit.agenda-stage-a.xml
public/css/scheduling/agenda-layout.css
public/js/scheduling/admission-board.js
public/js/scheduling/agenda-operational-workspace.js
public/js/scheduling/agenda-selection.js
public/js/scheduling/agenda.js
resources/views/scheduling/agenda/partials/operational-registration.blade.php
resources/views/scheduling/agenda/partials/quick-registration.blade.php
resources/views/scheduling/agenda/partials/toolbar.blade.php
tests/Feature/Auth/WorkerAccessTest.php
tests/Feature/Scheduling/AdmissionDepartureTest.php
tests/Feature/Scheduling/AgendaAppointmentWriteTest.php
tests/Feature/Scheduling/AgendaBoardTest.php
tests/Feature/Scheduling/AgendaCreatorPrivacyTest.php
tests/Feature/Scheduling/AgendaRefinementTest.php
tests/Feature/Scheduling/AgendaSemaphorizationTest.php
tests/Feature/Scheduling/PilotAppointmentOperationsTest.php
tests/Integration/AgendaStageA/AdmissionBoardMariaDbTest.php
tests/Integration/AgendaStageA/AdmissionDepartureMariaDbTest.php
tests/Integration/AgendaStageA/AgendaAppointmentWriteMariaDbTest.php
tests/Integration/AgendaStageA/AgendaBoardMariaDbTest.php
tests/Integration/AgendaStageA/AgendaCreatorPrivacyMariaDbTest.php
tests/Integration/AgendaStageA/AgendaDoubleDurationMariaDbTest.php
tests/Integration/AgendaStageA/AgendaSemaphorizationMariaDbTest.php
tests/Integration/AgendaStageA/AppointmentDiscountRegistrationMariaDbTest.php
tests/Integration/AgendaStageA/LegacyRegistrationParityMariaDbTest.php
tests/Integration/AgendaStageA/RegularReservationExclusivityMariaDbTest.php
tests/JavaScript/agenda-creator-privacy.test.js
tests/JavaScript/agenda-details-workspace.test.js
tests/JavaScript/agenda-guidance.test.js
tests/JavaScript/agenda-quote.test.js
tests/Support/IsolatedAgendaStageAQa.php
tests/Support/agenda-stage-a-readback.php
tests/Support/agenda-stage-a-router.php
tests/Support/agenda-stage-a-seed.php
tests/Support/prepare-agenda-stage-a.php
tools/qa/browser-agenda-stage-a.cjs
```

## Publicación y trazabilidad

La conexión Git HTTPS falló por timeout en dos intentos (lectura/publicación). Si se publica mediante el conector GitHub, el SHA puede diferir por autoría/fecha de commit generada por API. Se debe verificar **igualdad exacta del árbol Git completo** con el cierre local; la evidencia conserva el SHA y árbol del código probado. Cualquier diferencia de archivos invalida esa equivalencia y exige repetir las pruebas afectadas. No usar force push ni modificar main. El SHA candidato y enlace definitivos se informan en el PR y en la entrega.

## Ampliación autorizada y correcciones de esta iteración

Las respuestas de negocio sustituyen el estado PARCIAL de la primera revisión: fucsia/plomo solo chips, prioridad base completa y salida física explícita después de ATENDIDO. No se amplían reglas financieras R25–R30. Se añadió `AdmissionDepartureTest`, equivalente MariaDB, y escenarios de navegador de salida/competencia entre operadores. Se probó además que el rollback de esquema rehúse borrar salidas registradas.

El primer recorrido nuevo falló en un selector de fixture de Semana (nombre supuesto distinto del paciente realmente sembrado). Se corrigió el selector sin reducir validaciones. La inspección adicional detectó un defecto visual real: las etiquetas largas dejaban el nombre con ancho cero; se compactaron HERIDA/POSTOP y se verificaron sus rectángulos reales y la presencia del nombre. Resultados previos a esta corrección no se usan como validación final de interfaz.
