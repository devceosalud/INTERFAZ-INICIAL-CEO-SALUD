# Agenda: doble duración y cambios pendientes (R09, R10, R24)

Rama codex/mvp-agenda-duracion-contexto, base codex/mvp-reevaluaciones-indicadas (PR #7). Se revisa después de #5 → #6 → #7. No se ha desplegado, ejecutado migraciones productivas ni cambiado Clarity.

## Cambio y compatibilidad

- Agenda envía cita_doble explícita, apagada por defecto. El servidor calcula dos intervalos reales consecutivos del mismo bloque médico y sede; rechaza huecos, fin de bloque, cadencia distinta, duración manipulada y segundo intervalo ocupado al confirmar. Una consulta y un TICKET, nunca dos cobros.
- Ambas entradas conservan reserva privada, permisos, precios centralizados, transacciones, idempotencia y confirmación con el 50 %. Dos trabajadores compiten bajo el bloqueo del médico. Probado también el rechazo de una sede ajena y una cita confirmada que pasa de intervalos de30 a20 minutos en el fin del bloque.
- duration_slots nullable solo se registra en citas nuevas. Los históricos no se reinterpretan ni actualizan. Reprogramar una nueva cita doble conserva dos intervalos según la cadencia real del destino y el contrato económico de #7.
- El editor heredado muestra la doble duración persistida y no permite cambiarla al editar. El popover heredado escapa texto y mantiene el saneamiento HTML; nombres/notas no deben convertirse en HTML ejecutable.
- Al cambiar selección, fecha, vista o filtros, los cambios pendientes requieren confirmación de descarte. Cancelar conserva el contexto. Se limpian notas, contactos, importe, banco, operación, descuentos, indicación, adjuntos y referido del contexto anterior.
- Respuestas tardías de detalles, documentos y pacientes no hidratan otra cita. El refresco financiero conserva otros cambios todavía sin guardar. Abrir una ficha sin modificarla no produce falso aviso. Mientras se guarda, el formulario operativo queda inerte.

## Archivos y propósito

Motor: DoubleAppointmentDuration, OperationalRegistrationService/Request/Controller, CreateAppointmentService y RescheduleAppointmentService.
Persistencia: Appointment y migración 2026_10_13_200000_add_appointment_duration_slots.
Proyección: AppointmentEconomicPosition (heredada de #7 con duration_slots), ScheduleController heredado.
Interfaz: agenda.js, agenda-operational-workspace.js, partial operational-registration, calendario.js heredado y partial edit.
Pruebas: AgendaDoubleDurationTest, AgendaDoubleDurationMariaDbTest, AgendaConcurrencyTest, agenda-quote.test.js, agenda-appointment-actions.test.js y legacy-calendar-safety.test.js.
QA: IsolatedAgendaQa separa URL/cookie del runtime nuevo en 8023/33320. No modifica los servidores/bases anteriores.
Matriz: CEO_SALUD_MATRIZ_REGLAS.md/json.

## Pruebas de navegador ejecutadas

Instancia temporal loopback 8023, PHP 8.3.35 y MariaDB 11.8.9 descartable 33320. Usuario, médico y pacientes exclusivamente ficticios. Sin proveedores externos de notificaciones o pagos.

1. Precio S/100 → descuento fijo S/20 → neto S/80; confirmación bloqueada con adelanto S/30 y requisito visible S/40.
2. Cambio de horario: formulario anterior limpiado, precio restablecido y doble duración desactivada.
3. Reserva doble 09:00–10:00, sin dinero ni ocupación; pago separado S/50; posterior confirmación.
4. Comprobación SELECT del fixture: CONFIRMADA, duración60, duration_slots2, un pago de S/50, un TICKET de S/100; histórico original exactamente intacto.
5. Día muestra continuación 09:30; Semana carga cita; Mes carga resumen. Sin errores de consola observados en este recorrido.
6. Modal heredado abre la cita doble con checkbox marcado, deshabilitado y leyenda de conservación.

PENDIENTE: cancelación del diálogo nativo en navegador (la automatización fue interrumpida por el diálogo), ficha modal completa, alta/edición heredada end-to-end en navegador para todos los perfiles. Cancelación, ficha sin cambios, preservación del mismo contexto y reset sí tienen pruebas JavaScript. Los POST heredados y permisos se ejecutan en PHP/MariaDB. No se declara aprobado lo pendiente.

## Publicación y reversión (solo con autorización)

1. Revisar y autorizar PRs en orden #5, #6, #7 y esta rama; verificar autodespliegue antes de fusionar cualquiera.
2. Backup actualizado de archivos, .env protegido, BD y storage privado; restauración comprobada en entorno aislado.
3. Aplicar migraciones aditivas aprobadas en mantenimiento; no backfill ni permisos automáticos. La migración de duración rechaza rollback destructivo.
4. Limpiar caches con el procedimiento aprobado, verificar SHA/Clarity intacto y repetir smoke ficticio en staging equivalente antes de datos reales.
5. Ante error: detener nuevas operaciones, volver al artefacto previo aprobado manteniendo esquema/BD/adjuntos. El rollback completo previo a #7 debe conservar sus restricciones de documentos médicos y bloquear creación/reprecio hasta corregir. No restaurar una BD antigua sobre pagos/citas posteriores.
6. Reconcilación por IDs de operaciones y conteos; conservar duration_slots y auditoría aunque temporalmente se vuelva al código anterior.

No listo para uso productivo integral: privacidad de Clarity, backup/restauración, permisos/guards efectivos aprobados y catálogo de Magdalena siguen pendientes. R05 también conserva bloqueo seguro de reprogramaciones pagadas cuyo precio cambia y reasignación de indicación cancelada hasta sus flujos explícitos.

## Resultados automatizados finales — 13/10/2026

|Suite ejecutada|Resultado real|
|---|---|
|PHP 8.3.35, suite completa SQLite aislada|773 pruebas / 5931 aserciones, OK|
|PHP 8.3.35 + MariaDB 11.8.9 descartable, phpunit.rules.xml|260 pruebas / 3077 aserciones, OK|
|JavaScript, node --test tests/JavaScript/*.test.js|222 pruebas, 222 aprobadas, 0 fallidas|
|Doble duración, casos focalizados PHP|11 pruebas / 80 aserciones, OK|

Las suites completas incluyen la corrección final de cadencia en cita confirmada y ambas sedes/entradas. La suite JS incluye cancelación/descarte, formulario limpio, misma cita con notas pendientes y búsquedas tardías por documento/horario. Las últimas guardas de búsqueda y reprogramación pendiente tienen regresión JS; no se les atribuye un smoke adicional de navegador.

Una ejecución JS inicial falló por la invocación incorrecta del directorio (Node requiere los archivos). La ejecución siguiente detectó un contrato antiguo que prohibía cualquier window.confirm en Agenda; se acotó a la confirmación de reprogramación, que sigue usando la confirmación ERP y conserva sus pruebas de cancelación sin PATCH. Las 222 pruebas finales pasaron; no se ocultan las verificaciones de navegador pendientes.
