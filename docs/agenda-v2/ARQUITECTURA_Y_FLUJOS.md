# Arquitectura y flujos

Estado vigente de la continuación: [entrega exclusivamente local del20/10/2026](CONTINUACION_20261020.md). PR46 cerrado y sin fusionar; no publicar ni reabrir sin autorización. Los resultados anteriores son antecedentes.

Estado vigente: [cuarta ronda, gestión de citas, procedimientos personalizados y teleconsulta](RONDA_4_DIAGNOSTICO_Y_GUIA.md). Los resultados y decisiones anteriores se conservan como antecedentes.

## Arquitectura integrada con los hotfixes

`AppointmentReevaluationService` une en lectura el ledger directo v1 y el ledger de procedimientos, con tres orígenes identificables y una sola fila por cita. `AgendaAppointmentPresentation` usa esa unión y añade ATENDIDO al vocabulario compartido. Los escritores permanecen separados para conservar permisos, transacciones y datos históricos. Desactivar un origen no escribe en el otro.

La auditoría unificada pagina ambos ledgers con una referencia de máximos de ID, preserva igualdad de timestamps, serializa ISO y muestra Lima. El nuevo GET privado aplica la misma visibilidad y acceso que Admisión. No hay backfill ni tabla agregada. [Detalles y resolución](INTEGRACION_MAIN_Y_HOTFIXES.md).

## Cambios de arquitectura de la segunda ronda

`OperationalProcedureService::status()` es la proyección compartida: READY automático exige origen AUTOMATIC, solicitados > 0 y realizados = solicitados; READY manual exige origen MANUAL, autor de decisión y al menos un realizado. WITHDRAWN conserva su decisión y las citas fuera de jornada/inactivas no esperan. El recálculo transaccional de selección/realización permanece como autoridad de escritura; la defensa de lectura no crea decisiones ni corrige BD silenciosamente. `AppointmentOperationalReview` convierte los contadores a enteros y expone la relación del autor.

El detalle devuelve contadores reales de los procedimientos seleccionados, pendientes, estado efectivo y `authorized_by` para decisiones manuales vigentes/suspendidas. El último actor de decisión se distingue del autorizador: retirar una reevaluación no atribuye el envío al usuario que la retiró. La historia íntegra sigue en `appointment_procedure_events`.

El endpoint de Nicol devuelve `rows` exclusivamente listas y `groups.active/pending/history` para distinguir colas. Pendientes incluye citas con solicitudes confirmadas que aún nunca estuvieron listas. El mismo estado efectivo alimenta Admisión y `AgendaAppointmentPresentation`; no se modifica el estado clínico, la hora ni el dinero.

`OperationalText` normaliza espacios Unicode, caracteres de ancho cero y BOM. Motivo requerido y máximo 2000 caracteres tanto en FormRequest como en servicio, antes de cualquier escritura. El mismo textarea se mueve entre el registro principal y el modal, evitando duplicados. Pagos de citas existentes, procedimientos y reversión no exigen un motivo nuevo. Observaciones significativas de 1 a 16000 caracteres; el máximo protege la columna TEXT incluso con UTF-8 de cuatro bytes.

Admisión usa cabecera e indicadores compactos, filtros frecuentes y «Más filtros», resumen económico superior, listado y detalle con scroll interno en escritorio. Móvil alterna listado/detalle con «Volver a la lista». El detalle prioriza paciente/motivo/atención y separa observaciones, pagos y documentos en pestañas. Disponibilidad se consulta al abrir su sección. Se reutilizan colores/controles del ERP y JS/Blade existentes, sin framework adicional.

Borradores de observación y selección mantienen sus valores y la versión sobre la que comenzaron; refrescar remotamente no actualiza esa versión para encubrir un conflicto. Un 409 exige revisar y confirmar de nuevo. Cambiar filtros o pestañas preserva borradores; cambiar paciente o actualizar expresamente pide conservar/descartar. Las respuestas tardías y el polling mantienen abort/revisión, foco, visibilidad y exclusión de operaciones simultáneas. Los selectores nativos no reinicializan Bootstrap Select después de filtrar.

Motivo completo únicamente en detalle autenticado y autorizado; resumen de 160 columnas visuales únicamente para las 30 citas visibles. El historial de otras citas, disponibilidad genérica y Nicol no lo incluyen. No se añaden logs, telemetría ni Clarity.

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

## Tercera ronda: lecturas y documentos aportados

La lectura operativa tiene un scope específico para ADMIN activo con MVP_ACCESS/VIEW; no modifica el scope base ni las escrituras. PatientAppointmentDocumentUpload orquesta el almacén existente, ledger UUID y auditoría. PATIENT_PROVIDED mantiene separación frente a pagos e indicaciones. El componente compartido conserva borradores y usa PDF.js local para dibujar contenido, sin scripts/órdenes. La salida conserva su transacción y retorna el detalle ya actualizado, evitando dos lecturas HTTP inmediatas.

## Extensión de cuarta ronda

AppointmentManagement adapta permisos/revisión a economy y notes existentes; reschedule admite revisión opcional sin duplicar reservas. attendance_mode se mantiene separado del contrato económico y tipo_agendamiento. OperationalProcedureService admite custom_label con clave c:UUID. AgendaProfessionalPreferences guarda solo navegación individual en BD. financial_status es una lectura común y no autoriza dinero o confirmación. El dialog reutiliza el panel de cierre y el componente privado documental. [Detalle y secuencia](RONDA_4_DIAGNOSTICO_Y_GUIA.md).

## Continuación local: capacidad e indicadores

RegularCapacityService cambia solo la proyección anónima mensual: pagos reales positivos e inequívocamente atribuidos, con efectivo restante positivo; no usa secured como umbral. El nombre de respuesta ocupacion_segura permanece por compatibilidad. AppointmentEconomicPosition y sus reglas50%, confirmación, saldos y disponibilidad no cambian. Snapshots ambiguos y crédito sin nuevo pago no se convierten en cobro real.

El renderizador diario crea agenda-day-indicators separado de la identidad en la quinta celda. agenda-layout.css organiza las dos zonas con Grid y reordena las celdas en móvil. El componente compartido filtra únicamente el chip redundante PAGO_REAL, conserva claves/finanzas y prioridades; títulos/nombres accesibles describen indicadores en columnas estrechas. El detalle +N no propaga clic/doble clic a selección/gestor. Consultar [entrega20/10](CONTINUACION_20261020.md).

Los reportes integrados desde main76e6ad2 conservan su controlador/formatos de lectura y autorización independiente. No reutilizan disponibilidad pública para revelar pacientes. El reloj determinista pertenece exclusivamente a Tests/Support/IsolatedAgendaV2Qa, verifica primero la identidad ficticia y no participa en código de negocio ni suites PHP. Al finalizar QA se desactiva.
