# Entrega Urgente 1 — controles heredados

Base verificada: `ccd36c7536dddda4bb9ba5d1e6ff58a3b0dec283` (main / PR #4).
Rama: `codex/entrega-urgente-1-controles-heredados`.
Fecha de verificación: 12 de octubre de 2026.

## Problema y solución

Admisión podía crear/editar citas y sus importes directamente desde campos del navegador. Sus entradas ahora adaptan los formularios existentes a los servicios operativos: permisos Spatie, catálogo activo, disponibilidad, reserva privada, adelanto real mínimo del 50%, confirmación explícita, comprobante y bloqueo transaccional.

Una reserva no confirma ni ocupa un intervalo. Registrar un adelanto conserva la reserva hasta solicitar confirmación. El precio se calcula en servidor; un precio o saldo de referencia alterado produce 422 y revierte toda la operación. Reintentos con la misma UUID reutilizan el resultado; cambiar su contenido produce 409. Las operaciones bancarias duplicadas se rechazan también entre entradas.

Se conservan reconsultas y tarifas especiales por la decisión expresa del usuario. El cálculo compartido usa la última cita ATENDIDO del servicio canónico, plazo de reconsulta configurado al momento del registro y tarifa fija/porcentual activa vigente para la fecha elegida. No recalcula citas existentes.

## Compatibilidad y consumidores

Se mantienen las rutas de registro/estado de Admisión y edición del calendario; los consumidores son los formularios de citas, modal desde pacientes y calendario disponible. Se mantienen campos y respuestas `code/msg`, añadiendo UUID y elección RESERVE/CONFIRM (reserva por defecto). Peticiones antiguas sin clave siguen pudiendo reservar sin dinero; recibir dinero o confirmar requiere una UUID reutilizable. Errores financieros ahora utilizan 422 y conflictos 409.

El calendario distingue ID de servicio de ID de asignación médico-servicio. Se corrigieron selección de médico/servicio, estado visible del selector y retorno de Comercial. Las vistas que comparten esos scripts versionan sus recursos para evitar archivos obsoletos en caché.

La edición mantiene importes y precio; un cambio de horario requiere permiso de reprogramación, validación de disponibilidad y comprobación de fecha/hora esperadas. Cambiar médico o servicio devuelve 422 y conserva la cita original: necesita una transición económica definida antes de habilitarlo. No se eliminó ninguna ruta.

## Validación ejecutada

- PHP completo: 626 casos: 625 aprobados, 1 omitido, 3721 aserciones, 0 fallidos.
- JavaScript: 200 aprobadas, 0 fallidas/omitidas.
- MariaDB aislada 10.4.32, puerto 33318: 122 aprobadas, 957 aserciones, 0 fallidas.
- Navegador con datos ficticios: reserva desde Admisión y Agenda, adelanto separado de confirmación, confirmación posterior con S/50 sobre catálogo S/100, rechazo visible de confirmación impaga, formularios y vistas Día/Semana/Mes. El control posterior registró un solo pago S/50 y la cita histórica ficticia exactamente intacta.
- Pruebas HTTP automatizadas: paridad financiera/permisos en ambas entradas, precios/reconsultas/tarifas, referencias manipuladas, exoneración autorizada, privacidad, mutación histórica, doble duración ya existente y concurrencia real de peticiones (misma UUID, mismo horario y misma operación bancaria).
- Las primeras ejecuciones finales fallaron por fixtures antiguos que enviaban precio 1 al probar umbral/idempotencia. Se ajustaron a catálogo 100; los casos independientes que exigen rechazo de manipulación permanecen.
- `git diff --check` aprobado.

Evidencia: [logs y capturas](evidence/urgent1/). [Control posterior del navegador](evidence/urgent1/browser-check.json).
Los tests destructivos comprueban entorno testing, host, puerto, nombre, usuario y datadir propios antes de migrar. No se usaron pacientes reales.

## Límites y decisiones para revisión

- No hay migraciones nuevas, backfill ni cambios masivos de estados/importes históricos.
- Un rollback nativo DROP COLUMN quedó omitido por SQLite anterior a 3.35; no es una prueba nueva.
- No se ejecutó QA en MariaDB 11.8.9 ni Hostinger. No se hizo merge, despliegue, migraciones ni cambios de permisos productivos.
- Validar la transición propuesta para cambio de médico/servicio en edición: conservar cita y exigir revisión económica; no se implementan créditos/devoluciones.
- Revisar la dotación existente de VIEW/CREATE/UPDATE/RESCHEDULE/SUBMIT_PAYMENT y permisos de exoneración de los perfiles. No se concede acceso por rol automáticamente.
- Los clientes antiguos que confirman/pagan sin UUID o usan cotizaciones obsoletas deben actualizar su formulario y repetir la cotización.
- Permanecen fuera de esta entrega los nuevos descuentos, referidos, postoperatorios, panel, OCR, heatmap y demás reglas aplazadas.

## Archivos y motivo

| Grupo | Motivo |
|---|---|
| Controladores y request | Adaptar entradas, cotizar y conservar contratos JSON con validación del servidor. |
| Servicios Scheduling | Precio común, permisos, disponibilidad, idempotencia y preservación económica al editar. |
| Rutas | Añadir cotización operativa protegida; conservar rutas heredadas. |
| Scripts y vistas | UUID, intención explícita, tarifa/cotización, IDs correctos y recursos versionados. |
| Tests y bootstrap QA | Paridad, concurrencia, quote async y aislamiento estricto; fixtures ajustados al contrato seguro. |
| Evidencia | Resultados ejecutados y capturas únicamente con datos ficticios. |

Listado exacto de código/pruebas modificados o añadidos:

- `app/Http/Controllers/Api/appointment/AppointmentController.php`
- `app/Http/Controllers/Scheduling/AgendaBoardController.php`
- `app/Http/Controllers/Scheduling/OperationalRegistrationController.php`
- `app/Http/Controllers/admissionist/appointment/AppointmentController.php`
- `app/Http/Controllers/admissionist/schedule/ScheduleController.php`
- `app/Http/Requests/Scheduling/OperationalRegistrationRequest.php`
- `app/Services/Scheduling/CreateAppointmentService.php`
- `app/Services/Scheduling/OperationalRegistrationService.php`
- `app/Services/Scheduling/ReservationPaymentService.php`
- `phpunit.agenda-mariadb.xml`
- `public/js/admissionist/appointment/appointment.js`
- `public/js/admissionist/calendario/calendario.js`
- `public/js/scheduling/agenda-operational-workspace.js`
- `public/js/scheduling/agenda.js`
- `resources/views/admin/appointment/index.blade.php`
- `resources/views/admin/available-schedule/index.blade.php`
- `resources/views/admin/patient/index.blade.php`
- `resources/views/admissionist/appointment/crud/create.blade.php`
- `resources/views/admissionist/appointment/crud/edit.blade.php`
- `resources/views/admissionist/appointment/crud/update.blade.php`
- `resources/views/admissionist/appointment/index.blade.php`
- `resources/views/admissionist/available-schedule/index.blade.php`
- `resources/views/admissionist/patient/index.blade.php`
- `resources/views/admissionist/patient/modal/appointment.blade.php`
- `resources/views/receptionist/appointment/index.blade.php`
- `resources/views/receptionist/available-schedule/index.blade.php`
- `resources/views/receptionist/patient/index.blade.php`
- `resources/views/scheduling/agenda/partials/operational-registration.blade.php`
- `routes/scheduling.php`
- `tests/Feature/Baseline/PatientAndAppointmentSmokeTest.php`
- `tests/Feature/Scheduling/AgendaAppointmentWriteTest.php`
- `tests/Feature/Scheduling/AgendaStabilizationTest.php`
- `tests/Feature/Scheduling/AppointmentEndingTest.php`
- `tests/Feature/Scheduling/AppointmentReaderPrivacyTest.php`
- `tests/Feature/Scheduling/OperationalRegistrationTest.php`
- `tests/Integration/AgendaConcurrencyTest.php`
- `tests/Support/IsolatedAgendaQa.php`
- `app/Services/Scheduling/AppointmentPriceResolver.php`
- `app/Services/Scheduling/LegacyAppointmentMutation.php`
- `app/Services/Scheduling/LegacyAppointmentRegistration.php`
- `tests/Feature/Scheduling/LegacyRegistrationParityTest.php`
- `tests/Integration/LegacyRegistrationMariaDbTest.php`
- `tests/JavaScript/agenda-quote.test.js`
- `tests/Support/urgent-browser-check.php`
