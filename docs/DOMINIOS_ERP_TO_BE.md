# Dominios funcionales propuestos para el ERP CEO Salud

## 1. Criterio de organización

El ERP se propone como un conjunto de dominios funcionales dentro de un mismo producto. Los límites sirven para asignar responsabilidad y evitar fuentes de verdad duplicadas; no implican microservicios ni diseño de clases o tablas.

Cada dominio distingue **Confirmado por negocio**, **Propuesta funcional aprobada provisionalmente**, **Confirmado en código**, **Referencia externa**, **Propuesta técnica**, **Pendiente de negocio** y **Pendiente de validación productiva**. “Existe hoy” describe únicamente el AS-IS y no prueba que la capacidad sea completa.

## 2. Mapa general

```text
IDENTIDAD Y SEGURIDAD ───────────────→ AUDITORÍA
          ↓                                ↑
ORGANIZACIÓN ─→ PERSONAL ─→ PROFESIONALES       │
     ↓                         ↓                │
PACIENTES ─→ AGENDA ─→ ATENCIÓN / HISTORIA CLÍNICA
                  │              ↑             │
                  └────→ LLAMADOR              │

COMERCIAL ─→ PAGOS ─→ CAJA                  │
    │           └────→ FACTURACIÓN ─────────┘
    └→ INVENTARIO

INTEGRACIONES conectan DNI/RUC, llamador, SUNAT y futuras fuentes
clínicas sin sustituir las fuentes canónicas del ERP.

REPORTES consumen información autorizada de todos los dominios.
```

## 3. Dominios

### 3.1 Identidad y seguridad

- **Responsabilidad:** autenticar cuentas, autorizar capacidades y conservar la identidad del actor.
- **Entidades funcionales:** usuario, rol, permiso/capacidad, sesión, ámbito de acceso.
- **Dependencias:** persona, trabajador y organización.
- **Existe hoy:** login/logout, usuarios, Spatie roles/permisos y matriz provisional backend.
- **Confirmado por negocio:** todo trabajador que opere el ERP tendrá acceso según corresponda; la autorización será granular por módulo y operación sensible.
- **Propuesta funcional aprobada provisionalmente:** administrar el ERP no otorga capacidad clínica; autorización de excepción al adelanto y verificación de pagos son permisos separados.
- **Propuesta técnica:** separar autenticación, vínculo laboral y autorización, y preservar la identidad histórica del actor.
- **Pendiente de negocio:** matriz definitiva de roles/capacidades y autorizadores de excepciones.

### 3.2 Organización

- **Responsabilidad:** representar la estructura desde la que opera CEO Salud.
- **Entidades funcionales:** empresa, sede, consultorio/recurso, caja, punto de emisión y almacén cuando apliquen.
- **Dependencias:** personal, agenda, caja, inventario, facturación y reportes.
- **Existe hoy:** una sola sede operativa y caja física aislada; no hay organización explícita en el sistema.
- **Confirmado por negocio:** el TO-BE se prepara para múltiples sedes; consultorios, cajas, puntos de emisión, almacenes, horarios y otros recursos podrán asociarse a una sede.
- **Propuesta técnica:** tratar la sede como ámbito organizacional desde el modelo funcional, sin activar complejidad multisede innecesaria mientras exista una sola.
- **Propuesta funcional aprobada provisionalmente:** la historia clínica es única entre sedes y cada atención conserva su contexto organizacional.
- **Pendiente de negocio:** recursos, numeraciones y procesos no clínicos compartidos o independientes cuando abra la segunda sede.

### 3.3 Personal

- **Responsabilidad:** identificar a quienes trabajan o colaboran con CEO Salud y su vigencia organizacional.
- **Entidades funcionales:** persona, trabajador, cargo, vínculo, asignación organizacional.
- **Dependencias:** identidad, organización, profesionales, auditoría.
- **Existe hoy:** usuarios y médicos separados; no existe trabajador.
- **Confirmado por negocio:** trabajador, usuario y profesional de salud son conceptos separados aunque relacionados; quienes operen el ERP tendrán usuario.
- **Propuesta técnica:** permitir que una persona reúna perfiles sin fusionar sus ciclos de vigencia ni responsabilidades.
- **Pendiente de negocio:** tipos de vínculo, profesionales externos, múltiples cargos y asignación a sedes.

### 3.4 Pacientes

- **Responsabilidad:** administrar identidad del paciente, contactos, número de historia, responsables y datos administrativos.
- **Entidades funcionales:** persona, paciente, identificador, contacto, responsable/relación, canal de captación.
- **Dependencias:** identidad externa DNI, agenda, atención, comercial y privacidad.
- **Existe hoy:** CRUD, búsqueda, número de historia, responsable, canal y medio.
- **Falta:** correlativo seguro, deduplicación, múltiples responsables con vigencia y geografía integrada.
- **Pendiente de negocio:** reglas de responsable y alcance de datos/consentimientos; la unicidad y trazabilidad son propuestas técnicas.

### 3.5 Agenda

- **Responsabilidad:** administrar oferta de tiempo, recursos, disponibilidad y reserva de citas.
- **Entidades funcionales:** plantilla de agenda, excepción/bloqueo, recurso, slot disponible, pre-reserva temporal, cita y canal de solicitud.
- **Dependencias:** organización, profesionales, especialidades, servicios y pacientes.
- **Existe hoy:** horarios por fecha, calendario, cálculo de slots y citas.
- **Confirmado por negocio:** solicitudes por WhatsApp, llamadas, redes sociales u otros canales pueden generar pre-reservas; la cita se confirma normalmente con 50 % de adelanto y quien abona primero tiene prioridad sobre el horario.
- **Propuesta funcional aprobada provisionalmente:** la pre-reserva dura 15 minutos por defecto, debe ser configurable, expira a LIBERADA y puede extenderse manualmente con permiso y auditoría.
- **Propuesta técnica:** controlar vencimiento y concurrencia de la pre-reserva de forma trazable para evitar doble asignación.
- **Pendiente de negocio:** avisos, límite de extensiones, cancelación, reprogramación y devolución.

### 3.6 Atención

- **Responsabilidad:** proporcionar el Área de Trabajo Médico y registrar el tránsito operativo y el acto asistencial, incluida la historia clínica longitudinal.
- **Entidades funcionales:** lista de trabajo, llegada, cola, llamado, atención/encuentro, historia clínica, registro clínico, orden, resultado, documento y reevaluación/reconsulta.
- **Dependencias:** cita, profesional, paciente, llamador, auditoría y posibles órdenes/resultados externos.
- **Existe hoy:** estados y cuatro timestamps dentro de `appointments`; no hay flujo completo.
- **Confirmado por negocio:** el ERP incluirá historia clínica electrónica completa y el flujo cita, llegada, espera, llamado, en consulta, atención y finalización; una reevaluación deriva de la atención original y normalmente no genera cobro.
- **Confirmado por negocio:** el profesional dispondrá de un área clínica integrada con pacientes del día, contexto completo, historia, registro, solicitudes, documentos y pendientes según autorización.
- **Referencia externa:** HOSIX aporta únicamente el concepto general de Área de Trabajo Médico, lista de trabajo e información clínica integrada; no define la solución de CEO Salud.
- **Propuesta funcional aprobada provisionalmente:** médicos tratantes crean y cierran sus atenciones; otros profesionales clínicos autorizados consultan por contexto; la escritura inicial se prioriza para médicos.
- **Propuesta funcional aprobada provisionalmente:** administración no modifica contenido clínico, una atención cerrada solo se corrige mediante adenda trazable y existe acceso excepcional de emergencia auditado.
- **Propuesta funcional aprobada provisionalmente:** el cierre es una acción explícita del profesional; una reapertura exige permiso clínico especial, motivo y auditoría, sin implicar todavía firma digital avanzada.
- **Propuesta funcional aprobada provisionalmente:** la HCE combina información estructurada con narrativa clínica libre y mantiene una única historia longitudinal multisede.
- **Propuesta técnica:** separar el estado operativo de atención del contenido clínico firmado, conservando autoría, integridad, trazabilidad y adendas.
- **Pendiente de negocio:** datos especialmente sensibles, enfermería/otros profesionales, reglas de break glass, días exactos de reevaluación y contrato futuro con el llamador.

### 3.7 Comercial

- **Responsabilidad:** administrar catálogo vendible, precios, venta, cargos y saldos.
- **Entidades funcionales:** servicio/producto, lista o regla de precios, venta, línea, cargo/obligación, cliente/pagador.
- **Dependencias:** pacientes, profesionales, atención, caja, inventario y facturación.
- **Existe hoy:** servicios, items, tarifas, carrito, vouchers y saldos parciales.
- **Confirmado por negocio:** se venderán servicios y productos; cada línea de comprobante identificará tipo, cantidad y precio aplicado al vender.
- **Confirmado por negocio:** confirmar una cita requiere normalmente 50 % de adelanto, con excepciones autorizadas y trazables.
- **Propuesta funcional aprobada provisionalmente:** RECEPCIÓN y/o CAJA verificarán pagos según la matriz definitiva; verificación y excepción al adelanto no se heredan entre sí.
- **Propuesta técnica:** conservar una fuente canónica de deuda y el snapshot histórico de cada línea y precio.
- **Pendiente de negocio:** crédito, devoluciones, autorizadores de excepción y reglas de precios/descuentos.

### 3.8 Caja

- **Responsabilidad:** custodiar y conciliar fondos por caja y turno.
- **Entidades funcionales:** caja, turno, apertura, movimiento, arqueo, diferencia y reverso.
- **Dependencias:** organización, usuarios, pagos, ventas y auditoría.
- **Existe hoy:** apertura/cierre, cálculo de efectivo y movimientos.
- **Falta:** concurrencia segura, arqueo por medios, inmutabilidad/reversos y aprobación de excepciones.
- **Pendiente de negocio:** responsables de apertura/cierre/anulación y tratamiento de diferencias/reaperturas.

### 3.9 Facturación

- **Responsabilidad:** generar y custodiar documentos internos y tributarios, numeración y ciclo fiscal.
- **Entidades funcionales:** comprobante interno, documento tributario, serie, correlativo, nota, estado fiscal y representación.
- **Dependencias:** organización, comercial, cliente/pagador, catálogo fiscal, pagos, integraciones y storage.
- **Existe hoy:** tipos de voucher, series, correlativo bloqueado, campos SUNAT e impresión.
- **Confirmado en código:** existe comprobante interno con cabecera, líneas, pagos, series/correlativos, representación impresa y campos preparatorios SUNAT.
- **Falta:** emisión electrónica real, generación XML/UBL, firma, envío, CDR, reintentos, contingencia y bajas.
- **Propuesta técnica:** preservar el snapshot comercial/fiscal y separar consulta RUC, comprobante interno y documento tributario electrónico.
- **Propuesta funcional aprobada provisionalmente:** preparar BOLETA, FACTURA y corrección/anulación, considerando notas de crédito, otros correctivos, bajas, rechazos y contingencia.
- **Propuesta funcional aprobada provisionalmente:** las acciones fiscales sensibles exigen permisos específicos y no se derivan del rol de caja.
- **Pendiente de negocio:** validación contable definitiva, puntos de emisión y modalidad de integración.

### 3.10 Inventario

- **Responsabilidad:** controlar existencias y movimientos de los productos vendidos.
- **Entidades funcionales:** producto inventariable, almacén, movimiento/kardex, saldo; lote/vencimiento si aplica.
- **Dependencias:** organización, comercial, compras/proveedores potenciales y auditoría.
- **Existe hoy:** `stock_actual`, `stock_minimo` y decremento en venta.
- **Confirmado por negocio:** el ERP manejará stock y el catálogo deberá poder evolucionar a un alcance mayor.
- **Propuesta funcional aprobada provisionalmente:** el diseño base sigue sede → almacén → movimientos → stock; el primer despliegue puede usar un almacén lógico.
- **Propuesta técnica:** el kardex es fuente de trazabilidad y el saldo no debe ser la única evidencia.
- **Pendiente de negocio:** alcance de farmacia, lotes, vencimientos, compras, proveedores y transferencias entre almacenes.

### 3.11 Integraciones

- **Responsabilidad:** aislar proveedores externos y coordinar intercambios confiables sin convertirlos en fuentes paralelas de verdad.
- **Entidades funcionales:** consulta DNI, consulta RUC, emisión tributaria, llamado, solicitud, proveedor, resultado, intento, evento pendiente y estado de entrega.
- **Dependencias:** pacientes, comercial, facturación, atención, historia clínica y comunicaciones.
- **Confirmado en código:** consultas DNI/RUC, configuración residual SMS, correo comentado y campos preparatorios de llamador/SUNAT.
- **Confirmado por negocio:** existe intención de integrar el ERP con `devceosalud/LLAMADOR-PACIENTE-CEO` a lo largo del flujo operativo de atención.
- **Propuesta técnica:** mantener el estado canónico en el ERP, conservar ingreso manual y aislar contratos externos con validación, idempotencia, timeouts, reintentos y mínima exposición de datos.
- **Pendiente de validación productiva:** funcionamiento, proveedor y formato reales de DNI/RUC; emisión SUNAT no demostrada.
- **Pendiente de negocio:** contrato funcional con el llamador, proveedores/canales aprobados y modalidad SUNAT.

### 3.12 Auditoría

- **Responsabilidad:** demostrar quién hizo qué, cuándo, por qué y sobre qué operación sensible.
- **Entidades funcionales:** evento auditable, actor, motivo, referencia y cambio relevante.
- **Dependencias:** todos los dominios transaccionales.
- **Existe hoy:** timestamps y usuario en algunas tablas; no hay bitácora funcional.
- **Falta:** eventos para seguridad, agenda, atención, precios, caja, venta, pago y facturación.
- **Propuesta funcional aprobada provisionalmente:** auditar acceso de emergencia, adendas clínicas, extensiones de pre-reserva, verificación de pagos y excepciones al adelanto.
- **Pendiente de negocio:** plazos de conservación y responsables de consulta. Registrar operaciones críticas es una propuesta técnica necesaria, pero su alcance regulatorio debe confirmarse.

### 3.13 Reportes

- **Responsabilidad:** ofrecer información operativa y gerencial consistente con las fuentes canónicas.
- **Entidades funcionales:** indicador, dimensión, periodo y criterio de cálculo.
- **Dependencias:** organización, agenda, atención, comercial, caja, facturación, inventario y auditoría.
- **Existe hoy:** dashboard de agenda diaria.
- **Falta:** catálogo de indicadores, cierres, producción, cobranza, caja, inventario y gerencia.
- **Pendiente de negocio:** indicadores prioritarios, periodicidad y destinatarios.

## 4. Reglas de dependencia propuestas

1. Identidad no debe contener datos laborales o clínicos.
2. Organización define ámbitos, pero no ejecuta operaciones de agenda/caja/facturación.
3. Agenda reserva; Atención registra lo realizado.
4. Comercial genera venta/cargo; Caja registra custodia; Pago extingue obligación; Facturación documenta fiscalmente.
5. Inventario solo reacciona a operaciones aprobadas y conserva movimientos.
6. Integraciones no son fuente primaria de la transacción local.
7. Auditoría observa operaciones; no reemplaza sus estados.
8. Reportes leen fuentes canónicas y no corrigen datos transaccionales.
9. Consulta de RUC, comprobante interno y emisión SUNAT son capacidades diferentes.
10. IA futura solo propone apoyo; el profesional valida y conserva la decisión clínica.

## 5. Efecto de las decisiones confirmadas y dependencias pendientes

- **Confirmado por negocio:** organización preparada para múltiples sedes y asociación futura de recursos.
- **Confirmado por negocio:** trabajador, usuario y profesional separados, con autorización granular.
- **Confirmado por negocio:** historia clínica electrónica completa y flujo operativo de atención integrable con el llamador.
- **Confirmado por negocio:** disponibilidad, pre-reserva y cita confirmada con adelanto general del 50 %, evidencia y excepción autorizada.
- **Confirmado por negocio:** reevaluación vinculada normalmente sin cobro, y venta de productos con manejo de stock.

El gobierno clínico, núcleo HCE, historia única multisede, cierre explícito, modelo híbrido, pre-reserva, separación de autorizaciones, política de reevaluación, base de inventario y preparación tributaria quedaron resueltos provisionalmente. Permanecen pendientes los detalles clínicos, contables y operativos señalados por dominio.

## 6. Diseño evolutivo transversal

- **Confirmado por negocio:** la HCE debe poder incorporar progresivamente antecedentes, alergias, problemas, recetas, órdenes, resultados, informes, imágenes y documentos sin asumir que todo estará en la primera versión.
- **Confirmado por negocio:** la IA clínica es candidata futura y nunca sustituye la decisión profesional.
- **Propuesta técnica:** duración de pre-reserva, días de reevaluación, políticas de adelanto, autorizadores, permisos, sedes, consultorios, series y puntos de emisión deben poder evolucionar sin quedar dispersos como reglas rígidas.
- **Propuesta técnica:** esto no justifica construir ahora un motor genérico de configuración; solo preservar límites de dominio y registrar el motivo de las decisiones.
