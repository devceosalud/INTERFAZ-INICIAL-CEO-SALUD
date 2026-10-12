# Entrega Urgente 1 — controles heredados

Base verificada: `ccd36c7536dddda4bb9ba5d1e6ff58a3b0dec283` (main / PR #4).
Rama: `codex/entrega-urgente-1-controles-heredados`.
Fecha de verificación: 12 de octubre de 2026.

## Problema y solución

Admisión podía crear/editar citas y sus importes directamente desde campos del navegador. Sus entradas ahora adaptan los formularios existentes a los servicios operativos: permisos Spatie, catálogo activo, disponibilidad, reserva privada, adelanto real mínimo del 50%, confirmación explícita, comprobante y bloqueo transaccional.

Una reserva no confirma ni ocupa un intervalo. Registrar un adelanto conserva la reserva hasta solicitar confirmación. El precio se calcula en servidor; un precio o saldo de referencia alterado produce 422 y revierte toda la operación. Reintentos con la misma UUID reutilizan el resultado; cambiar su contenido produce 409. Las operaciones bancarias duplicadas se rechazan también entre entradas.

Se conservan reconsultas y tarifas especiales por la decisión expresa del usuario. El cálculo compartido usa la última cita ATENDIDO del servicio canónico con estado de agenda LEGADO o CONFIRMADA, uniforme entre trabajadores, plazo de reconsulta configurado al momento del registro y tarifa fija/porcentual activa vigente para la fecha elegida. No recalcula citas existentes.

## Compatibilidad y consumidores

Se mantienen las rutas de registro/estado de Admisión y edición del calendario; los consumidores son los formularios de citas, modal desde pacientes y calendario disponible. Se mantienen campos y respuestas `code/msg`, añadiendo UUID y elección RESERVE/CONFIRM (reserva por defecto). Peticiones antiguas sin clave siguen pudiendo reservar sin dinero; recibir dinero o confirmar requiere una UUID reutilizable. Errores financieros ahora utilizan 422 y conflictos 409.

El calendario distingue ID de servicio de ID de asignación médico-servicio. Se corrigieron selección de médico/servicio, estado visible del selector y retorno de Comercial. Las vistas que comparten esos scripts versionan sus recursos para evitar archivos obsoletos en caché.

La edición mantiene importes y precio; un cambio de horario requiere permiso de reprogramación, validación de disponibilidad y comprobación de fecha/hora esperadas. Cambiar médico o servicio devuelve 422 y conserva la cita original: necesita una transición económica definida antes de habilitarlo. No se eliminó ninguna ruta.

## Validación ejecutada

- PHP completo: 643 casos: 642 aprobados, 1 omitido, 3909 aserciones, 0 fallidos.
- JavaScript: 205 aprobadas, 0 fallidas/omitidas.
- MariaDB aislada 10.4.32, puerto 33318: 139 aprobadas, 1144 aserciones, 0 fallidas.
- Navegador con datos ficticios: reserva desde Admisión y Agenda, adelanto separado de confirmación, confirmación posterior con S/50 sobre catálogo S/100, rechazo visible de confirmación impaga, formularios y vistas Día/Semana/Mes. El control posterior registró un solo pago S/50 y la cita histórica ficticia exactamente intacta.
- Pruebas HTTP automatizadas: paridad financiera/permisos en ambas entradas, precios/reconsultas/tarifas, referencias manipuladas, exoneración autorizada, privacidad, mutación histórica, doble duración ya existente y concurrencia real de peticiones (misma UUID, mismo horario y misma operación bancaria).
- Las primeras ejecuciones finales fallaron por fixtures antiguos que enviaban precio 1 al probar umbral/idempotencia. Se ajustaron a catálogo 100; los casos independientes que exigen rechazo de manipulación permanecen.
- `git diff --check` aprobado.

Evidencia actual de la revisión: [logs actuales](evidence/urgent1/authorized/). Las capturas y logs de revisiones anteriores se conservan en sus directorios originales. [Control posterior del navegador](evidence/urgent1/browser-check.json).
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

## Revisión del PR #5 y correcciones posteriores autorizadas

| Problema y causa | Corrección y archivos | Prueba de regresión |
|---|---|---|
| paintIdentity cotizaba antes de cambiar el ID en el formulario. | agenda.js actualiza primero; agenda-operational-workspace.js recibe explícitamente el nuevo ID. Conserva invalidación por versión de respuestas y errores anteriores. | agenda-quote.test.js ejecuta el pintor real y workspace async con dos pacientes de S/100 y S/50; cambios de médico, servicio, fecha y tarifa. La copia de b634197 falla (8/9); la corrección pasa (9/9). |
| Middleware por rol bloqueaba a ADMINISTRADOR incluso con permisos. | routes/admision.php incorpora ADMINISTRADOR únicamente en POST appointment/store, appointment/update y schedule/update. Los servicios siguen exigiendo VIEW/CREATE/UPDATE/RESCHEDULE y permisos financieros efectivos. | LegacyRegistrationParityTest: administrador autorizado registra/confirma/edita; sin SUBMIT_PAYMENT o aprobación de exoneración recibe 403; administrador/trabajador sin permisos de escritura recibe 403 y conserva datos. |
| La cotización consultaba antecedentes sin aplicar privacidad; API heredada solo verificaba el rol. | AppointmentPriceResolver requiere actor y usa visibleToAgendaUser; ambos controladores de cotización y CreateAppointmentService usan el mismo actor. API exige VIEW. | LegacyRegistrationParityTest en SQLite y MariaDB: historia privada inconsistente no cambia tipo/precio para ningún trabajador, incluido el dueño; lector sin VIEW recibe 403; búsqueda de datos básicos y nuevo registro siguen funcionando. AppointmentReaderPrivacyTest conserva igualdad para historia LEGADO/CONFIRMADA y excluye historia privada ajena. |

Las identidades de pacientes son compartidas por la política existente. No se deniega la búsqueda legítima de un paciente por pertenecer a otro usuario; se excluyen del cálculo para todos los trabajadores los registros inconsistentes ATENDIDO + PENDIENTE_CONFIRMACION, incluso para el propietario. Los registros LEGADO/CONFIRMADA siguen siendo visibles conforme a la política existente, sin excepciones por administrador.

QA de navegador repetido: primera consulta S/100; cambio a reconsulta S/50; tarifa especial +S/30 produce S/80; volver al paciente inicial produce S/130 y restablecer tarifa estándar S/100. Reserva sin dinero, adelanto separado S/50 y confirmación explícita; vistas Día/Semana/Mes sin errores de consola. Control posterior: dos fixtures históricos intactos, una cita nueva confirmada y un único pago S/50. Registro/edición heredados, exoneración, idempotencia y concurrencia se verificaron mediante suites HTTP automatizadas.

La primera suite completa de esta revisión detectó una expectativa antigua que exigía divulgar reconsulta privada a otro lector. Se corrigió para verificar privacidad y conservar igualdad en antecedentes compartidos. Resultado final: 0 fallos; una omisión SQLite ya documentada. Sin pruebas en Hostinger/MariaDB 11.8.9.

Archivos adicionales de esta revisión:
- routes/admision.php: retirar bloqueo de rol de las tres entradas de citas.
- tests/Support/urgent-review-browser-seed.php: fixtures descartables para primera consulta/reconsulta.
- tests/Support/urgent-review-browser-check.php: control de integridad histórica y pago único después del navegador.
- docs/evidence/urgent1/review/: logs reales, reproducción previa y capturas actuales.

Los demás archivos corregidos y sus motivos se detallan en la matriz y en el inventario anterior. Otras rutas de pacientes, responsables y horarios médicos mantienen su middleware heredado; no se amplió esta revisión a cambios generales de permisos. Permanecen pendientes la transición económica al cambiar médico/servicio y el QA autorizado en el motor de Hostinger.

## Cierre de las dos condiciones de código autorizadas tras el preflight

1. AppointmentPriceResolver filtra ATENDIDO y estado_agenda LEGADO/CONFIRMADA antes de elegir el antecedente más reciente. Se conserva la ventana/precio de reconsulta y tarifas existentes. ATENDIDO + PENDIENTE_CONFIRMACION no otorga reconsulta automática ni al dueño ni a otros trabajadores; sus filas, precios y estados permanecen intactos. Si existe un antecedente válido anterior, sigue siendo elegible. Las pruebas cubren cotización y nuevo registro de precios iguales en entradas operativa/heredada para ADMISION, COMERCIAL y ADMINISTRADOR.
2. PatientWriteAccess incluye ADMINISTRADOR en la política existente de fichas. Los endpoints y controles reutilizados de Agenda/Pacientes habilitan alta, ficha completa y contacto. Se conservan validaciones de documento único, DNI nuevo válido, teléfono principal requerido al registrar desde Agenda, pasaporte sin restricción DNI y demás validaciones heredadas. Las pruebas conservan HCE/creador, eventos REGISTRO_OPERATIVO/CONTACTO_PACIENTE_ACTUALIZADO, visibilidad de citas privadas y rechazos indistinguibles con APP_DEBUG=false. No se concede CREATE, SUBMIT_PAYMENT ni APPROVE_ZERO_COST por rol: citas/dinero conservan sus controles adicionales y rollback de paciente/cita/pago.

Existencia/revisión de inconsistencias: los fixtures ficticios demuestran el estado ATENDIDO + PENDIENTE_CONFIRMACION y se comparan antes/después sin cambios. Su cantidad productiva permanece PENDIENTE. Una revisión posterior autorizada puede contar por estado con SELECT; no se eliminan, reclasifican ni reprician registros automáticamente.

| Archivos de este cierre | Motivo |
|---|---|
| app/Services/Scheduling/AppointmentPriceResolver.php | Elegibilidad uniforme y exclusión de antecedentes inconsistentes. |
| app/Support/Patients/PatientWriteAccess.php | ADMINISTRADOR escribe fichas sin obtener permisos financieros. |
| resources/views/patients/operational.blade.php; resources/views/scheduling/agenda/partials/patient-modal.blade.php | Mensajes de acceso coherentes con la política aprobada. |
| tests/Feature/Scheduling/LegacyRegistrationParityTest.php | 47 casos/399 aserciones: precio uniforme, registro, ficha ADMIN, controles financieros, auditoría y privacidad; también ejecutados en MariaDB por la clase de integración existente. |
| tests/Feature/Scheduling/AppointmentReaderPrivacyTest.php | Inconsistencia privada ignorada para dueño y otro lector; historia compartida conserva reconsulta. |
| tests/Feature/Patients/OperationalPatientModuleTest.php; tests/Feature/Scheduling/AgendaBoardTest.php; tests/Feature/Scheduling/AgendaDetailsTest.php | Adoptar ADMIN escritor en formularios/endpoints; conservar rechazo de lectores sin rol y contexto privado ajeno. |
| docs/DECISIONES_NEGOCIO_PENDIENTES.md | Registrar las dos decisiones aprobadas y cancelación de mapa de calor como entrega posterior. |
| docs/evidence/urgent1/authorized/ | Resultados finales reales de PHP, JavaScript y MariaDB. |

Resultado final ejecutado: PHP 643 casos (642 aprobados, 1 omitido por SQLite <3.35), 3909 aserciones, 0 fallidos; JavaScript 205 aprobados, 0 fallidos; MariaDB aislada 10.4.32: 139 aprobados, 1144 aserciones, 0 fallidos. La primera corrida completa detectó una prueba que aún esperaba ADMIN como lector de contactos; se actualizó el caso negativo a un lector sin rol de escritura y se repitió PHP completo. No se repitió QA manual de navegador en este cierre; la habilitación de los formularios y las operaciones se verificó por PHP/HTTP, y JavaScript completo se ejecutó. Los datos ficticios de la instancia local se restablecieron tras finalizar MariaDB para permitir pruebas locales.

Las dos condiciones de código del preflight quedan atendidas. Siguen pendientes los permisos efectivos productivos, PHP/CLI-FPM/vendor de Hostinger, QA equivalente MariaDB 11.8.9, schema/índices reales, SHA instalado, autodespliegue, backup/restauración y rollback ensayado. No hay migraciones/seeders nuevos ni cambios productivos. No se autoriza merge ni despliegue por estos resultados locales.

Mapa de calor: decisión de producto documentada. No se retiran enlaces, vistas, rutas, eventos, JavaScript ni tests en este PR; migraciones y datos se conservan. Microsoft Clarity ya está instalado por decisión de negocio en main (1a2c1895); PR #5 debe conservar sin cambios resources/views/layouts/app.blade.php al integrar main. La retirada del mapa interno se hará en un PR independiente. La revisión de privacidad y captura de sesiones continúa pendiente.
