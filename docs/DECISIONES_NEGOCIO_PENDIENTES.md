# Decisiones de negocio pendientes

## Cómo usar esta lista

Estas son las diez decisiones estructurales con mayor efecto en dependencias y datos. No son preguntas técnicas; cada respuesta debe convertirse después en reglas, ejemplos y criterios de aceptación. El orden es de desbloqueo recomendado.

## 1. Organización, sedes y puntos operativos

**Pregunta para Rodrigo:** ¿CEO Salud operará una o varias sedes y, dentro de cada sede, qué unidades deben distinguirse: consultorios, cajas, almacenes y puntos de emisión?

Debe precisar:

- si una persona/trabajador/profesional puede pertenecer a varias sedes;
- si agenda, caja, stock y series se separan por sede;
- si se requiere consolidación corporativa.

**Por qué es primera:** condiciona llaves, permisos, correlativos, disponibilidad, inventario y reportes. No debe inferirse una única sede solo porque el esquema actual no tenga esa entidad.

## 2. Persona, trabajador, profesional y usuario

**Pregunta para Rodrigo:** ¿qué personas administra el ERP y cuándo una persona debe ser trabajador, profesional de salud, paciente y/o usuario del sistema?

Debe precisar:

- si todo trabajador tendrá usuario;
- si existen profesionales externos o por turnos;
- si un trabajador puede cumplir varios cargos;
- si un profesional puede tener varias especialidades;
- quién activa/desactiva la relación laboral y el acceso.

**Desbloquea:** modelo de identidad, personal, médicos, seguridad, agenda y auditoría.

## 3. Responsabilidad y permisos operativos definitivos

**Pregunta para Rodrigo:** ¿qué cargos pueden ver, crear, modificar, cancelar, anular o aprobar cada operación sensible y en qué ámbito?

Operaciones mínimas a decidir:

- pacientes y responsables;
- horarios, disponibilidad y citas;
- llegada/llamado/atención;
- precios, descuentos y exoneraciones;
- apertura/cierre/reapertura de caja;
- movimientos, ventas, pagos, devoluciones y anulaciones;
- series, documentos SUNAT y reportes.

**Desbloquea:** sustitución de la matriz provisional por permisos/capacidades reales y diseño de vistas por rol.

## 4. Alcance clínico del ERP

**Pregunta para Rodrigo:** ¿el sistema será solo administrativo/comercial o también custodiará historia clínica y el acto asistencial?

Si incluye clínica, precisar si abarca:

- anamnesis, diagnóstico, evoluciones, recetas y órdenes;
- consentimientos/documentos;
- laboratorio, imágenes o terapia;
- firma, confidencialidad y acceso por profesional.

**Desbloquea:** límite entre cita y atención, protección de datos de salud, documentos y módulos candidatos. El campo `historia_clinica` actual es solo un número; no demuestra historia clínica electrónica.

## 5. Ciclo de vida de cita, pago y atención

**Pregunta para Rodrigo:** ¿cuándo una cita se considera reservada, puede existir sin pago y puede atenderse con saldo pendiente?

Debe precisar:

- reserva temporal frente a confirmada;
- anticipo mínimo/total y vencimiento;
- reprogramación, cancelación, no-show y penalidad;
- llegada, cola, llamado, inicio y cierre;
- quién puede cambiar cada estado y si se exige motivo;
- qué ocurre con pago/deuda al cancelar o reprogramar.

**Desbloquea:** máquina de estados, reserva de slots, cobranza, llamador y auditoría.

## 6. Reconsulta, reevaluación y continuidad asistencial

**Pregunta para Rodrigo:** ¿qué significa exactamente reconsulta/reevaluación y qué condiciones otorgan ese beneficio?

Debe precisar:

- si es tipo de cita, estado, servicio o tarifa;
- ventana de días y desde qué evento se cuenta;
- mismo médico, especialidad o servicio;
- precio, número máximo y excepciones;
- efecto de cancelación/no-show y si requiere atención previa completada.

**Desbloquea:** corrección del estado `REEVALUACION`, reglas de pricing y relación cita-atención.

## 7. Venta, deuda, pago, caja y reversos

**Pregunta para Rodrigo:** ¿cuál es el proceso autorizado desde que se genera un cargo hasta que queda cobrado, anulado o devuelto?

Debe precisar:

- quién compra, quién recibe el servicio y quién paga;
- pagos parciales, mixtos, anticipos y crédito;
- si un pago puede aplicarse a varias deudas o viceversa;
- tratamiento de vuelto, comisiones y número de operación;
- quién anula/reembolsa y cómo se refleja en caja;
- reapertura/corrección de turnos y arqueos por medio de pago.

**Desbloquea:** fuente única de saldo, separación venta-pago-caja y controles financieros.

## 8. Documentos tributarios y SUNAT

**Pregunta para Rodrigo:** ¿qué comprobantes debe emitir legalmente este ERP y cuál será el proceso fiscal completo?

Debe precisar con contabilidad:

- boleta, factura, notas de crédito/débito y ticket interno;
- empresa(s), RUC, establecimientos, series y puntos de emisión;
- afectaciones IGV por servicio/producto, exoneraciones y detracciones;
- momento de emisión respecto al pago/atención;
- proveedor/OSE/PSE o integración directa;
- contingencia, resúmenes, bajas, rechazo y custodia de XML/CDR/PDF.

**Desbloquea:** modelo fiscal, numeración, catálogo tributario, integración, certificados y representación impresa. La impresión actual no es emisión electrónica.

## 9. Catálogo, farmacia/laboratorio e inventario

**Pregunta para Rodrigo:** ¿qué vende y controla realmente CEO Salud: consultas, procedimientos, laboratorio, medicamentos, insumos u otros productos, y cuáles requieren inventario?

Debe precisar:

- catálogo clínico vs comercial y reglas de precio;
- servicios propios/tercerizados;
- productos con lote, vencimiento, receta o control;
- almacenes, compras, proveedores, transferencias, pérdidas y conteos;
- si el costo/margen y comisiones médicas forman parte del ERP.

**Desbloquea:** unificación `services/items`, inventario, compras, farmacia/laboratorio y reportes de margen.

## 10. Llamador y comunicaciones con pacientes

**Pregunta para Rodrigo:** ¿qué experiencia de espera y comunicación debe ofrecerse y qué sistema será dueño de cada evento?

Debe precisar:

- relación y autenticación con `LLAMADOR-PACIENTE-CEO`;
- quién marca llegada y quién llama;
- datos permitidos en pantallas públicas;
- recordatorios/confirmaciones por correo o SMS y consentimiento;
- proveedores/canales autorizados, horarios y reintentos;
- si las notificaciones son informativas o cambian el estado de la cita.

**Desbloquea:** contrato entre aplicaciones, CORS/API, eventos, queue/cron, privacidad y trazabilidad.

## Resultado esperado de las respuestas

Para cada decisión se recomienda registrar:

1. regla general;
2. roles/cargos involucrados;
3. tres ejemplos normales;
4. excepciones y quién las aprueba;
5. datos que deben conservarse como evidencia;
6. reportes o alertas esperados.

No deben diseñarse migrations definitivas hasta que las decisiones 1–8 tengan respuesta o una exclusión explícita de alcance.
