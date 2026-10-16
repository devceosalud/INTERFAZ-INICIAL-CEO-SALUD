# Agenda CEO Salud — auditoría y etapas #25–#34

Baseline verificado: `origin/main = 2b4f1e8db81b8629aa8508cb41f45b0a263e660c`, merge del PR #24 (`9bc0796c…`). Nuevo worktree `agenda-etapa-a`, rama `codex/agenda-etapa-a-semaforizacion`. No se incorporó PR #21. Worktrees y cambios anteriores conservados; sin acceso/escrituras Hostinger.

| Issue | Estado al inicio | Reutilización / evidencia de código | Faltante, riesgo o decisión |
|---|---|---|---|
| #25 | PARCIAL | AgendaLegend, AgendaBoardPresenter, AppointmentEconomicPosition; is_postoperative; AdmissionCareService y hora_llegada/hora_atendido | Color económico no usa pagos reales; canceladas excluidas del feed operativo. Falta flag explícito herida y marca de salida física. Prioridad rojo/turquesa aprobada; resto de coincidencias consultado. No equiparar ATENDIDO a salida/reevaluación. |
| #26 | PARCIAL / privacidad | Presenter carga user/responsibleUser; agenda-selection.js y detalle | Nombre creador se envía hoy a todos los perfiles; filtrar backend por ADMIN, distinguir responsable y permitir consulta accesible en vistas. |
| #27 | FALTANTE | Feed y rangeQuery con AppointmentVisibility, motor separado DoctorAvailabilityService | Vista ADMIN de seguimiento después de autorización; pagos reales parciales incluidos. CANCELADO/RETIRO informativos no ocupan. NO_ASISTIO no se equipara a cancelación sin definición. No añadir «todas» sin aprobación. |
| #28 | PARCIAL | BankPaymentIdentity, VoucherPaymentRecorder, ReservationPaymentService; OCR/adjuntos y pagos sucesivos existentes | Diferenciar cuenta Yape en identidad/almacenamiento, compatibilidad histórica y duplicados entre cuentas; PR financiero separado, sin duplicar motor ni modificar el 50%. |
| #29 | DECISIÓN PENDIENTE | is_postoperative y servicio disponibilidad/registro con locks/UUID | Definir cualquier cita vs postoperatoria e inicio–inicio vs fin–inicio. Advertencia no bloqueante, confirmación explícita auditada; no programar una referencia inventada. |
| #30 | PARCIAL | Nicol registra primera hora_llegada idempotentemente; AdmissionStatus/Board | Numeración médico/día/sede propuesta; definir persistencia vs renumeración tras salida y prioridades clínicas. No cambiar hora reservada ni duplicar timestamp. |
| #31 | FALTANTE | DoctorSchedule, DoctorScheduleMutation, doctor.schedule.manage, auditoría/locks y DoctorAvailabilityService | Entidad BREAK, cadencia/excepciones y contingencias sin modificar citas; bloquear transaccionalmente API/confirmación/extendida. Aclarar excepciones ADD/FH si se pretenden. |
| #32 | DECISIÓN PENDIENTE | Editor permisos, documentos privados, auditoría, visibilidad de citas | No reutilizar observaciones visibles. Confirmar escritor formal Quiroz/ADMIN y alcance cita/paciente/arrastre. Notas privadas con permisos específicos, versionado y lectura Admisión; privacidad Clarity pendiente. |
| #33 | PARCIAL | Mensajes HTTP422, patientValidationMessage, draft model, botones explícitos del #24 | Resumen, campo/ARIA, contraste y foco/scroll conservando datos; CSS local. Independiente del motor de precios y sin bloquear corrección con Enter. |
| #34 | PLAN | Issue maestro y decisiones más recientes del usuario | Esta tabla no acredita implementación; resultados reales y PR por etapa. |

## Secuencia de ramas/PR

1. **A / lectura y semáforo**: rama actual, #25/#26/#27 coordinados en el mismo contrato de feed. Si se divide el lote, PR posteriores apilan sobre el anterior y declaran base; no PR paralelos que dupliquen el presenter. Migración aditiva exclusivamente para flags/marcas que se aprueben, sin backfill ni modificación de históricos.
2. **B1 / llegada**: `codex/agenda-b-orden-llegada`, desde A revisada; timestamp existente, definición pendiente de persistencia. **B2 / descansos**: `codex/agenda-b-descansos`, desde main actualizado o B1 si comparte disponibilidad; API y carreras obligatorias.
3. **C / pagos**: `codex/agenda-c-yape-cuentas`, rama financiera separada; depende únicamente de contratos base de pagos, integrar A al coordinar la UI sin duplicar cambios. Históricos YAPE nunca reasignados.
4. **D1/D2**: `codex/agenda-d-aviso-postoperatorio` y `codex/nicol-d-comentarios-privados`, sin codificar reglas hasta respuestas de negocio; comentarios no van en feed/telemetría/logs.
5. **E / validación**: `codex/agenda-e-validaciones`, puede avanzar independientemente; si toca workspace modificado por A, apilar y declarar dependencia.

## Invariantes de aceptación

Con responsable asignado, reserva privada visible solo para él; sin responsable, solo creador, también para ADMIN. Conservar AppointmentVisibility byte a byte y probar los dos casos, incluyendo reservas con dinero. No cambiar ocupación, 50%, cobros, precios, UUID ni los controles del PR #24. Rojo informativo no consume capacidad. AMARILLO requiere dinero atribuido real, no estado_pagado, crédito o snapshot histórico. Autorización en servidor y errores sin datos ajenos. QA SQLite/MariaDB aisladas y navegadores con pacientes ficticios; sin proveedores reales ni producción.
## Estado del lote A entregado para revisión

Código: `3ba9001264734bd8afae537757fd774b317e5b5b` (baseline anterior sin merges adicionales).

| Issue | Resultado de desarrollo | Criterio pendiente |
|---|---|---|
| #25 | **PARCIAL**: señales únicas blanco/amarillo/plomo/fucsia, rojo > turquesa, etiquetas conservadas, bandera HERIDA explícita, misma presentación en Nicol y Agenda. Mes mantiene resumen anónimo. | Prioridad entre PAGO_REAL/POSTOPERATORIO/HERIDA antes de llegada; registrar salida física explícita antes del tachado. No se presume una respuesta de negocio. |
| #26 | **IMPLEMENTADO Y PROBADO**: creador exclusivo ADMIN en feed/detalle accesible; alta e historial no lo revelan a otros perfiles. Nicol comparte la proyección de privacidad. Responsable sin asignar no se sustituye por creador. | Revisión del PR; en Mes se accede al detalle mediante apertura por teclado del día, conservando el resumen anónimo. |
| #27 | **IMPLEMENTADO Y PROBADO**: seguimiento ADMIN por defecto, dentro de visibilidad autorizada; dinero parcial no confirma. | Revisión del PR. No hay excepción ADMIN para reservas privadas ni opción «todas» no aprobada. |

Las dos consultas de negocio de #25 permanecen abiertas. Hasta recibir respuesta, las coincidencias conservan su base anterior y todas sus etiquetas (`visual_priority_pending=true`); `ATENDIDO` no tacha el nombre ni determina salida o decisión de reevaluación. Este lote no cierra #25 ni declara completa la Etapa A. #28–#33 conservan los estados y las ramas propuestas arriba; no se mezclaron sus cambios.

Pruebas, archivos, reproducción y reversión: [AGENDA_ETAPA_A_QA.md](AGENDA_ETAPA_A_QA.md).
