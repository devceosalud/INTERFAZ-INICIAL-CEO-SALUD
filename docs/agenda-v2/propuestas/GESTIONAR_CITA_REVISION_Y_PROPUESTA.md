# Gestionar cita: revisión y propuesta para aprobación

Fecha: 20/10/2026. Código inspeccionado: `13efa1489dfdb98e7f309a716225a539fd15e3cd`.

**Propuesta, sin implementación de operaciones nuevas.** Solo se agregan documentación y una maqueta independiente. No se cambia aplicación, permisos, esquemas ni datos. No se ejecutan suites funcionales nuevas: las evidencias de cuarta ronda no acreditan operaciones propuestas.

## Estado preservado

Worktree `.worktrees/agenda-v2`, rama `feat/agenda-operativa-flujo-reevaluaciones-v2`, HEAD igual a referencia. Sin cambios versionados al iniciar; archivo ajeno no versionado `dad}sad}sa` conservado. Sin cambios en main, otros worktrees, PR46, remotos o producción. Sin push, commits ni migraciones en esta revisión.

## Hallazgos comprobados

| Área | Evidencia y consecuencia |
|---|---|
| Modal | `agenda-manage-appointment.js` construye seis apartados, monta documentos y traslada seguimiento al dialog. Las capturas de cuarta ronda muestran casi toda la altura ocupada en escritorio y datos básicos consumiendo la primera pantalla móvil. Reemplazar estructura por cinco acciones y un formulario activo. |
| Protección existente | Conservar bloqueo/restauración de controles, confirmación de borradores, cancelación de lecturas, revisión y control de generaciones. |
| Permisos | `AppointmentManagement::canEdit` exige usuario activo, capacidades y visibilidad original de escritura. Lectura ampliada de ADMINISTRADOR no implica permiso de edición. |
| Servicio | `LegacyAppointmentMutation` bloquea cambiar médico/servicio porque modifica el contrato económico. No hay acción equivalente en el gestor. No basta agregar un select. |
| Compatibilidad y precio | Reutilizar `ActiveDoctorServiceResolver`, `ServiceSpecialtyEligibility` y `AppointmentPriceResolver`: asignación única activa, especialidades y excepciones expresas. Cotizar y revalidar bajo bloqueo. |
| Reprogramación | PATCH `/scheduling-mvp/agenda/appointments/{id}/reschedule`, `RescheduleAppointmentService`: médico/cita bloqueados, revisión y fecha/hora esperadas, duración doble, clasificación y reglas clínicas/económicas. Ya bloquea cambios económicos clínicos con dinero/comprobante. Reutilizar. |
| Reasignación | Adaptador heredado rechaza `patient_id`; gestor no tiene reasignación. Requiere acción específica con análisis de relaciones. |
| Ficha de paciente | `/patients`, GET/PUT `/patients/{id}`, `OperationalPatientMutationController`: identidad única, DNI, teléfono, correo y responsables de menores. Navegación de retorno a Agenda ya disponible. No copiar validaciones. |
| Auditoría de ficha | El PUT general inspeccionado no registra valores anteriores/nuevos ni revisión esperada. PATCH de contacto registra campos si recibe una cita. Proponer auditoría y concurrencia en el editor compartido; no afirmar que ya existen completas. |
| Pagos | `ReservationPaymentService`, `AppointmentTicketService`, `VoucherPaymentRecorder` y `Sales::guardarVenta`: cita→voucher_items→voucher→payments; pago ligado a usuario y turno. No atribuir dinero solo por paciente. |
| Atribución | `AppointmentEconomicPosition` reconoce familia TICKET/BOLETA/FACTURA, excluye anulados y exige una sola línea de cita para atribuir sus pagos. Distingue PAYMENTS, UNALLOCATED, LEGACY_SNAPSHOT y créditos/devoluciones. |
| Riesgo de anulación | Caja (`CashierShift::calcularMontoSistema` y Livewire `CashierShifts`) y `Voucher::total_pagado` suman pagos directamente; Agenda excluye comprobantes ANULADO. Anular solo voucher puede dejar cálculos distintos. No se encontró corrección integral reutilizable en los flujos revisados. |
| Operación bancaria | `Payment::save`, `BankPaymentIdentity` e índice único: método+banco/billetera+operación normalizados, ceros iniciales preservados, protección concurrente. Falta flujo específico con motivo/auditoría. |
| Emisión | `Sales` deja no-TICKET con `requiere_sunat=true`, PENDIENTE; Voucher guarda hash/respuesta/XML/CDR/fecha. `SunatService` inspeccionado consulta RUC. Estos campos no demuestran por sí solos emisión o ausencia de comunicación real. No se consultó ningún servicio externo. |
| Conciliación | No se encontró estado bancario conciliado por Payment en modelo/migración ni flujo de conciliación en los servicios revisados. Turno abierto no prueba ausencia de conciliación externa. |
| Devolución/crédito | `AppointmentWithdrawalService` solicita devolución/aplica crédito desde RETIRO; no corrige captura ni demuestra devolución ejecutada. No usarlo para simular S/100→S/10. |

## Propuesta visual

`gestionar-cita-propuesta.html`: maqueta con datos ficticios, HTML/CSS sin envíos ni integración con ERP. Se pueden alternar vistas demostrativas. Capturas de escritorio y móvil acompañan la propuesta.

Modal centrado, máximo aproximado 760px y 88% de altura disponible, desplazamiento interno solo si hace falta. Cabecera compacta con paciente/documento, servicio, profesional, fecha/hora, estado y modalidad; economía resumida en una línea. Cinco acciones visibles: **Cambiar servicio · Reprogramar · Cambiar paciente · Datos del paciente · Corregir adelanto**. Inicio sin formularios abiertos; un único panel activo. En móvil acciones compactas y formulario de una columna.

Atención, procedimientos, documentos e historial se abren en sus módulos con contexto, sin montar formularios duplicados en el gestor. Cancelación y retiro conservan sus flujos en seguimiento. Motivo/modalidad/indicadores ya editables se mantienen accesibles en datos operativos existentes mediante enlace secundario, conservando permisos y confirmaciones.

Conservar foco, teclado, Escape con protección de borrador, bloqueo de operaciones simultáneas y estado de carga accesible. Cambiar acción no descarta texto sin confirmación. Actualización externa avisa conflicto sin reemplazar el borrador.

## Cambiar servicio: alcance inicial propuesto

- Mismo profesional; servicios activos compatibles/autorizados. Cotizar con motor actual, sin copiar automáticamente descuentos, exoneraciones, tarifas adicionales o indicaciones del servicio anterior.
- Mostrar servicio, precio y duración actuales/propuestos; comprobar toda la duración y disponibilidad. Si falta regla válida de duración, bloquear, no estimar.
- Permitir inicialmente PROGRAMADO/CONFIRMADO sin atención iniciada, actividad clínica, documentos, comprobantes, pagos/snapshots positivos, crédito ni devolución, y **mismo importe final**. Motivo y auditoría anterior/nuevo obligatorios.
- Diferencia económica: mostrarla y bloquear hasta decisión adicional de negocio. Comprobante también bloquea aunque coincida el precio: su descripción y relaciones no deben quedar inconsistentes.
- Indicaciones clínicas como origen/destino, procedimientos o reevaluación histórica/operativa bloquean la corrección simple. No borrar relaciones para habilitarla.
- Ampliar a reservas sin dinero y distinto precio requeriría aprobar explícitamente la recotización.

## Cambiar paciente: alcance inicial propuesto

Buscar otro paciente registrado/activo y mostrar ambas identidades, motivo y confirmación explícita. Permitir solo reserva PROGRAMADO sin llegada/atención, pagos/snapshots positivos, comprobantes incluso sin pago, crédito/devolución, documentos, procedimientos, tareas de atención, reevaluación o indicaciones clínicas como origen/destino.

**Motivo, observaciones e indicadores clínicos no pueden transferirse.** Si contienen datos del paciente original, bloquear la reasignación simple. No vaciarlos silenciosamente. Como el motivo es obligatorio en nuevas citas, muchas reservas quedarán bloqueadas: no presentar este alcance como solución completa de esos casos.

Alternativa que necesita aprobación y diseño adicional: archivar el contexto previo bajo el paciente original, restringir su acceso y exigir datos nuevos del paciente correcto antes de reasignar. No elegirla implícitamente ni mostrar al paciente nuevo el historial clínico anterior.

No bloquear por el mero evento administrativo de creación: conservarlo y registrar identidad anterior/nueva. Los eventos con contenido clínico sí impiden la vía simple. Revalidar todas las relaciones bajo bloqueo: un pago/documento nuevo en otra sesión debe impedir la reasignación. No crear citas ni trasladar dinero automáticamente.

## Corregir ficha del mismo paciente

Abrir módulo `/patients` con contexto de Agenda existente y regreso a la cita. Aviso: **Cambias la ficha del paciente, utilizada en todas sus citas**. Reutilizar validaciones y permisos efectivos. Proponer auditoría estructurada y revisión de versión en el editor compartido, sin duplicación. Datos sensibles solo en almacenamiento autorizado, sin contenido en logs/telemetría. No alterar identidades impresas de comprobantes históricos ni fusionar pacientes.

## Corregir adelanto: flujo común propuesto

1. Listar Payment reales atribuidos inequívocamente, familia ticket/comprobante sin duplicar. Mostrar ID, método, importe, referencia textual, comprobante, turno y responsable. Snapshot sin Payment no se convierte en pago corregible.
2. Elegir exactamente un pago y error: importe, referencia o ambos. Mostrar actuales/propuestos y efecto sobre acreditado, saldo y caja.
3. Prevalidar permiso, relaciones, turno, emisión, crédito/devolución y conciliación. Campos vacíos no acreditan ausencia de emisión/conciliación.
4. Exigir motivo y confirmación. No pedir al paciente pagar otra vez ni reutilizar nuevo cobro para corregir referencia.
5. Transacción con versiones, bloqueos compatibles, clave idempotente/hash e índice de unicidad. Una operación concurrente invalida la vista previa. Error en ambos campos: todo o nada.
6. Auditoría inmutable con pago/cita/voucher/turno, anterior/nuevo, actor, fecha/hora, motivo y solicitud. Mantener soportes privados; marcarlos para revisión si la corrección los contradice, sin reemplazarlos ni acreditarlos de nuevo automáticamente.

### Matriz propuesta

| Situación | Resultado propuesto tras aprobación |
|---|---|
| Referencia errónea; TICKET interno único sin hijos/evidencia fiscal; turno original propio abierto; sin crédito/devolución y conciliación expresamente descartada | Corregir referencia con capacidad específica, auditoría y unicidad. Importe/método/cantidad de pagos intactos. |
| Referencia vigente duplicada para método/banco; legado ambiguo; efectivo con número de operación | Rechazar según reglas actuales. |
| Monto erróneo con condiciones simples anteriores | Requiere aprobar mecanismo económico descrito abajo antes de habilitarlo. |
| Ambos errores | Una sola corrección atómica, aplicando restricciones del caso más estricto. |
| BOLETA/FACTURA, hijo, envío/hash/XML/CDR o emisión incierta | Bloquear vía simple y derivar a revisión contable/fiscal. PENDIENTE no demuestra ausencia de emisión. |
| Turno cerrado/ajeno, conciliación desconocida/realizada, créditos, devoluciones o atribución compartida | Bloquear. No reabrir turno ni simular devoluciones. |
| Snapshot histórico sin Payment | Conciliación documentada; no fabricar pagos. |
| Cambio concurrente | Conflicto; conservar propuesta y solicitar revisión actualizada. |

### Monto: recomendación para aprobación

**Registro inmutable de corrección**, en tabla aditiva ligada al Payment original, cita, comprobante, turno y solicitud. Ejemplo: original S/100, importe válido S/10, diferencia registral −S/90. Original y secuencia permanecen consultables. No es devolución, egreso, descuento ni un cobro nuevo, y no cambia precio contratado. No crear Payment negativo/cero ni otro Payment de S/10.

Una proyección monetaria compartida debe alimentar Agenda, total pagado/saldo del voucher, caja, liquidación, créditos/devoluciones, resúmenes y CSV/XLSX. Actualizar también snapshots y estado económico dentro de la transacción. Mantener por separado monto registrado, correcciones e importe válido donde corresponda auditar.

No recomiendo anular solo voucher ni borrar/reemplazar Payment: hoy los lectores no comparten reversión y se arriesgan relaciones y consistencia. Anulación con sustitución completa también requeriría todos los lectores y aprobación contable; no es un atajo existente.

Límites propuestos: importe válido positivo, dentro del contrato y sin aplicaciones posteriores afectadas. Si exige modificar confirmación, exoneración, precio, crédito o estado clínico, bloquear hasta definir esa política.

**Rollback:** después del primer ajuste, código antiguo que suma `payments.monto` devolvería importes incorrectos. Mantener lectores compatibles con correcciones, conservar tabla/auditoría y poder desactivar nuevas escrituras. No prometer rollback a código anterior incompatible. Aprobar esta dependencia antes de habilitar montos.

### Referencia: recomendación para aprobación

Actualizar solo `numero_operacion` por modelo/índice existente, con auditoría anterior/nuevo en la misma transacción. `001245→001254` permanece texto; ningún Payment ni movimiento adicional. El importe y método se conservan.

Referencia antigua siempre en auditoría. Propuesta: unicidad de referencia vigente, sin reservar permanentemente la errónea porque podría corresponder a otro pago auténtico. Reservar referencias históricas requiere una regla adicional aprobada.

## Permisos propuestos, sin conceder

Reprogramación y ficha mantienen permisos efectivos existentes. Nuevas capacidades separadas para servicio, paciente, referencia e importe. Propuesta inicial: ejecución solo por ADMINISTRADOR activo con capacidad expresa y visibilidad de escritura; ADMISION/COMERCIAL no reciben nuevas capacidades automáticamente. Registrar adelantos no otorga corregirlos. Corrección de pagos de turno ajeno por supervisor requeriría otra aprobación.

## Pruebas tras aprobación

SQLite/MariaDB ficticias, permisos por rol, bloqueos por relación, idempotencia, carreras pago/documento/reasignación, unicidad y ceros iniciales, igualdad de saldo/caja/voucher/reportes. Chrome 1366/1440/1920/390, cinco acciones, teclado/móvil, borradores, sesiones concurrentes, reprogramación, simultaneidad y revisión visual humana. Regresión de cuarta ronda: precios/MFR, pagos/caja/OCR, teleconsulta, reglas clínicas0/1/15/16, documentos, procedimientos, hotfixes históricos y reportes CSV/XLSX.

## Decisiones solicitadas

1. Aprobar diseño, reprogramación reutilizada y ficha con retorno contextual.
2. Aprobar servicio inicialmente sin vínculos y mismo importe; decidir aparte recotización de reservas sin dinero.
3. Reasignación: aprobar alcance vacío muy limitado o solicitar diseño ampliado que archive contexto bajo paciente original y exija nuevos datos. Recomendación: bloquear contenido anterior hasta diseñar archivo y privacidad.
4. Aprobar referencia auditada, ADMINISTRADOR con capacidad específica y unicidad vigente sin reserva permanente de referencia equivocada.
5. Aprobar o aplazar ajuste inmutable de montos y lectores/rollback compatibles; definir quién acredita ausencia de conciliación externa. Hasta resolverlo, monto bloqueado.

Ninguna decisión se aplica al elaborar esta propuesta. Se espera aprobación antes de programar.

## Alternativa concreta para reasignar reservas que solo contienen motivo/indicadores

Para hacer útil la reasignación sin transferir contenido, propongo aprobar un segundo alcance limitado: reserva sin atención ni relaciones económicas/documentales/procedimientos, cuyo único contenido del paciente sean motivo, observaciones e indicadores iniciales.

- Mostrar dos columnas: paciente original y paciente correcto. Exigir motivo administrativo del error y un motivo de consulta nuevo; los indicadores del nuevo paciente se confirman expresamente, sin precargarlos del anterior.
- Antes del cambio, guardar una versión privada del contexto original ligada explícitamente a su patient_id y appointment_id, con autor y fecha de la corrección. Conservar referencias a eventos originales, sin reescribirlos ni atribuir su fecha/autor al corrector.
- El contexto archivado queda accesible solo mediante historial autorizado del paciente original y auditoría específica. El detalle normal de la cita reasignada y el historial del paciente nuevo no muestran ese contenido.
- Cambiar la asignación y registrar el contexto nuevo y el evento administrativo en una sola transacción. No borrar el anterior ni mover archivos, comprobantes o procedimientos. Cualquier vínculo nuevo de esos tipos bloquea la operación.
- Requiere almacenamiento versionado con identidad de paciente y reglas de lectura explícitas: no basta meter texto anterior en el historial normal de la cita porque podría mostrarse al paciente nuevo. El diseño definitivo de tabla y permisos se revisaría antes de su migración local QA.
- Esta alternativa permite resolver el caso habitual con motivo obligatorio; recomiendo aprobarla para reservas sin otros vínculos en lugar de aceptar una reasignación casi siempre bloqueada. No se ha implementado.

La confirmación debe elegir expresamente entre esta alternativa y el alcance mínimo sin contenido. Los casos con dinero/documentos/actividad clínica siguen bloqueados en ambos.
