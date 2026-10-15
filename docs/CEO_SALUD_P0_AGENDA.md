# CEO Salud — P0-A: reservas, controles financieros y contexto de Agenda

14/10/2026. Rama codex/mvp-p0-agenda; base PR #8 (a6fcb713). PR #5 no se amplía. Desarrollo local; sin merge, producción, migraciones ni cambios de Clarity.

## Cambios y regla aprobada

- Una reserva REGULAR nueva exige intervalo real disponible. También se valida antes de cada nuevo adelanto y reprogramación, bajo lock del médico; error genérico sin datos del ocupante.
- Una reserva sin dinero permanece no exclusiva. Si existe pago positivo trazable en su familia de voucher, o aplicación positiva de crédito, protege el intervalo contra otra cita regular incluso con menos de 50%. No equivale a CONFIRMADA, ni a ATENDIDO. No hay TTL ni liberación automática.
- La protección usa payments/voucher_items/vouchers y créditos registrados, nunca total_pagado, estado_pagado ni imágenes del cliente. Conservador ante asignaciones ambiguas o tickets anulados con pagos: requieren revisión expresa. Solicitar devolución o deducir crédito no libera automáticamente un intervalo.
- Reprogramar mueve la protección; la transición de atención liberadora existente conserva el dinero/historial. No implementa devoluciones ni convierte excedentes en crédito. Dos intervalos de una cita extendida se protegen juntos.
- Confirmar REGULAR, ADICIONAL o FUERA_HORARIO exige 50% del neto, salvo exoneración específica autorizada o tarifa automática válida de reevaluación gratuita. El permiso ADD no sustituye CREATE, VIEW ni PAYMENT_SUBMIT. Las rutas especiales conservan URL/response y usan el registro común; UUID obligatorio, con validación de importe, motivo y autorización. Los formularios actuales consumen registrations; consumidores antiguos directos deben enviar request_key y payment.
- Rebooking con crédito exige PAYMENT_SUBMIT; modalidades especiales no omiten el mínimo.
- Agenda presenta horario, atención y pago por separado en Día/Semana/detalle. Mes expresa capacidad global y adicionales; las reservas con dinero bloquean disponibilidad sin contarse como confirmaciones aseguradas.
- Un intervalo ocupado por una reserva privada muestra OCUPADO sin paciente, ID de cita ni estado financiero. Se corrigió la clasificación que podía confundirlo con FUERA_HORARIO y la proyección de Semana; regresión de feeds Día/Semana/Mes y grilla JavaScript.
- R24 usa diálogo Swal del ERP. Todas las llamadas decisivas esperan su resultado antes de limpiar o cambiar contexto. No hay descarte silencioso si Swal falta.
- Fixture de navegador exige contraseña efímera privada antes de reiniciar la BD QA. ADMIN ficticio recibe solo seis permisos ordinarios; ADD se asignó y retiró usando el editor existente.

## Archivos principales

|Área|Archivos / finalidad|
|---|---|
|Ocupación|FundedReservation; AppointmentAgendaLifecycle: predicado SQL compartido, global y sin identidad. RegularReservationAvailability: validación financiera y proyección informativa.|
|Escrituras|CreateAppointmentService; RescheduleAppointmentService; ReservationPaymentService; OperationalRegistrationService; AppointmentWithdrawalService: slots, locks, gates y mínimo común.|
|Compatibilidad|StoreAgendaAppointmentRequest; StoreAdditionalAppointmentRequest; AgendaAppointmentController: entradas especiales delegan al flujo común, sin eliminar rutas.|
|UI|agenda.js; agenda-operational-workspace.js; agenda-operational-form.js; agenda-appointment-actions.js; guidance/partials: estados separados, descarte y CTA coherentes. CSS cambia solo tipografía de estados.|
|QA|RegularReservationExclusivityTest y wrapper MariaDB; AgendaConcurrencyTest: reservas pagadas, primer adelanto concurrente, histórico inconsistente, doble intervalo, crédito, snapshots manipulados, mínimos y permisos. Tests anteriores adaptados a decisiones aprobadas.|

## Evidencia ejecutada

- PHP **8.3.35**, suite completa SQLite aislada: **793 pruebas / 6.093 aserciones, aprobada**.
- PHP 8.3.35 / MariaDB **11.8.9**, suite completa aislada: **281 pruebas / 3.233 aserciones, aprobada**. Incluye procesos independientes, barrera real y carreras de primer adelanto / reserva con dinero.
- Regresión MariaDB 11.8.9 posterior para reservas y privacidad: **22 pruebas / 215 aserciones, aprobada**; incluye la nueva proyección anónima Día/Semana/Mes. La suite completa de 281 precede esta última adición.
- JavaScript Node: **224/224 aprobadas**. Incluye pendientes/confirmadas/PAGADO y descarte asíncrono.
- Navegador local (puerto 8023; BD propia 33320): cancelar conserva nota/paciente/hora; descartar limpia nota/documento/paciente; reserva S/20 permanece pendiente; completar con S/30 no confirma; confirmación expresa alcanza S/50 sin nuevo pago; ADD rechaza CTA con S/20 y acepta S/50; Día/Semana/Mes cargados con contenido real.
- ADMIN ficticio: ficha desde Agenda cargada/editada; editor de roles asigna ADD, acción aparece; lo retira, checkbox queda desmarcado y acción desaparece. Tras interrupción inicial del diálogo nativo se verificó el resultado persistido; retirada final sin interrupción.
- Contraste de BD QA después del navegador: tres payments suman S/100 (20+30+50), dos citas nuevas CONFIRMADA / PROGRAMADO / PARCIAL con S/50 cada una; cita histórica exacta sin cambios; edición maestra persistida; petición HTTP ADD tras retirada obtiene **403** y no cambia ninguna tabla operativa/financiera revisada.

Logs completos quedan en storage/app/qa-rules (ignorados, sin credenciales publicadas). Screenshot local p0-agenda-states.png. El primer intento MariaDB rechazó el bootstrap del nuevo wrapper; se corrigió para usar IsolatedAgendaQa, sin relajar guardas, y la suite completa final pasó. No se declaran aprobadas pruebas productivas.

## Revisión de anomalías existentes — SOLO LECTURA

Antes de liberar, obtener en staging una copia restringida/autorizada y revisar reservas pendientes REGULAR con pagos/créditos, conflictos de intervalo y horarios inválidos. Nunca usar producción para QA. El diagnóstico no debe devolver DNI/nombre/notas/evidencias.

Para cada candidato, comparar AppointmentEconomicPosition y RegularReservationAvailability::describe con su intervalo real. Emitir solo ID de cita, médico, fecha/hora/duración, dinero trazable y motivo genérico. Si hay conflicto, detener nuevo adelanto y confirmación; responsable autorizado define reprogramación o tratamiento explícito del dinero. No cambiar importes, estados, pacientes, vouchers, créditos ni refund requests automáticamente. Una vista previa de permisos no soluciona estos registros.

El catálogo productivo comunicado conserva 4.807 pacientes, 14 citas y 2 pagos. No se asumen ficticios. La auditoría de anomalías en producción **PENDIENTE**, a ejecutar supervisadamente con SELECT y sin datos personales innecesarios.

## Límites y publicación controlada

P0-A no se declara listo en Hostinger. Pendientes: creación/ficha modal y registro/edición heredado de extremo a extremo en navegador con todos los perfiles; OCR/pagos R20; sede/panel Nicole; permisos efectivos/guards/mode piloto; respaldo y restauración ensayada; privacidad/admisibilidad de Clarity.

Publicar/revisar en orden main → #5 → #6 → #7 → #8 → PR P0-A. No fusionar ni activar autodespliegue. Este PR agrega **cero migraciones/seeders**. Las migraciones de las ramas base continúan sujetas al checklist de sus PR.

Antes de un despliegue autorizado: respaldo consistente de archivos, BD y adjuntos privados con checksum, restauración aislada verificada, SHA esperado y mecanismo de autodespliegue documentados; smoke ficticio en staging equivalente; comprobación de modo financiero y grants aprobados.

Rollback de código al SHA anterior conserva BD y adjuntos, detiene nuevas escrituras durante la reconciliación y exige comprobar compatibilidad con nuevos registros. Revertir esta protección no habilita reanudar escrituras inseguras: mantener el bloqueo operativo hasta reconciliar reservas pagadas. No hacer migrate:rollback ni restaurar toda la BD sobre pagos/citas creados después del despliegue. Primero inventariar y conciliar estos movimientos con respaldo; el dinero e historial no se borran.
