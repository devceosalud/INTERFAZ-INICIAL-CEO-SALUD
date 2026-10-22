# Diagnóstico previo a cambios — Fase A

Base comprobada: `2e81877557fc07d7bb6bf3f31e06f2f100b958e8`, padre `6719af57d5fb01af10ed4ae78f02c61ef2565de3`. La rama candidata anterior estaba limpia. Se creó `codex/auditoria-funcional-mvp-20261021` en otro worktree. Los cambios locales de la raíz y de la rama económica anterior no se trasladan ni se modifican. Ningún SHA local acredita por sí solo el estado instalado en Hostinger.

## 1. Reprogramación

YA FUNCIONABA: `RescheduleAppointmentService::reschedule` actualiza la misma cita y registra `REPROGRAMADA`; `RescheduleHistory::forRange` consulta eventos con visibilidad/filtros y devuelve orígenes/destinos/vigente sin incorporarlos a ocupación. `agenda-day-grid.js` y `agenda.js` colocan `AgendaRescheduleHistory.marker` dentro del intervalo de origen y conservan coexistencia con otra cita. `calendarIndicators` añade DOM compacto, no eventos FullCalendar. `AdmissionBoardService::board` devuelve referencias separadas de filas/KPI/dinero, y `admission-board.js::renderReschedules` las muestra.

DIFERENCIAS: `agenda-reschedule-history.css` pinta azul, no rojo; el texto del marcador omite la fecha original aunque el intervalo esté fechado. Sus botones usan varios textos (vigente/actual). Cambios previstos: solo CSS rojo suave y texto completo/etiqueta de enlace común. No cambiar servicios, consultas, reprogramación, disponibilidad o semáforo.

## 2. Atención y reevaluación

YA FUNCIONABA: `AdmissionCareService::transition` reutiliza `LegacyAppointmentMutation`, registra timestamps y actor/eventos/idempotencia; llegada (`EN_ESPERA`) no inicia atención. Finalización (`ATENDIDO`) y salida física son acciones separadas. `OperationalProcedureService` conserva selección, progreso, verificador y eventos; la lista vacía NO activa READY. `send` permite envío parcial con realización previa y motivo; `ManualReevaluationService` conserva una marca administrativa de retorno sobre ATENDIDO. `AppointmentReevaluationService` unifica estos dos orígenes de listo.

DIFERENCIAS CRÍTICAS: `AdmissionBoardService::careActions` permite EN_ATENCION y ATENDIDO a cualquier usuario con `appointment.update`, sin identidad médica. `LegacyAppointmentMutation::update` tampoco comprueba identidad médica. No existe indicación médica pendiente en el flujo de la misma cita ni filtro/contador específico. No se puede usar el marcador manual administrativo como sustituto.

BLOQUEO DE IDENTIDAD: `Doctor` no tiene usuario asociado; `User` no tiene médico asociado; las migraciones de users/doctors y sus extensiones no establecen un vínculo autenticado. `agenda_professional_preferences` es una preferencia de selección de profesionales, editable por el propio usuario, NO una acreditación de identidad. La cadena MEDICO aparece en una clave de caché de `AgendaHeatmapController`; no establece una cuenta médica ni una política. No se presupone que dicho rol exista o esté autorizado. Los seeders no definen un acceso médico. No se consultó la DB productiva.

`ReevaluationIndicationService` registra una acreditación administrativa de orden escrita sobre una atención anterior y puede vincular otra cita/servicio económico: no representa el retorno pendiente del mismo episodio. Reutilizarlo para el nuevo botón alteraría su significado y sus reglas. `appointment_operational_reviews` ya representa preparación de procedimientos; tampoco acredita quién es el médico autenticado.

Se solicitó al responsable la ubicación de una vinculación aprobada si existe fuera de esta candidata. Hasta resolverla, no se habilitan acciones médicas usando nombres, CMP, doctor_id recibido del navegador, preferencia de filtro o una cuenta administrativa. No se crean roles, permisos clínicos o asociaciones supuestas. El punto 2 NO se considera aprobado. Las reglas actuales que permiten inicio administrativo se informan como hallazgo abierto; no se presentan como una atención médica segura.

Propuesta mínima para revisión si no existe integración: vínculo explícito, verificable y administrado entre cuenta activa y médico activo (definir cardinalidad, quién lo asigna/revoca y cuentas con múltiples roles), aplicado bajo bloqueo a todas las transiciones médicas y rutas heredadas. Luego evento de indicación/retirada dentro de `appointment_events`, con UUID/revisión existentes; proyección pendiente solo si no está listo/inactivo/finalizado; reutilización de preparación manual/automática, filtros excluyentes y chip secundario sin modificar `estado_cita` ni colores. No es necesario inventar otro módulo de procedimientos. Esta propuesta no está implementada ni aprobada por la auditoría.

## 3. Colores de Admisión

YA FUNCIONABA: `AgendaAppointmentPresentation::of`, `AgendaLegend`, `AdmissionStatus` definen presentación operativa y estado económico por separado; la respuesta de Admisión ya los contiene. Confirmada sin pago no se informa como pago real.

DIFERENCIAS: `admission-board.js::renderRows` utiliza una tabla local `tones` solo para el borde. `admission-board.css` mantiene filas/celdas blancas y sobrescribe la selección con otro fondo. Cambios previstos: eliminar ese mapa paralelo y consumir el color/fondo/franja del backend para toda la fila, incluidas celdas en escritorio y tarjeta móvil; selección mediante contorno, manteniendo etiquetas y legibilidad. No modificar los cálculos ni estados del backend. El chip pendiente médico queda bloqueado por el punto 2; no se simula con procedimientos incompletos.

## Orden de trabajo

Corregir y probar las partes independientes de 1 y 3; comprobar con datos sintéticos las reglas existentes de 2 y documentar sus fallos/bloqueos. No avanzar a 4 y 5 mientras cualquiera de los primeros tres permanezca abierto. Tampoco se afirma aprobación integral de la Fase A por pasar solo las regresiones técnicas.

## Actualización posterior al diagnóstico

El responsable confirmó que la vinculación no está aprobada y autorizó diseñarla, completar partes independientes y mantener deshabilitadas las acciones médicas. Se implementó la guardia403 para la vía actual y heredada; se preservaron acciones administrativas con sus roles y capacidades. Se añadió únicamente la lectura de eventos de indicación, etiquetas/filtros/counters y su relación excluyente con READY existente. No hay escritor médico, asociación, migración, concesión o botón médico habilitado. Ver propuesta, matriz y resultados posteriores; el diagnóstico previo del inicio administrativo200 describe la base anterior.
