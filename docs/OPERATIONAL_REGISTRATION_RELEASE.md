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

MariaDB local ERPCEOSALUD, 127.0.0.1:3308. Migration individual aplicada tras guardas; ningún otro path ejecutado. QA completado y fixtures 202/203/204/220 limpiadas selectivamente con sus relaciones, pacientes nuevos 4818/4819, horario ficticio 26 y PNG privado sintético. Se conservaron las 14 citas anteriores y pacientes 4816/4817. Las huellas de todas las demás filas/tablas permanecieron iguales.

Demo Admision Local no posee appointment.payment.submit ni turno propio abierto: no se alteraron roles/permisos. Pago positivo y Caja se verificaron con actores controlados de SQLite. Antes de activar: revisar matriz de permisos/turnos de COMERCIAL y ADMISION, reconciliar catálogo canal/contacto y definir atribución de pagos en comprobantes multilínea. La deduplicación bancaria global entre tickets requiere una regla adicional de conciliación.

La migration 160000 agrega RETIRO, eventos, créditos, solicitudes de devolución y contingencias persistentes. Está aplicada localmente (batch 12); no reaplicarla. RETIRO exige appointment.withdraw, no appointment.audit.view. La capability se asigna explícitamente al operador autorizado; no se modifican roles automáticamente.

Cambios de horario conservan reservas privadas y crean bandeja ABIERTA para su dueño efectivo. Resolver registra seguimiento humano sin cancelar. RETIRO conserva fecha/hora y libera intervalo. Reprogramar desde RETIRO crea otra cita: crédito por voucher con bloqueo transaccional, sin copiar Payment ni total_pagado. Devolución SOLICITADA reserva el presupuesto, sin Payment negativo, modificación de voucher, nota de crédito ni movimiento de caja. Comprobantes multilínea requieren revisión humana. Sales excluye RETIRO y cobra el saldo efectivo de la nueva cita.

## Contexto financiero piloto

appointment_pilot_cash_contexts identifica el turno automático, caja, actor y fecha operativa. Bloqueo de User + unicidad actor/día y turno; correlativos TICKET se bloquean en la serie. Serie reservada P + ID base36 de tres dígitos: colisión o ID >46655 exige configuración humana, nunca reutiliza una caja arbitraria. El dinero conserva Payment, actor, operación e idempotencia. Contextos anteriores permanecen identificados para conciliación posterior: no se simula conteo/cierre humano. Caja manual, Sales y movimientos excluyen esos contextos incluso con flag apagado. No borrarlos con pagos: FKs restrict y rollback bloqueado si existen contextos.

El flag no concede capabilities. Demo10 no recibió permisos ni roles nuevos. QA positivo local usa un actor ficticio transaccional, flag solo en memoria y rollback completo; .env no se modifica. Para operar visualmente el piloto debe activarse explícitamente el flag y aprobarse appointment.payment.submit para el operador.

## Registro progresivo y navegación (cierre 2026-10-07)

Paciente/servicio/contexto y acciones de reserva/agendamiento aparecen primero. Pago, documentos y datos complementarios permanecen cerrados. El modal nuevo registra identidad, celular, servicio y responsable sin abrir secciones opcionales. Género permanece requerido por la regla y columna existentes: no se inventa un valor ni se elimina esta validación en un cambio de UX. Celular secundario está en Paciente. El panel mantiene altura propia y un solo scroll lateral en escritorio; en pantallas angostas se apila y permite scroll de página sin colapsar el registro.

Completar ficha abre /patients con IDs internos y contexto técnico, sin DNI/nombre en URL. El backend valida capabilities, cita visible y coincidencia patient_id. Volver a Agenda usa doctor/fecha/appointment derivados de la cita autorizada, nunca un return_url externo. La ficha comparte Patient y la API maestra; su detalle incluye teléfono secundario para no borrarlo al guardar. Agenda recupera la selección y el celular. Después del alta queda la nueva reserva seleccionada con confirmación y acciones posteriores. Otra reserva en esta hora es una acción explícita distinta de modificar la existente.

Registrar adelanto acepta comprobante en el mismo multipart, servicio financiero e idempotencia: un retry conserva un Payment y un documento. Un fallo posterior al almacenamiento revierte el dinero y borra el archivo provisional. Payment proof storage centraliza MIME real, nombre generado, límite 8 MB y disco privado para el registro/documentos/pago existentes; no crea una contabilidad nueva.

Retiro y seguimiento permite operación sin audit.view, según appointment.withdraw y privacidad. Se corrigió un error de ámbito JS que impedía generar el UUID al pulsar Registrar retiro, con test que ejecuta la mutación real. Reservas afectadas por cambios de horario muestra paciente, fecha/hora, causa, estado y acción humana pendiente; leer/resolver no cancela la reserva.

QA visual Demo10: reserva básica 220/Patient4819 sin Pago/Documentos/Más datos; ficha4818 editada (segundo celular/canal/medio/nacimiento); retorno a cita203 sin volver a buscar; adelanto progresivo y privacidad; contingencia204 leída/resuelta conservando PROGRAMADO. Desktop1366 y móvil390px comprobados. Sin permisos nuevos ni credenciales cambiadas. Pago/RETIRO positivo: tests y QA MariaDB transaccional con actor ficticio, flag en memoria y rollback de todas las filas. No se afirma pago/RETIRO positivo visual con Demo10: no tiene las capabilities; el flag local permanece false.

Regresión final: Scheduling335 passed/1 skipped SQLite3.33; Patients68; Sales12; Security45; Catalog8; Baseline16; filtro Factiliza/providers/A2/Lifecycle45; JavaScript154. Cero fallos. El skip es el rollback nativo de DROP COLUMN de SQLite anterior a3.35; no afecta las migrations aplicadas en MariaDB. git diff --check limpio para el lote; whitespace heredado protegido se conserva.

Migrations locales:150000 batch11,160000 batch12,2026_10_07_120000 batch13. Solo120000 se aplicó en este cierre, por path individual; no se reaplicó150000/160000 ni se ejecutó rollback. Configurar SCHEDULING_MVP_ENABLED y SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT explícitamente para activar; el flag no otorga permisos. Factiliza mantiene su configuración documentada y fallback manual; no se hicieron consultas reales.

Pendientes antes de producción: aprobar matriz COMERCIAL/ADMISION (payment.submit/withdraw y acceso), activar piloto con supervisión y conciliar contextos diarios automáticos; atribuir vouchers multilínea antes de crédito; reconciliar legado sin documentos; confirmar duplicación bancaria entre tickets; configurar proveedor Factiliza y backup BD+storage privado. Devolución definitiva/nota de crédito/cierre financiero no se automatizan. Ningún despliegue ni push autorizado por este documento.
