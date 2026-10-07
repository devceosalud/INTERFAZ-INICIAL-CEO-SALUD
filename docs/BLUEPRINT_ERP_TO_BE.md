# Blueprint maestro ERP CEO Salud — TO-BE propuesto

## 1. Naturaleza de este documento

Este es un diseño técnico de alto nivel. No modifica el sistema ni afirma requisitos que CEO Salud todavía no confirmó.

- **CONFIRMADO:** hecho demostrado por el AS-IS o por decisiones ya adoptadas.
- **PROPUESTA TÉCNICA:** dirección recomendada, sujeta a aprobación.
- **PENDIENTE DE DECISIÓN DE NEGOCIO:** regla que no debe resolverse solo desde código.

## 2. Resultado objetivo

**PROPUESTA TÉCNICA:** evolucionar el monolito Laravel a un **monolito modular**. Mantener un solo despliegue y una sola base mientras se separan límites de dominio, transacciones y contratos. El tamaño actual no justifica microservicios; sí justifica evitar que citas, caja, ventas y documentos sigan escribiéndose desde el mismo componente.

Objetivos arquitectónicos:

1. Una sola fuente de verdad por concepto.
2. Reglas de negocio ejecutadas en backend y no confiadas al navegador.
3. Estados explícitos, transiciones autorizadas y auditables.
4. Efectos externos desacoplados, idempotentes y reintentables.
5. Seguridad por capacidad/operación además de navegación.
6. Evolución de datos con migración verificable y sin pérdida de historia.
7. Reportes derivados de transacciones canónicas, no de campos duplicados.

## 3. Límites de dominio propuestos

| Dominio | Responsabilidad propuesta | Punto de partida AS-IS |
|---|---|---|
| Identidad y acceso | Credenciales, roles, permisos, sesiones, ámbito organizacional. | `users` + Spatie. |
| Personas y organización | Persona, trabajador, vínculo laboral, sede, cargo. | No existe; médico y usuario están aislados. |
| Pacientes | Perfil asistencial/administrativo, identificadores, contactos y responsables. | `patients`, `responsibles`, geografía. |
| Profesionales | Profesional de salud, colegiatura, especialidades, servicios habilitados. | `doctors`, `specialties`, `doctor_services`. |
| Catálogo clínico/comercial | Servicios prestables y productos vendibles con reglas fiscales/precios. | `services`, `items`, `additional_rates`. |
| Agenda y disponibilidad | Plantillas, excepciones, recursos, slots y bloqueos. | `doctor_schedules` + cálculo en controladores. |
| Citas | Reserva, reprogramación, cancelación, no-show y fuente comercial. | `appointments`. |
| Atención | Llegada, cola, llamado, encuentro y finalización. | Estados/timestamps dentro de cita. |
| Ventas | Orden/venta y snapshots de líneas. | `vouchers`/`voucher_items` cumplen varias funciones. |
| Cobranza | Obligación, pagos, aplicaciones, devoluciones y deuda. | `appointments` + `payments` + estados de voucher. |
| Caja | Caja física, turnos, arqueos y movimientos/reversos. | `cashiers`, `cashier_shifts`, `cash_movements`. |
| Documentos tributarios | Documento fiscal, series, numeración, notas y ciclo SUNAT. | `voucher_series`, parte de `vouchers`. |
| Inventario | Stock por almacén, kardex, movimientos, lotes si aplican. | Solo saldo en `items`. |
| Integraciones | DNI/RUC, llamador, notificaciones, SUNAT y contratos externos. | Servicios directos y estructura parcial. |
| Auditoría y reporting | Evento de negocio, actor, antes/después y proyecciones. | No existe. |

## 4. Separaciones conceptuales obligatorias

### 4.1 Persona, trabajador, usuario y profesional

**CONFIRMADO:** hoy `users` solo tiene credenciales; `doctors` contiene nombre/CMP/RNE; no existe trabajador.

**PROPUESTA TÉCNICA:**

```text
Persona
 ├─ perfil de paciente (cuando recibe atención)
 ├─ vínculo de trabajador (cuando presta servicios a CEO Salud)
 │   └─ perfil de profesional de salud (cuando aplica)
 └─ usuario del sistema (cuando necesita acceso)
```

- Persona resuelve identidad, documentos y contacto.
- Trabajador resuelve relación organizacional, cargo, vigencia y sede.
- Usuario resuelve credenciales, acceso y estado de cuenta.
- Profesional resuelve CMP/RNE, especialidades y capacidad clínica.
- No todo trabajador necesita usuario y no todo usuario tiene que ser médico.

**PENDIENTE:** multisede, pertenencia múltiple, profesionales externos y política de cuentas.

### 4.2 Agenda, disponibilidad, cita y atención

**CONFIRMADO:** `doctor_schedules` mezcla día recurrente con fecha concreta; `appointments` mezcla reserva, atención y dinero.

**PROPUESTA TÉCNICA:**

```text
Plantilla de agenda + excepciones + recursos
                    ↓
              disponibilidad
                    ↓
                  cita
                    ↓ llegada
             cola/llamador
                    ↓
                atención
```

- Agenda define capacidad ofertable.
- Disponibilidad es una proyección calculada/reservable, no una tabla de citas libres necesariamente.
- Cita conserva la reserva y sus transiciones administrativas.
- Atención/encuentro registra el servicio efectivamente realizado y sus tiempos.
- Un constraint o estrategia de lock debe impedir dos reservas simultáneas del mismo recurso/slot.

**PROPUESTA de estados, no confirmada:**

- Cita: `BORRADOR → PROGRAMADA → CONFIRMADA → LLEGÓ`, con salidas `CANCELADA`, `NO_ASISTIÓ`, `REPROGRAMADA`.
- Cola: `EN_ESPERA → LLAMANDO → EN_CONSULTORIO → FINALIZADA`.
- Atención: creada al iniciar; `EN_CURSO → COMPLETADA/ANULADA`.

No se debe agregar `REEVALUACION` al enum sin decidir si significa tipo de cita, beneficio tarifario o estado.

### 4.3 Venta, obligación, pago, caja y documento tributario

**CONFIRMADO:** una cita puede crear TICKET/pago y también conserva total/saldo; `vouchers` mezcla ticket interno y documento fiscal.

**PROPUESTA TÉCNICA:**

```text
Cita/atención/producto
          ↓
      orden/venta ─────→ documento tributario
          ↓                      ↓
   cuenta por cobrar        ciclo SUNAT
          ↓
        pago ─────────────→ turno/caja
```

- Venta representa qué se vendió, a quién, cantidad, precio e impuestos acordados.
- Cuenta por cobrar representa monto exigible y saldo.
- Pago representa dinero recibido y su método; se aplica a una o varias obligaciones según regla confirmada.
- Caja representa custodia y arqueo, no el estado de la venta.
- Documento tributario representa la obligación fiscal y puede tener ciclo distinto al cobro.
- Un ticket interno no debe aparentar ser documento tributario.

**PENDIENTE:** pagos parciales, crédito, anticipos, devolución, anulación, cambio de comprobante, notas y atención antes de pago.

## 5. Modelo funcional objetivo

No es aún un diseño de tablas. Son agregados/conceptos que deberán convertirse en esquema después de responder las decisiones prioritarias.

### Identidad y organización

- Persona e identificadores.
- Trabajador, cargo, vigencia y asignación organizacional.
- Usuario, credenciales y estado.
- Rol/permiso con alcance global o por sede.
- Profesional de salud, colegiatura y especialidades.

### Paciente

- Perfil de paciente ligado a persona.
- Número de historia emitido por mecanismo transaccional y único.
- Contactos y direcciones estructuradas.
- Responsables/representantes con tipo, vigencia y relación.
- Consentimientos y tratamiento de PII, si negocio/legal lo exige.

### Oferta clínica y agenda

- Servicio clínico canónico.
- Oferta profesional-servicio con tarifa/vigencia.
- Lista de precios/reglas comerciales separada del vínculo profesional.
- Plantillas de disponibilidad, excepciones, bloqueos, feriados y recursos.
- Cita con versión/lock, historial de estado y motivo de cambio.
- Atención separada de cita.

### Comercial y finanzas

- Catálogo canónico con categoría “producto/servicio clínico/otro servicio”.
- Precio e impuesto versionados; snapshot en línea de venta.
- Venta/orden, cuenta por cobrar, aplicación de pago y devolución.
- Caja, turno, arqueo y movimiento inmutable/reversable.
- Documento interno y documento tributario separados o tipados con invariantes explícitas.
- Serie/correlativo por empresa/sede/punto/tipo, según decisión.

### Inventario

- Producto inventariable y unidad.
- Almacén/ubicación, movimiento y saldo derivado.
- Motivos: compra, venta, devolución, ajuste, traslado.
- Lote/serie/vencimiento solo si el alcance farmacia/insumos lo exige.

### Integraciones y trazabilidad

- Solicitud externa con proveedor, idempotency key, estado, intentos y respuesta sanitizada.
- Outbox/eventos para correo, SMS, llamador y SUNAT.
- Auditoría con actor, acción, entidad, motivo y snapshot mínimo seguro.
- Métricas y reportes sobre vistas/proyecciones de lectura.

## 6. Arquitectura de aplicación propuesta

### 6.1 Organización del código

```text
app/
  Domain/            reglas, estados y value objects por dominio
  Application/       casos de uso/acciones y DTOs
  Infrastructure/    Eloquent, HTTP providers, queue, storage
  Http/              controllers, requests, resources, middleware
  Livewire/          presentación; sin transacciones de negocio extensas
```

La transición puede ser gradual. No se propone mover todo el repositorio de una vez.

### 6.2 Patrón de caso de uso

**PROPUESTA TÉCNICA:** cada operación crítica debe tener una entrada backend única:

- `CreatePatient`
- `BookAppointment`
- `RescheduleAppointment`
- `RegisterArrival`
- `OpenCashierShift`
- `RecordPayment`
- `CreateSale`
- `IssueFiscalDocument`
- `ReverseCashMovement`

Controllers y Livewire validan/autorizan y delegan. El caso de uso recalcula precios, aplica locks, abre transacción y emite eventos.

### 6.3 Validación y autorización

- Form Requests/DTOs para contratos HTTP.
- Policies/capacidades por operación; roles solo agrupan capacidades.
- Scope por sede/caja cuando sea confirmado.
- Enums/clases de estado compartidos en PHP; el frontend consume catálogos del backend.
- Optimistic lock o versión en operaciones susceptibles a edición concurrente.

### 6.4 Transacciones e idempotencia

- Numeración y reserva de slot bajo transacción de MySQL probada.
- Idempotency key en cobros, emisión y callbacks externos.
- Unique constraints alineados a las invariantes de negocio.
- No eliminar transacciones financieras: usar reversos/anulaciones con actor/motivo.
- Outbox para que commit de negocio y publicación de evento no diverjan.

### 6.5 Integraciones

Definir puertos como `IdentityLookup`, `TaxpayerLookup`, `SmsGateway`, `MailGateway`, `CallerGateway`, `ElectronicBillingGateway`.

- Timeout corto, retry controlado y errores tipados.
- Fakes contractuales obligatorios en tests.
- Secrets solo en entorno/secret manager.
- Datos personales fuera de logs; registrar identificadores técnicos y resultado sanitizado.
- Queue para efectos reintentables, nunca para ocultar una transacción local incompleta.

### 6.6 Observabilidad y auditoría

- Correlation ID por request/caso de uso.
- Log estructurado sin PII/secrets.
- Eventos auditables para login sensible, cambio de rol, agenda, caja, venta, pago, anulación y SUNAT.
- Alertas de queue/SUNAT/correlativos y dashboard operativo.

## 7. Estrategia de datos

### Paso 1 — Perfilado sin modificar producción

- Conteos, nulls, duplicados y huérfanos por tabla mediante scripts read-only aprobados.
- Distribución de estados, documentos, series y saldos.
- Reconciliación: appointment ↔ voucher item ↔ voucher ↔ payments ↔ shift.
- Detección de `doctor_services` duplicados y solapes de horarios.

### Paso 2 — Contratos canónicos

- Acordar fuentes de verdad y reglas de transición.
- Mapear cada campo heredado a conservar, transformar, archivar o descartar.
- Definir identificadores inmutables y constraints.

### Paso 3 — Migración incremental

- Añadir estructuras nuevas solo después de aprobar diseño y respaldo.
- Backfill idempotente y verificable.
- Lectura comparada/dual temporal donde sea necesario.
- Cutover por módulo con reconciliación y rollback.
- Retirar campos temporales únicamente después de demostrar equivalencia.

## 8. Qué conservar, corregir o sustituir

### Conservar como base

- Laravel monolítico, Eloquent, Blade/Livewire y Spatie Permission.
- Esqueleto de pacientes, especialidades y relaciones principales.
- Concepto de doctor-service y snapshot de línea de venta.
- Transacciones y lock de serie como intención correcta.
- Suite aislada y fakes de efectos externos.
- Catálogos/seed importado como insumo para limpieza.

### Corregir antes de ampliar

- Carga duplicada de rutas y contratos API.
- Validación de IDs/estados, manejo de null/errores y paginación.
- Historia clínica, número de cita, apertura de caja y reserva de slot concurrentes.
- Relaciones Eloquent incorrectas y FKs/unique faltantes.
- UI de solo lectura por rol.
- Integraciones con timeouts, errores y contratos simulados.

### Rediseñar por límite de dominio

- Usuario/personal/médico.
- Agenda/disponibilidad/cita/atención/reconsulta.
- `services` frente a `items` y estrategia de precios.
- Venta/deuda/pago/caja/documento fiscal.
- Inventario real si se confirma su alcance.
- Permisos definitivos y auditoría.
- Reportes e indicadores.

### Retirar o aislar tras migración

- Campos financieros temporales de `appointments` cuando exista fuente canónica.
- Estados imposibles/ambiguos y código de reevaluación sin contrato.
- Etiquetas “electrónicas” hasta que exista comprobante aceptado o representación válida.
- Código comentado/debug/template no utilizado.

## 9. Candidatos a evaluar, no requisitos confirmados

| Candidato | Condición para incluirlo |
|---|---|
| Sedes, consultorios y puntos de venta | CEO Salud opera o planea operar más de una unidad/ubicación. |
| Personal/RRHH | El ERP administrará trabajadores más allá de sus usuarios. |
| Historia clínica | El alcance incluye acto médico, no solo gestión administrativa. |
| Laboratorio | Se gestionan órdenes, muestras, resultados y trazabilidad. |
| Farmacia | Se dispensan medicamentos con lote/vencimiento y regulación. |
| Compras/proveedores | CEO Salud necesita reabastecimiento y costo dentro del ERP. |
| Almacenes/kardex | El stock debe ser confiable y auditable por ubicación. |
| Cuentas por cobrar/pagar | Hay crédito, convenios, aseguradoras o proveedores. |
| Documentos/consentimientos | Se requiere expediente documental y firma/aceptación. |
| Reportes gerenciales | Se deben medir producción, ingresos, margen, agenda y cobranza. |
| Notificaciones omnicanal | Se confirman recordatorios, consentimientos y canales. |
| Facturación SUNAT | CEO Salud emitirá comprobantes desde este ERP. |

## 10. Estrategia de pruebas objetivo

- Unitarias para pricing, estados, saldos, impuestos y permisos.
- Integración MySQL desechable para FKs, enums, locks, unique y concurrencia.
- Feature para cada caso de uso positivo/negativo por capacidad.
- Contract tests para DNI, RUC, llamador, correo/SMS y SUNAT con fixtures, nunca tráfico real en CI.
- Pruebas de idempotencia de pagos/emisión y reconciliación.
- Browser tests para agenda, ventas, cierre e impresión.
- Smoke de despliegue sin migraciones automáticas destructivas.

## 11. Roadmap preliminar

### Etapa 1 — Riesgo inmediato

- Mantener aislamiento, seguridad provisional y rotación externa de credenciales históricas.
- Corregir errores que pueden producir corrupción: montos confiados al cliente, doble booking, aperturas concurrentes, stock negativo y divergencia de pagos.
- No ampliar funcionalidad sobre fuentes de verdad ambiguas.

### Etapa 2 — Decisiones y dependencias

- Resolver las 10 decisiones de negocio prioritarias.
- Aprobar glosario, límites, estados y permisos definitivos.
- Definir sedes/puntos/series antes de tocar organización o facturación.

### Etapa 3 — Datos

- Perfilar producción de forma read-only.
- Reconciliar pacientes, citas, tickets, vouchers, pagos y caja.
- Diseñar migración de persona/trabajador/profesional y catálogos canónicos.

### Etapa 4 — Núcleo funcional

- Casos de uso y auditoría.
- Agenda/disponibilidad/cita/atención.
- Venta/cobranza/pago/caja/documento interno.
- Inventario solo con alcance confirmado.

### Etapa 5 — Integraciones

- Contrato con llamador.
- Notificaciones y workers.
- Emisión SUNAT con sandbox/certificados/contingencia, si se confirma.
- Reportes sobre datos ya estabilizados.

### Etapa 6 — Experiencia de usuario

- Vistas específicas por capacidad.
- Formularios coherentes, mensajes de error y accesibilidad.
- Dashboards y flujos optimizados a partir de procesos ya aprobados.

## 12. Criterios para pasar del Blueprint a implementación

No debería empezar una refactorización estructural hasta contar con:

1. Respuesta a las decisiones 1–8 de `DECISIONES_NEGOCIO_PENDIENTES.md` o un alcance explícitamente excluido.
2. Glosario de cita, atención, reconsulta, venta, ticket, comprobante, pago y caja.
3. Matriz definitiva de capacidades por rol y ámbito.
4. Perfil read-only de los datos productivos y plan de reconciliación.
5. MySQL local/desechable para pruebas concurrentes.
6. Contratos/sandboxes de cualquier integración que se decida activar.
