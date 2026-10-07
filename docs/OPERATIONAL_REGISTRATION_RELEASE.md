# Registro operativo de Agenda

## Alcance y despliegue

Aplicar `2026_10_06_150000_add_operational_registration_and_documents` antes del código consumidor. Agrega teléfono secundario, procedencia económica, idempotencia y documentos privados. Conserva todas las citas y sus snapshots. Contactos WhatsApp/Llamada/Presencial se agregan solo si no existen. No migra automáticamente categorías heredadas de captación a contacto.

Guardar sin agendar realiza paciente/captación y cita en una transacción. Crea PROGRAMADO/PENDIENTE_CONFIRMACION/REGULAR y congela el precio del catálogo vigente. El propietario privado es responsible_user_id o, si es NULL, user_id. COMERCIAL/ADMISION reciben al actor automáticamente. ADMIN puede delegar con capability; no obtiene acceso especial a reservas ajenas. Dos reservas pueden compartir un slot.

Guardar y agendar REGULAR exige pago real >=50% o exoneración autorizada. Una autorización no genera dinero. Las excepciones explícitas ADICIONAL/FUERA_HORARIO conservan su flujo propio. Confirmar posteriormente reservas existentes serializa por médico: primera REGULAR, segunda ADICIONAL si el cupo está ocupado; el pago no se duplica.

## Dinero y permisos

Payment/Voucher/VoucherItem existentes son la autoridad. AppointmentEconomicPosition agrega cada Payment una vez y considera crédito/reserva de devolución cuando dichas tablas existan. Legacy sin estructura financiera usa snapshot solo como compatibilidad; no puede trasladarse como dinero. Documentos con múltiples líneas requieren atribución humana: no se prorratean pagos por suposición.

appointment.payment.submit es necesario para registrar dinero. Con SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT=false se requiere un turno manual propio ABIERTO. Con el flag true, solo operadores COMERCIAL/ADMISION/ADMINISTRADOR autorizados usan contexto AGENDA_PILOT_AUTO por actor/día (America/Lima), sin apertura humana ni comprobante SUNAT. Aplicar migration 2026_10_07_120000 antes de activarlo. La serie TICKET activa debe pertenecer a esa caja. Medio no efectivo exige operación; efectivo conserva operación NULL. Idempotencia actor/UUID + hash evita retries con datos distintos; la misma operación no efectiva del mismo ticket se rechaza. No se emiten documentos SUNAT desde Agenda.

El semáforo usa posición económica real cuando hay documentos, con fallback legado controlado. Exoneración no cuenta como adelanto. appointments.total_pagado queda como cache compatible de pago real, nunca como una segunda contabilidad.

## Documentos y DNI

Storage `local` privado (`storage/app/appointment-documents`), JPEG/PNG/PDF real, máximo 8 MB, nombres UUID. Descarga autenticada, scope de privacidad, attachment/nosniff/no-store. Links HTTPS se abren sin descarga automática. Patient consulta referencias a las citas visibles, sin duplicar archivos. Respaldo conjunto BD + storage privado; no usar storage:link para estos archivos.

Factiliza es opcional detrás de ReniecService. Ver FACTILIZA_IDENTITY_PROVIDER.md. Sin token/error continúa el ingreso manual y no se sobrescribe silenciosamente Patient existente. Ninguna variable secreta se entrega al navegador.

## QA local y pendientes de activación

MariaDB local ERPCEOSALUD, 127.0.0.1:3308. Migration individual aplicada tras guardas; ningún otro path ejecutado. Fixtures UI: reservas 202/203, paciente nuevo 4818 con documento OPS-20261006-NEW-5843; documentos 1/2 (PNG sintético y link ficticio). Deben limpiarse selectivamente tras el QA. Reserva 202 pertenece al paciente ficticio anterior 4816, que debe conservarse.

Demo Admision Local no posee appointment.payment.submit ni turno propio abierto: no se alteraron roles/permisos. Pago positivo y Caja se verificaron con actores controlados de SQLite. Antes de activar: revisar matriz de permisos/turnos de COMERCIAL y ADMISION, reconciliar catálogo canal/contacto y definir atribución de pagos en comprobantes multilínea. La deduplicación bancaria global entre tickets requiere una regla adicional de conciliación.

La migration 160000 agrega RETIRO, eventos, créditos, solicitudes de devolución y contingencias persistentes. Está aplicada localmente (batch 12); no reaplicarla. RETIRO exige appointment.withdraw, no appointment.audit.view. La capability se asigna explícitamente al operador autorizado; no se modifican roles automáticamente.

Cambios de horario conservan reservas privadas y crean bandeja ABIERTA para su dueño efectivo. Resolver registra seguimiento humano sin cancelar. RETIRO conserva fecha/hora y libera intervalo. Reprogramar desde RETIRO crea otra cita: crédito por voucher con bloqueo transaccional, sin copiar Payment ni total_pagado. Devolución SOLICITADA reserva el presupuesto, sin Payment negativo, modificación de voucher, nota de crédito ni movimiento de caja. Comprobantes multilínea requieren revisión humana. Sales excluye RETIRO y cobra el saldo efectivo de la nueva cita.

## Contexto financiero piloto

appointment_pilot_cash_contexts identifica el turno automático, caja, actor y fecha operativa. Bloqueo de User + unicidad actor/día y turno; correlativos TICKET se bloquean en la serie. Serie reservada P + ID base36 de tres dígitos: colisión o ID >46655 exige configuración humana, nunca reutiliza una caja arbitraria. El dinero conserva Payment, actor, operación e idempotencia. Contextos anteriores permanecen identificados para conciliación posterior: no se simula conteo/cierre humano. Caja manual, Sales y movimientos excluyen esos contextos incluso con flag apagado. No borrarlos con pagos: FKs restrict y rollback bloqueado si existen contextos.

El flag no concede capabilities. Demo10 no recibió permisos ni roles nuevos. QA positivo local usa un actor ficticio transaccional, flag solo en memoria y rollback completo; .env no se modifica. Para operar visualmente el piloto debe activarse explícitamente el flag y aprobarse appointment.payment.submit para el operador.
