# Arquitectura y flujos

## Registro/pagos

Se reutilizan OperationalRegistrationService, ReservationPaymentService, CreateAppointmentService, AppointmentSlotValidator, RegularReservationAvailability, AppointmentTicketService, VoucherPaymentRecorder y AppointmentPaymentProofs. El cambio de condición de confirmación ocurre **dentro** de la transacción financiera existente, con el mismo bloqueo por médico y operación idempotente. El caso fuera de horario revalida fuera de horario, el regular revalida ocupación y el adicional conserva su capacidad explícita.

La idempotencia sigue siendo `(actor_user_id, request_key)` con payload_hash. Reutilizar una clave con otro contenido produce 409. Repetir la misma operación no registra dinero nuevamente. Las excepciones de conflicto deshacen las escrituras y la limpieza elimina exclusivamente los archivos privados del intento fallido. No se concilian ni confirman masivamente pagos históricos.

## Solicitudes y cola

OperationalProcedureService trabaja con tres tablas nuevas y el ledger appointment_operations ya existente:

- appointment_operational_reviews: una cabecera por cita, version, requested_count, completed_count, status, origin, ready_at, usuario y motivo de decisión.
- appointment_requested_procedures: una fila por cita/clave, selected, solicitante/fecha y verificador/fecha. Retirar una solicitud desactiva la fila; no elimina historia.
- appointment_procedure_events: actor, UUID, acción, antes/después completos y hora.

Orden de escritura: autorizar acción → comprobar visibilidad → bloquear operación UUID → bloquear cita visible → comprobar version → modificar selección/realización → recalcular cola → incrementar versión → auditar → finalizar operación. Las transacciones reintentan deadlocks hasta tres veces. El índice único de cita evita colas duplicadas y el de cita/procedimiento impide solicitudes duplicadas.

| Condición | Resultado operativo |
|---|---|
| Sin solicitados o sin realizados | NOT_READY automático; envío manual rechazado |
| Algunos realizados, otros pendientes | NOT_READY hasta envío autorizado |
| Todos realizados, al menos uno, cita activa hoy | READY / AUTOMATIC |
| Envío explícito con realizados y pendientes | READY / MANUAL; motivo obligatorio |
| Decisión manual y se retira último realizado | SUSPENDED / MANUAL; no se borra decisión |
| Retirada explícita con motivo | WITHDRAWN; no resucita por polling ni nueva realización |
| Cancelación, retiro, inasistencia, salida o fuera de jornada | Proyección INACTIVE, sin alterar historia de la cola |

La cabecera guarda estado operativo; `estado_cita`, `estado_agenda`, horarios, importes e indicaciones clínicas no se escriben en este servicio. Los contadores permiten actualizar chips de las agendas sin cargar narrativas ni crear consultas por procedimiento en el feed operativo. La cola limita su salida a información administrativa y omite motivo libre de la decisión.

## Endpoints autenticados y CSRF

Todos están dentro del grupo feature flag + auth + appointment.mvp.access + appointment.view:

| Método/ruta bajo /scheduling-mvp/admission | Operación |
|---|---|
| GET /reevaluations | Cola visible de hoy en Lima |
| POST /appointments/{id}/procedures/selection | Confirmar conjunto completo |
| POST /appointments/{id}/procedures/completion | Cambiar una realización |
| POST /appointments/{id}/procedures/send | Decisión manual confirmada |
| POST /appointments/{id}/procedures/withdraw | Retirada confirmada y motivada |
| POST /appointments/{id}/care-reversal | Reversión auditada |

Los POST de procedimientos requieren request_key UUID y expected_version. Selection acepta únicamente procedures del catálogo. Completion acepta procedure y completed; no puede introducir solicitudes. Send/withdraw requieren confirmed y validan reason. Se rechazan campos extra (422), roles no autorizados (403), citas o procedimientos no visibles (404) y versiones obsoletas (409). El backend repite las comprobaciones de rol/capacidad; no confía en los checkboxes.

## Reversión

AdmissionCareService registra previous_timestamps y operation_id junto con cada transición nueva. reversalOffer solo se ofrece si el último evento de la cita es una transición de Admisión reversible con snapshot y el estado sigue coincidiendo. Cualquier evento posterior, incluido un cambio que regrese al mismo estado, invalida la oferta. La reversión mantiene bloqueos de médico y cita, usa appointment_operations y agrega ADMISION_ESTADO_REVERTIDO, con motivo, evento corregido y timestamps preservados. No interpreta eventos históricos incompletos ni reabre estados finales.

## Lecturas y presentación

Ambos feeds cargan la relación operationalReview para usar el mismo presentador. La cola filtra fecha_cita del día America/Lima y ordena ready_at, review.id. ready_at conserva su instante original mientras una decisión siga lista; volver a quedar listo después de suspender/inactivar automáticamente asigna un nuevo instante. No se ordena por la hora de cita.

Agenda operativa usa agenda-refresh.js, con temporizador al finalizar, exclusión de solicitudes superpuestas, abort/secuencia y snapshot de filtros. Solo se reemplazan proyecciones del calendario; se preservan contexto y scroll. Una selección nueva, cambios sin guardar, controles enfocados y modales aplazan el refresco. Admisión conserva su patrón de revisiones y añade protección de borradores incluso después de perder foco. La ventana de reevaluaciones tiene polling independiente de lectura y no toca formularios. Una respuesta de sesión vencida nunca recarga el documento ni descarta datos.

## Prioridad visual

DESACTIVADA → REEMBOLSO_SOLICITADO → SALIDA_REGISTRADA → LISTO_REEVALUACION (si no existe incertidumbre financiera) → PRESENTE → PAGO_REAL → reserva/sin pago. La incertidumbre conserva la advertencia CONCILIAR_PAGO y evita el fondo naranja. Cuando un reembolso tiene prioridad, la disponibilidad para reevaluación permanece como chip textual. NO_ASISTIO y las citas fuera de jornada no esperan activamente. Ninguna regla visual escribe estados.

## Sincronización del selector Profesional / Servicio

`public/js/admin/master/doctor-service/doctor-service.js` recalcula las opciones desde `data-specialties`, aplica la excepción explícita y limpia únicamente valores incompatibles. A continuación refresca la caché visual de Bootstrap Select, sin volver a asignar el valor seleccionado. Carga inicial, cambio de profesional, cambio de excepción y `shown.bs.modal` comparten el mismo cálculo. La validación autoritativa continúa en CatalogManagement, independiente del estado del desplegable.
