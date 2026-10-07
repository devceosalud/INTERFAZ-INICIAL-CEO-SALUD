# Requisitos funcionales iniciales del ERP CEO Salud

## 1. Alcance de la especificación

Esta especificación transforma el AS-IS y el Blueprint TO-BE en comportamientos funcionales. No diseña tablas, pantallas, endpoints, clases ni tecnología de integración.

Etiquetas:

- **BASE / Confirmado AS-IS:** capacidad demostrada por el ERP actual que debe preservarse funcionalmente.
- **CONFIRMADO / Confirmado por negocio:** requisito decidido expresamente por Rodrigo para el TO-BE.
- **PROVISIONAL / Propuesta funcional aprobada provisionalmente:** regla funcional vigente para el Blueprint, sustituible solo mediante una decisión posterior trazada.
- **ESTÁNDAR / Propuesta técnica:** comportamiento de integridad, seguridad o trazabilidad derivado de buenas prácticas.
- **CONDICIONAL / Pendiente de negocio:** alcance que no debe asumirse hasta recibir una decisión empresarial.

Las evidencias narrativas pueden marcarse además como **Confirmado en código**, **Referencia externa** o **Pendiente de validación productiva**.

Las reglas marcadas como “por definir” no autorizan escoger una alternativa durante implementación.

## 2. Actores funcionales provisionales

Los nombres no constituyen la futura matriz de roles:

| Actor funcional | Responsabilidad observable |
|---|---|
| Usuario autorizado | Persona con cuenta vigente y capacidades explícitas. |
| Administración del sistema | Mantiene usuarios, acceso y maestros autorizados. |
| Admisión | Mantiene pacientes, responsables, agenda y citas según permisos. |
| Recepción/caja | Consulta operación, abre/cierra caja, cobra, vende, mueve fondos e imprime. |
| Profesional de salud | Participa en agenda, atención, historia clínica y flujo con el llamador según sus capacidades. |
| Supervisor/aprobador | Actor futuro para excepciones sensibles cuando el negocio las requiera. |

La matriz de Fase 1 sigue siendo contención provisional hasta una definición funcional posterior.

## 3. Requisitos transversales

| ID | Estado | Requisito |
|---|---|---|
| RF-TR-001 | ESTÁNDAR | Toda operación debe verificar en backend que el usuario tenga la capacidad requerida. |
| RF-TR-002 | ESTÁNDAR | Ocultar una opción de menú no debe considerarse autorización. |
| RF-TR-003 | ESTÁNDAR | Una operación rechazada no debe producir cambios parciales. |
| RF-TR-004 | ESTÁNDAR | Los importes, precios, impuestos, saldos y comisiones deben verificarse con fuentes autorizadas antes de registrar la operación. |
| RF-TR-005 | ESTÁNDAR | Los cambios críticos deben conservar actor, fecha y motivo cuando corresponda. |
| RF-TR-006 | ESTÁNDAR | Las operaciones financieras confirmadas no deben corregirse mediante borrado silencioso; deben anularse o revertirse. |
| RF-TR-007 | ESTÁNDAR | Los estados de cita, atención, cobranza, caja y documento tributario deben evolucionar de forma independiente y controlada. |
| RF-TR-008 | ESTÁNDAR | Los datos personales y de salud no deben exponerse a usuarios, integraciones o logs sin necesidad funcional. |
| RF-TR-009 | ESTÁNDAR | Una acción repetida accidentalmente no debe duplicar cobros, documentos ni reservas. |
| RF-TR-010 | ESTÁNDAR | El sistema debe ofrecer una alternativa controlada cuando una consulta externa no esté disponible, sin inventar datos. |
| RF-TR-011 | ESTÁNDAR | Reglas variables como plazos, porcentajes, autorizadores, sedes, permisos y series no deben dispersarse como constantes rígidas cuando su evolución sea previsible. |
| RF-TR-012 | ESTÁNDAR | Toda decisión funcional relevante debe conservar nivel de certeza, motivo y relación con la decisión que reemplaza. |

## 4. Identidad y seguridad

| ID | Estado | Requisito |
|---|---|---|
| RF-ID-001 | BASE | El usuario debe iniciar sesión con credenciales válidas y cerrar su sesión. |
| RF-ID-002 | BASE | El sistema debe limitar intentos repetidos de acceso y proteger la sesión autenticada. |
| RF-ID-003 | BASE | Un administrador autorizado debe crear y actualizar cuentas de usuario. |
| RF-ID-004 | BASE | Un administrador autorizado debe asignar roles/capacidades sin permitir autoescalamiento no autorizado. |
| RF-ID-005 | BASE | Un administrador autorizado debe mantener roles y permisos. |
| RF-ID-006 | ESTÁNDAR | Una cuenta debe poder deshabilitarse sin eliminar el historial del actor. |
| RF-ID-007 | ESTÁNDAR | Los cambios de credenciales y privilegios deben ser auditables. |
| RF-ID-008 | CONFIRMADO | La autorización debe poder distinguir visibilidad de módulos y operaciones de lectura, creación, modificación, eliminación, aprobación, anulación y otras acciones sensibles. |
| RF-ID-009 | CONDICIONAL | La matriz definitiva de roles, capacidades y ámbitos de sede se establecerá posteriormente. |
| RF-ID-010 | PROVISIONAL | Ser ADMINISTRADOR del ERP no concede automáticamente lectura o modificación de contenido clínico. |
| RF-ID-011 | PROVISIONAL | Autorizar una excepción al adelanto y verificar un pago son capacidades independientes. |

## 5. Organización y personal

| ID | Estado | Requisito |
|---|---|---|
| RF-OR-001 | CONFIRMADO | El modelo funcional debe admitir múltiples sedes aunque inicialmente opere una sola. |
| RF-OR-002 | CONFIRMADO | Consultorios, cajas, puntos de emisión, almacenes, horarios y otros recursos deben poder asociarse a una sede. |
| RF-OR-003 | CONDICIONAL | Las reglas de independencia o compartición entre sedes se definirán antes de habilitar la segunda sede. |
| RF-OR-004 | PROVISIONAL | La historia clínica será única entre sedes; cada atención conservará sede, consultorio, profesional, especialidad/servicio y fecha/hora. |
| RF-PE-001 | CONFIRMADO | El sistema debe distinguir persona, trabajador, usuario y profesional de salud, aunque puedan relacionarse. |
| RF-PE-002 | ESTÁNDAR | Debe conservarse la vigencia del vínculo de un trabajador/profesional sin borrar su historia. |
| RF-PE-003 | CONFIRMADO | Todo trabajador que opere el ERP debe disponer de acceso acorde con sus capacidades autorizadas. |

## 6. Pacientes y responsables

| ID | Estado | Requisito |
|---|---|---|
| RF-PA-001 | BASE | Un usuario autorizado debe registrar un paciente con sus datos administrativos y de contacto. |
| RF-PA-002 | BASE | Debe buscarse un paciente por documento y por datos de identificación permitidos. |
| RF-PA-003 | BASE | Un usuario autorizado debe actualizar e inactivar un paciente sin perder su historia. |
| RF-PA-004 | BASE | Cada paciente debe recibir un número de historia único. |
| RF-PA-005 | ESTÁNDAR | La generación simultánea de historias no debe producir duplicados. |
| RF-PA-006 | ESTÁNDAR | El sistema debe advertir o impedir duplicados de identidad según reglas verificables. |
| RF-PA-007 | BASE | Debe registrarse el canal y medio de captación cuando estén disponibles. |
| RF-RE-001 | BASE | Debe registrarse al menos un responsable vinculado al paciente cuando corresponda. |
| RF-RE-002 | BASE | Un usuario autorizado debe consultar y actualizar al responsable. |
| RF-RE-003 | CONDICIONAL | Deben admitirse múltiples responsables, tipos de representación y vigencias si el negocio lo confirma. |

## 7. Profesionales, especialidades, servicios y tarifas

| ID | Estado | Requisito |
|---|---|---|
| RF-PR-001 | BASE | Administración debe mantener profesionales de salud y sus credenciales registrales disponibles. |
| RF-PR-002 | BASE | Administración debe mantener especialidades activas/inactivas. |
| RF-SV-001 | BASE | Administración debe mantener servicios que puedan programarse y venderse. |
| RF-SV-002 | BASE | Debe asociarse un profesional a los servicios que puede prestar. |
| RF-TA-001 | BASE | Debe definirse el precio aplicable del servicio por profesional según las reglas vigentes. |
| RF-TA-002 | BASE | Debe poder registrarse una tarifa de reconsulta y una ventana de días. |
| RF-TA-003 | BASE | Deben poder aplicarse ajustes/tarifas adicionales autorizados. |
| RF-TA-004 | ESTÁNDAR | El precio utilizado en una operación debe conservarse históricamente aunque cambie la tarifa futura. |
| RF-TA-005 | ESTÁNDAR | El sistema debe evitar ofertas duplicadas o contradictorias para el mismo profesional/servicio/vigencia. |
| RF-TA-006 | CONDICIONAL | Elegibilidad y precio de reconsulta deben seguir la regla empresarial que se defina posteriormente. |
| RF-TA-007 | PROVISIONAL | La vigencia gratuita de reevaluación debe poder definirse por profesional + servicio o una política equivalente flexible, tomando `doctor_services.dias_reconsulta` solo como antecedente. |

## 8. Agenda, disponibilidad y citas

| ID | Estado | Requisito |
|---|---|---|
| RF-AG-001 | BASE | Un usuario autorizado debe registrar y modificar horarios de profesionales. |
| RF-AG-002 | BASE | El sistema debe mostrar agenda y citas en calendario con filtros relevantes. |
| RF-AG-003 | BASE | El sistema debe calcular horarios disponibles considerando agenda y citas ocupadas. |
| RF-AG-004 | ESTÁNDAR | Deben distinguirse disponibilidad recurrente, excepciones y bloqueos puntuales. |
| RF-AG-005 | ESTÁNDAR | No deben existir horarios incompatibles/solapados para el mismo recurso cuando la regla no lo permita. |
| RF-CI-001 | BASE | Un usuario autorizado debe crear una cita para paciente, profesional, servicio, fecha y hora. |
| RF-CI-002 | BASE | Debe impedirse una duplicidad incompatible de cita para paciente/profesional/slot. |
| RF-CI-003 | ESTÁNDAR | Dos usuarios concurrentes no deben reservar el mismo recurso/slot. |
| RF-CI-004 | BASE | Un usuario autorizado debe consultar y modificar/reprogramar la cita. |
| RF-CI-005 | BASE | La cita debe conservar motivo, observación, duración y estado administrativo. |
| RF-CI-006 | ESTÁNDAR | Cancelación, reprogramación y no-asistencia deben conservar motivo y actor. |
| RF-CI-007 | CONFIRMADO | Deben distinguirse disponibilidad, pre-reserva temporal y cita confirmada. |
| RF-CI-008 | CONFIRMADO | Una solicitud por WhatsApp, llamada, red social u otro canal debe poder originar una pre-reserva temporal. |
| RF-CI-009 | CONFIRMADO | Como regla general, la cita se confirma al acreditar un adelanto del 50 %; ante competencia por el mismo horario tiene prioridad quien realiza el abono requerido. |
| RF-CI-010 | CONFIRMADO | Una excepción al adelanto solo puede aprobarla una persona autorizada y debe conservar autorizador, motivo y fecha/hora. |
| RF-CI-011 | PROVISIONAL | La pre-reserva debe vencer a los 15 minutos por defecto y pasar a LIBERADA si no existe adelanto validado. |
| RF-CI-012 | ESTÁNDAR | La duración debe ser configurable y no quedar incorporada como constante dispersa en la implementación. |
| RF-CI-013 | PROVISIONAL | Un usuario con permiso puede extenderla; se registrarán usuario, fecha/hora, vencimiento anterior, nuevo vencimiento y motivo cuando corresponda. |

## 9. Atención y llamador

| ID | Estado | Requisito |
|---|---|---|
| RF-AT-001 | CONFIRMADO | Debe registrarse el flujo cita, llegada, espera, llamado a consultorio, en consulta, atención y finalización. |
| RF-AT-002 | ESTÁNDAR | Las transiciones de atención deben validar su secuencia, capacidad del actor y fecha/hora. |
| RF-AT-003 | CONFIRMADO | El ERP debe mantener una historia clínica electrónica longitudinal vinculada con las atenciones del paciente. |
| RF-AT-004 | CONFIRMADO | La historia debe poder incorporar evolución, diagnóstico, indicaciones, órdenes, recetas, resultados y otros componentes cuyo detalle se definirá posteriormente. |
| RF-AT-005 | ESTÁNDAR | El contenido clínico debe conservar autoría, integridad, confidencialidad, momento y contexto de la atención. |
| RF-AT-006 | CONFIRMADO | Una reevaluación debe vincularse a la atención/cita original y normalmente no generar un nuevo cobro. |
| RF-AT-007 | CONDICIONAL | La ventana gratuita y demás reglas de elegibilidad de reevaluación serán definidas por negocio; el horario habitual no será una regla fija. |
| RF-AT-008 | CONFIRMADO | El profesional debe disponer de un Área de Trabajo Médico que reúna su agenda, pacientes en espera/llamados/en consulta, atenciones pendientes, reevaluaciones y acceso a historia. |
| RF-AT-009 | CONFIRMADO | Al seleccionar un paciente, el profesional debe poder consultar contexto relevante e historia longitudinal según permisos y especialidad. |
| RF-HC-001 | CONFIRMADO | Cada registro clínico debe identificar paciente, atención, profesional responsable y fecha/hora. |
| RF-HC-002 | ESTÁNDAR | Un registro clínico cerrado o firmado debe corregirse mediante una adenda trazable sin sustituir silenciosamente el original. |
| RF-HC-003 | PROVISIONAL | Médicos tratantes crean y cierran sus propias atenciones; la primera etapa prioriza escritura clínica para médicos. |
| RF-HC-004 | PROVISIONAL | Profesionales clínicos autorizados consultan la historia longitudinal según permisos y contexto asistencial. |
| RF-HC-005 | PROVISIONAL | Personal administrativo no modifica contenido clínico, aunque tenga funciones administrativas elevadas. |
| RF-HC-006 | ESTÁNDAR | Órdenes, resultados, informes y adjuntos deben conservar origen, estado, autor y relación con la atención correspondiente. |
| RF-HC-007 | PROVISIONAL | El núcleo inicial contempla contexto del paciente, antecedentes, alergias, cronología, motivo, evolución/examen, diagnósticos, indicaciones, tratamiento, recetas, órdenes, resultados, informes/adjuntos, reevaluaciones y documentos generados. |
| RF-HC-008 | PROVISIONAL | Una atención cerrada no se sobrescribe; la adenda conserva autor, fecha/hora, motivo y contenido anterior cuando corresponda. |
| RF-HC-009 | PROVISIONAL | El acceso de emergencia exige motivo y audita usuario, paciente, fecha/hora y acción realizada. |
| RF-HC-010 | CONDICIONAL | Participación de enfermería y otros profesionales, plantillas avanzadas, integración profunda con laboratorio/imágenes y alertas avanzadas son evolución. |
| RF-HC-011 | CONDICIONAL | Contenido obligatorio y vocabularios por especialidad serán definidos clínicamente antes de implementar cada componente estructurado. |
| RF-HC-012 | PROVISIONAL | El profesional debe ejecutar una acción explícita para FINALIZAR/CERRAR la atención. |
| RF-HC-013 | PROVISIONAL | Una reapertura excepcional exige permiso clínico especial, motivo obligatorio y auditoría completa. |
| RF-HC-014 | ESTÁNDAR | El cierre/validación institucional se trata separadamente de una eventual firma electrónica o digital avanzada. |
| RF-HC-015 | PROVISIONAL | El modelo será híbrido: contexto, alergias, antecedentes, diagnósticos, medicamentos/recetas, órdenes, resultados, reevaluaciones y estados se estructuran cuando corresponda; motivo, anamnesis, examen/evolución, impresión, plan e indicaciones pueden comenzar como narrativa. |
| RF-LL-001 | CONFIRMADO | El flujo de atención debe quedar preparado funcionalmente para integrarse en el futuro con `devceosalud/LLAMADOR-PACIENTE-CEO`. |
| RF-LL-002 | ESTÁNDAR | Una falla del llamador no debe perder ni cambiar indebidamente el estado canónico de la atención. |
| RF-LL-003 | CONDICIONAL | El contrato funcional y la responsabilidad de cada sistema se definirán después de auditar el repositorio del llamador. |
| RF-LL-004 | ESTÁNDAR | El ERP debe ser la fuente principal de los estados llegada, espera, llamado, en consulta y finalizada. |

## 10. Comercial, cargos y ventas

| ID | Estado | Requisito |
|---|---|---|
| RF-CO-001 | BASE | Un usuario autorizado debe buscar y seleccionar servicios, citas e ítems vendibles. |
| RF-CO-002 | BASE | Debe identificarse quién recibe el servicio y, cuando difiera, quién paga. |
| RF-CO-003 | BASE | La venta debe registrar líneas, cantidades, precios, impuestos, profesional y totales aplicables. |
| RF-CO-004 | ESTÁNDAR | Las líneas deben usar información validada por el sistema y conservar su snapshot histórico. |
| RF-CO-005 | ESTÁNDAR | La venta confirmada debe registrarse completa o no registrarse. |
| RF-CO-006 | CONDICIONAL | Descuentos, exoneraciones y cambios manuales de precio requerirán reglas de autorización. |
| RF-CO-007 | CONFIRMADO | El catálogo y los comprobantes deben distinguir producto o servicio y conservar cantidad y precio aplicado al momento de la venta. |
| RF-CA-001 | ESTÁNDAR | Todo importe por cobrar debe pertenecer a una obligación canónica con monto original y saldo. |
| RF-CA-002 | BASE | Deben admitirse pagos totales y el comportamiento parcial ya demostrado, sujeto a definición final. |
| RF-CA-003 | CONFIRMADO | El adelanto de una cita debe aplicarse trazablemente a la obligación correspondiente. |

## 11. Pagos y caja

| ID | Estado | Requisito |
|---|---|---|
| RF-PG-001 | BASE | Debe registrarse un pago por efectivo, tarjeta, Yape o Plin y su referencia cuando corresponda. |
| RF-PG-002 | BASE | Una operación debe admitir combinación de medios actualmente soportados. |
| RF-PG-003 | ESTÁNDAR | El pago debe afectar una obligación y actualizar su saldo de manera consistente. |
| RF-PG-004 | ESTÁNDAR | Un pago no debe duplicarse ante reintentos. |
| RF-PG-005 | CONDICIONAL | Devoluciones, extornos, vuelto y aplicación múltiple se regirán por la política comercial aprobada. |
| RF-PG-006 | CONFIRMADO | El pago debe conservar evidencia de la operación y permitir verificar su número o referencia. |
| RF-PG-007 | ESTÁNDAR | Debe conservarse quién verificó el pago, cuándo lo hizo y el resultado de la verificación. |
| RF-PG-008 | PROVISIONAL | El pago contempla monto, método, número de operación, evidencia, usuario verificador, fecha/hora y estado de verificación. |
| RF-PG-009 | PROVISIONAL | RECEPCIÓN y/o CAJA tendrán la responsabilidad operativa de verificar pagos según la matriz definitiva. |
| RF-PG-010 | CONDICIONAL | Negocio definirá criterios de verificación, conservación de evidencia y distribución definitiva entre RECEPCIÓN y CAJA. |
| RF-CJ-001 | BASE | Un usuario autorizado debe abrir una caja con un monto inicial. |
| RF-CJ-002 | ESTÁNDAR | No debe abrirse más de un turno incompatible para la misma caja o usuario. |
| RF-CJ-003 | BASE | Deben registrarse ingresos y egresos manuales vinculados al turno. |
| RF-CJ-004 | BASE | El sistema debe calcular el efectivo esperado a partir de apertura, pagos y movimientos. |
| RF-CJ-005 | BASE | El usuario autorizado debe cerrar el turno registrando contado, diferencia y observación. |
| RF-CJ-006 | ESTÁNDAR | Un turno cerrado debe conservar su composición y cualquier corrección debe quedar trazada. |
| RF-CJ-007 | CONDICIONAL | Reapertura, diferencias máximas y aprobaciones dependerán de política de negocio. |

## 12. Comprobantes, series y facturación

| ID | Estado | Requisito |
|---|---|---|
| RF-CP-001 | BASE | La operación debe generar un comprobante interno con serie, correlativo, cliente, líneas y totales. |
| RF-CP-002 | BASE | El sistema debe asignar correlativos únicos aun con operaciones concurrentes. |
| RF-CP-003 | BASE | Un usuario autorizado debe obtener una representación imprimible. |
| RF-CP-004 | ESTÁNDAR | La representación debe identificar claramente si es documento interno o tributario y su estado real. |
| RF-CP-005 | ESTÁNDAR | La numeración no debe reutilizarse silenciosamente tras error/anulación. |
| RF-FT-001 | PROVISIONAL | El diseño debe preparar BOLETA y FACTURA y conservar el ciclo fiscal completo cuando se implemente emisión electrónica. |
| RF-FT-002 | CONDICIONAL | Notas, detracciones, contingencia y bajas seguirán reglas validadas con contabilidad. |
| RF-FT-003 | ESTÁNDAR | Imprimir o marcar un estado local no debe considerarse aceptación fiscal. |

## 13. Inventario

| ID | Estado | Requisito |
|---|---|---|
| RF-IN-001 | BASE | Los productos vendidos deben estar identificados como tales dentro del catálogo. |
| RF-IN-002 | ESTÁNDAR | Una venta que afecte stock no debe producir saldo negativo no autorizado. |
| RF-IN-003 | CONFIRMADO | El sistema debe manejar stock de productos. |
| RF-IN-004 | PROVISIONAL | Toda variación de stock debe generar un movimiento/kardex con origen, cantidad, actor y fecha; el saldo no será la única fuente de trazabilidad. |
| RF-IN-006 | PROVISIONAL | El diseño debe admitir sede → almacén → movimientos → stock, aunque el primer despliegue use un almacén lógico. |
| RF-IN-005 | CONDICIONAL | Lotes, vencimientos, compras y proveedores solo se incluyen con alcance de farmacia/insumos completo. |
| RF-IN-007 | CONDICIONAL | Transferencias entre almacenes, lotes, vencimientos, farmacia, compras y proveedores son evolución futura. |

## 14. Integraciones y comunicaciones

| ID | Estado | Requisito |
|---|---|---|
| RF-IG-001 | BASE | El sistema debe consultar identidad por DNI y permitir continuar controladamente si el proveedor falla. |
| RF-IG-002 | BASE | El sistema debe consultar RUC para completar datos del pagador empresarial. |
| RF-IG-003 | ESTÁNDAR | La respuesta externa debe validarse antes de usarse y no debe registrarse íntegra en logs. |
| RF-IG-004 | ESTÁNDAR | Ninguna prueba o entorno local debe efectuar comunicaciones externas reales. |
| RF-IG-005 | CONFIRMADO | La consulta DNI debe conservarse para autocompletar datos y siempre permitir revisión e ingreso manual. |
| RF-IG-006 | ESTÁNDAR | Deben distinguirse no encontrado, proveedor indisponible, timeout y respuesta inválida. |
| RF-IG-007 | ESTÁNDAR | Las consultas DNI/RUC deben validar entrada, limitar solicitudes y no exponer datos personales en logs o consola. |
| RF-RU-001 | BASE | La consulta RUC debe completar datos del cliente empresarial sin considerarse emisión tributaria. |
| RF-RU-002 | CONDICIONAL | Contabilidad definirá el tratamiento de contribuyentes con estado o condición observados. |
| RF-NT-001 | CONDICIONAL | Correo/SMS deben enviarse solo para eventos, destinatarios y consentimientos aprobados. |
| RF-NT-002 | ESTÁNDAR | Los fallos de notificación deben poder reintentarse sin duplicar mensajes ni revertir la transacción principal. |
| RF-SU-001 | CONFIRMADO | El TO-BE debe preservar la posibilidad de emisión electrónica real sin acoplarla a ventas, caja o consulta RUC. |
| RF-SU-002 | ESTÁNDAR | Generación, firma, envío, respuesta y procesamiento de CDR deben ser trazables e idempotentes. |
| RF-SU-003 | ESTÁNDAR | Un documento no debe presentarse como electrónico aceptado por imprimirlo o asignarle únicamente un estado local. |
| RF-SU-004 | CONDICIONAL | El alcance definitivo de correctivos, OSE/proveedor, notas, bajas, contingencia y puntos de emisión se validará posteriormente con contabilidad. |
| RF-SU-005 | PROVISIONAL | El diseño debe preparar BOLETA, FACTURA y mecanismos de corrección/anulación, incluyendo capacidad futura para notas, bajas, rechazos y contingencia. |
| RF-SU-006 | PROVISIONAL | Rechazos, notas, anulaciones y otras acciones fiscales sensibles requieren capacidades financieras/administrativas específicas; operar caja no las concede automáticamente. |

## 15. IA asistiva futura

| ID | Estado | Requisito |
|---|---|---|
| RF-IA-001 | CONDICIONAL | Podrán evaluarse resúmenes, alertas, organización documental, detección de inconsistencias, sugerencias y borradores clínicos. |
| RF-IA-002 | CONFIRMADO | El profesional conserva siempre la decisión clínica y debe revisar, aceptar, modificar o descartar toda sugerencia. |
| RF-IA-003 | ESTÁNDAR | Una salida de IA no debe escribirse automáticamente como decisión clínica definitiva ni confundirse con contenido firmado. |
| RF-IA-004 | ESTÁNDAR | Origen, revisión y aceptación deben ser auditables cuando la sugerencia se incorpore al registro. |
| RF-IA-005 | ESTÁNDAR | La operación clínica esencial debe continuar si la IA no está disponible. |

## 16. Auditoría y reportes

| ID | Estado | Requisito |
|---|---|---|
| RF-AU-001 | ESTÁNDAR | Deben auditarse cambios de acceso, pacientes sensibles, agenda, atención, precios, caja, ventas, pagos y documentos. |
| RF-AU-002 | ESTÁNDAR | La auditoría debe conservar actor, momento, operación, referencia y motivo aplicable sin exponer secretos. |
| RF-RP-001 | BASE | El sistema debe ofrecer una vista operativa de citas y agenda del día. |
| RF-RP-002 | CONDICIONAL | Los reportes futuros deben calcularse desde fuentes canónicas y respetar ámbito/autorización. |
| RF-RP-003 | CONDICIONAL | Indicadores gerenciales se definirán después de estabilizar datos y responder prioridades de negocio. |

## 17. Reglas pendientes que no deben asumirse

- Avisos, límite de extensiones y excepciones adicionales de pre-reserva.
- Lista definitiva de roles/personas que autorizan confirmación sin adelanto.
- Criterios y conservación de evidencia de pago y distribución final entre RECEPCIÓN/CAJA.
- Restricciones adicionales para información clínica especialmente sensible, reglas de break glass y participación de enfermería/otros profesionales.
- Vocabularios clínicos concretos por especialidad.
- Reglas de llamado, prioridad, ausencia y reasignación de consultorio.
- Número exacto de días de reevaluación por profesional + servicio y excepciones.
- Cantidad inicial de almacenes y alcance de farmacia, lotes, vencimientos, compras, proveedores y transferencias.
- Reglas de operación compartida o separada entre futuras sedes.
- Autorizaciones de descuentos, anulaciones, devoluciones y reaperturas.
- Documentos SUNAT, afectaciones y puntos de emisión.
- Contrato del llamador y notificaciones.
- Validación contable definitiva, proveedor/OSE, detalle del ciclo de notas, bajas y contingencia SUNAT.
- Indicadores/reportes prioritarios.

## 18. Exclusiones de esta fase

Esta especificación no define:

- estructuras de base de datos;
- nombres de clases o servicios;
- rutas, APIs o payloads;
- pantallas o componentes;
- diseño visual;
- proveedor SUNAT/SMS/correo definitivo;
- orden de migrations o estrategia física de despliegue.
