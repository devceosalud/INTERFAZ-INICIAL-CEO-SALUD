# MVP de Agendamiento — delta mínimo de datos

## 1. Alcance

Este documento propone estructuras conceptuales; no contiene migrations ejecutables. El objetivo es evolucionar el modelo productivo de forma aditiva, conservar historia y evitar trasladar todo el MVP a `appointments`.

## 2. Principios

1. No eliminar ni renombrar columnas legacy durante el MVP.
2. No corregir migrations históricas para representar producción; crear cambios nuevos y explícitos.
3. No cambiar el enum `estado_cita` para representar tipo de agendamiento, hold, pago o autorización.
4. Separar estado operativo de la cita, tipo de agendamiento y estado financiero.
5. Las FK nuevas deben preferir `RESTRICT` o nulificación controlada; no repetir `CASCADE` sobre historia operativa.
6. Nuevas columnas sobre tablas pobladas nacen nullable, se backfillean de forma idempotente y solo después se evalúa endurecerlas.
7. Todo importe histórico conserva snapshot; no se recalcula desde tarifas actuales.
8. Fechas operativas se almacenan con precisión suficiente y se interpretan en `America/Lima`; no usar hora del navegador como fuente de verdad.

## 3. Estrategia sobre `appointments`

### 3.1 Campos que se conservan

Se conservan todos los campos productivos actuales, especialmente:

- `id`, `numero_cita` y timestamps;
- `user_id` como creador legacy;
- `patient_id`, `doctor_id`, `service_id`, `additional_rate_id`;
- `fecha_cita`, `hora_cita`, `duracion_cita`, `turno_cita`;
- `motivo_consulta`, `observaciones`, `fecha_registro`;
- `estado_cita` con su enum productivo confirmado;
- todos los campos financieros legacy.

No se agregan las cuatro horas del llamador al ERP solo para imitar `visorTemporal`. La integración futura tendrá contrato propio.

### 3.2 Columnas aditivas mínimas propuestas

| Columna conceptual | Motivo | Tipo/relación | Índice/constraint | Nullable/default | Historia y backfill | Rollback |
|---|---|---|---|---|---|---|
| `site_id` | sede canónica de la cita | FK a `sites` | índice `(site_id, fecha_cita, doctor_id)`; `RESTRICT` | nullable inicialmente, sin default implícito | asignar sede inicial solo tras validar que todos los registros pertenecen a ella | eliminar FK/columna si ninguna escritura nueva depende de ella |
| `responsible_user_id` | responsable actual distinto del creador | FK a `users` | índice para producción por responsable; `RESTRICT`/soft deactivation | nullable | no inferir automáticamente del canal; backfill provisional desde `user_id` solo si se aprueba y queda marcado | retirar columna conservando export de cambios si no se activó |
| `last_modified_by` | último actor humano conocido | FK a `users` | índice opcional; `SET NULL` ante caso excepcional | nullable | no reconstruir pasado; poblar prospectivamente | retirar sin alterar `updated_at` |
| `scheduling_version` | control optimista/idempotencia de edición | entero sin signo | comparación al actualizar | `0` | registros históricos inician en cero | retirar si no hay clientes que lo utilicen |

No se propone agregar a `appointments`: estado de hold, expiración, evidencia de pago, archivo, autorizaciones, historial de responsables, información de adicional ni bitácora JSON.

## 4. Nuevas estructuras

### 4.1 `sites`

Catálogo mínimo de sedes para no hardcodear la sede actual.

| Campo conceptual | Regla |
|---|---|
| `id`, `code`, `name` | `code` único y estable; nombre editable |
| `timezone` | default empresarial `America/Lima`, no supuesto global |
| `active` | default true; no borrar sedes usadas |
| timestamps | auditoría técnica |

- **Relaciones:** citas, horarios y futuras cajas/consultorios.
- **Índices:** unique `code`; índice `active` solo si el volumen lo justifica.
- **Historia:** crear una sede inicial confirmada y backfill validado.
- **Rollback:** posible mientras ninguna nueva operación dependa de múltiples sedes; nunca borrar silenciosamente referencias.

### 4.2 Cambios a `doctor_schedules` y `doctor_schedule_exceptions`

Añadir `site_id` nullable a `doctor_schedules`. Crear `doctor_schedule_exceptions` para no sobrecargar la plantilla recurrente.

Campos de excepción:

- `doctor_schedule_id` o combinación `site_id + doctor_id`;
- `date`, `starts_at`, `ends_at`;
- `kind`: `BLOCK`, `OVERRIDE`, `ABSENCE` como string validado por aplicación, no enum rígido inicial;
- `reason`, `created_by`, timestamps.

Constraints/índices:

- validar `ends_at > starts_at` en aplicación y, si la versión real de MySQL lo soporta de forma confiable, mediante `CHECK` futuro;
- índice `(site_id, doctor_id, date, starts_at)`;
- no borrar en cascada historia por eliminar usuario/médico; desactivar recursos.

Backfill: asociar horarios vigentes a la sede inicial tras validación. Rollback: desactivar lectura de excepciones y retirar estructura solo antes de depender de ella.

### 4.3 `appointment_scheduling_details`

Extensión uno-a-uno de una cita para conceptos nuevos de agenda.

| Campo conceptual | Regla |
|---|---|
| `appointment_id` | FK unique; una extensión por cita |
| `booking_type` | `REGULAR`, `OVERBOOKED`, `ADDITIONAL`; string validado, no `estado_cita` |
| `source_channel` | canal de solicitud si está disponible, sin reinterpretar paciente/canal histórico |
| `manual_time` | indica selección manual, no bypass de disponibilidad |
| `informed_at`, `informed_by` | obligatorios para adicional al activarse |
| `expected_wait_minutes` | snapshot de política informada |
| `not_guaranteed_acknowledged` | constancia explícita |
| `operational_outcome` | nullable hasta cierre; catálogo empresarial pendiente |
| `request_key` | idempotencia de alta, unique nullable |
| timestamps | trazabilidad técnica |

- **Índices:** unique `appointment_id`, unique `request_key`; `(booking_type, operational_outcome)` para operación.
- **Defaults:** `booking_type=REGULAR` solo para nuevas citas creadas por el caso de uso; no crear filas masivas para historia sin evidencia.
- **Historia:** citas heredadas sin detalle significan “tipo no reconstruido”, no necesariamente regular.
- **Rollback:** la fila legacy continúa válida; apagar consumidores de detalles antes de retirar.

### 4.4 `appointment_responsibility_changes`

Historial específico, consultable y no reescribible.

Campos:

- `appointment_id`;
- `previous_user_id` nullable para asignación inicial;
- `new_user_id`;
- `changed_by`;
- `reason` nullable según política;
- `changed_at`.

Índices: `(appointment_id, changed_at)`, `(new_user_id, changed_at)`. Las FK no deben borrar el historial cuando un usuario se desactive. Backfill: crear evento “asignación inicial migrada” solo si el responsable histórico se aprueba; si no, iniciar con el primer cambio prospectivo.

### 4.5 `appointment_holds`

Representa una pre-reserva separada de la cita confirmada.

| Campo conceptual | Regla |
|---|---|
| `id`, `public_reference` | referencia no predecible para interacción externa futura |
| `site_id`, `doctor_id`, `patient_id`, `service_id` | contexto completo del cupo |
| `created_by`, `responsible_user_id` | autoría/responsabilidad |
| `starts_at`, `ends_at` | intervalo exacto |
| `status` | `ACTIVE`, `CONVERTED`, `EXPIRED`, `RELEASED`, `CANCELLED`; string validado |
| `expires_at` | 15 minutos por defecto desde configuración al crear |
| `converted_appointment_id` | nullable hasta confirmar; unique cuando exista |
| `request_key` | unique para doble clic/reintento |
| timestamps | técnicos; no sustituyen eventos |

Índices: `(site_id, doctor_id, starts_at, ends_at, status)`, `(expires_at, status)`, `(patient_id, status)`, unique `request_key`.

No existe unique médico+hora porque la exclusión de rangos y las excepciones no se expresan correctamente así. La garantía se logra con el lock transaccional descrito en la sección 6.

Historia: no backfill; solo holds nuevos. Rollback: detener creaciones/expirador; conservar filas para auditoría y liberar estados activos de forma controlada, nunca borrarlas.

### 4.6 `agenda_day_locks`

Fila de coordinación transaccional por recurso/día.

- `site_id`, `doctor_id`, `service_date`;
- unique `(site_id, doctor_id, service_date)`;
- timestamps opcionales.

No contiene negocio. El caso de uso bloquea la fila con `SELECT ... FOR UPDATE`, reevalúa cruces y luego crea/convierte hold o cita dentro de la misma transacción. Serializa solo un profesional/día, aceptable para el volumen esperado y correcto para duraciones variables.

Rollback: cambiar a otro mecanismo de locking solo tras prueba de equivalencia; las filas pueden conservarse sin efecto.

### 4.7 `appointment_authorizations`

Autorización genérica para excepciones, sin hardcodear una columna por flujo.

Campos:

- `appointment_id` o `hold_id` (exactamente uno cuando corresponda);
- `type`: `ZERO_COST`, `DOWN_PAYMENT_EXCEPTION`, `OVERBOOKING`, `HOLD_EXTENSION`;
- `status`: `REQUESTED`, `APPROVED`, `REJECTED`, `REVOKED`;
- `requested_by`, `decided_by` nullable hasta decisión;
- `reason`, `decision_reason`;
- `original_amount`, `approved_amount` nullable según tipo;
- `requested_at`, `decided_at`;
- `request_key` unique.

Índices: `(type, status, requested_at)`, `appointment_id`, `hold_id`. Una regla de aplicación impide más de una autorización activa incompatible para el mismo objeto/tipo. No usar `autorizado_por varchar(255)` como identidad canónica; se mantiene solo como proyección legacy.

### 4.8 `zero_cost_approvers`

Maestro específico confirmado por negocio.

- `user_id` unique por vigencia activa lógica;
- `active`, `valid_from`, `valid_until` nullable;
- `amount_limit` nullable hasta decisión;
- `can_self_approve` nullable mientras negocio no decida; una policy conservadora lo trata como false;
- `created_by`, `disabled_by`, timestamps.

No depende del rol: un médico, Comercial, jefatura u otro usuario puede ser habilitado. La capacidad backend y la vigencia del maestro deben cumplirse simultáneamente.

### 4.9 `appointment_payment_evidences`

Separa evidencia/verificación del resumen financiero legacy.

Campos:

- `appointment_id` o `hold_id`;
- `amount`, `payment_method`, `operation_number` nullable según medio;
- `storage_disk`, `storage_path`, `mime_type`, `checksum` para archivo privado;
- `submitted_by`, `submitted_at`;
- `status`: `PENDING`, `VERIFIED`, `REJECTED`, `VOIDED`;
- `verified_by`, `verified_at`, `verification_note`;
- `request_key` unique.

Constraints/índices: importe positivo salvo medios/excepciones expresamente modelados; `(appointment_id, status)`, `(hold_id, status)`, operación normalizada indexada solo si la política confirma unicidad. El archivo nunca es público ni se registra en logs.

Compatibilidad: al verificar, un único caso de uso actualiza transaccionalmente el resumen legacy y, cuando corresponda, crea/relaciona `payments`; no duplicar cobro ni voucher.

### 4.10 `appointment_events`

Bitácora de negocio append-only para hechos no cubiertos por tablas específicas.

- `appointment_id`/`hold_id`;
- `event_type`;
- `actor_user_id` nullable para proceso automático;
- `occurred_at`;
- `correlation_id`, `request_key`;
- `metadata` JSON mínimo, sin DNI completo, secretos ni capturas.

Índices: `(appointment_id, occurred_at)`, `(hold_id, occurred_at)`, `correlation_id`; unique `request_key` cuando exista. No se actualizan/borran eventos por operaciones ordinarias.

Eventos mínimos: creado, reprogramado, responsable cambiado, hold extendido/expirado, pago verificado/rechazado, excepción solicitada/decidida, paciente informado, adicional cerrada.

## 5. Feature flag y alcance del piloto

No se propone una tabla genérica de feature flags en el primer delta.

- flag global por configuración de entorno;
- permiso `appointment.mvp.access` para usuario/rol;
- si el grupo piloto exige limitar profesionales o sede, crear posteriormente `scheduling_pilot_scopes` con `doctor_id`/`site_id`, vigencia y actor, sin nombres hardcodeados;
- no añadir esa tabla hasta conocer el grupo real.

## 6. Concurrencia propuesta para MySQL

### 6.1 Algoritmo para hold regular

1. Validar formato, capacidad y permiso fuera de la transacción.
2. Abrir transacción MySQL.
3. Crear/obtener `agenda_day_locks(site, doctor, date)` de forma idempotente.
4. Bloquear esa fila con `FOR UPDATE`.
5. Recalcular dentro de la transacción:
   - horario y excepciones;
   - citas activas que se solapan;
   - holds `ACTIVE` con `expires_at > database_now()`;
   - tipo solicitado.
6. Si es regular y existe cruce, rechazar con conflicto recuperable.
7. Insertar el hold con `request_key` unique.
8. Registrar evento y confirmar transacción.

La hora de la BD o una fuente consistente del servidor determina expiración; nunca el cliente.

### 6.2 Confirmación concurrente

1. Bloquear hold y fila profesional/día.
2. Verificar que siga activo/no vencido.
3. Verificar pago ≥ 50 % o autorización aplicable.
4. Recalcular cruces.
5. Crear cita/idempotently actualizar detalle.
6. Marcar hold `CONVERTED` con la cita.
7. Registrar auditoría y proyección financiera legacy en la misma transacción.

Dos confirmaciones sobre el mismo hold: solo una puede cambiar `ACTIVE → CONVERTED`. `request_key` devuelve el resultado previo ante doble clic.

### 6.3 Sobreagenda y adicional

- **Sobreagenda:** toma el mismo lock; permite cruce solo si existe autorización válida. Se registra como `OVERBOOKED`.
- **Adicional:** toma el lock; no consume un cupo regular, pero valida bloque/jornada, agenda llena, carga y límite configurado. Se registra `ADDITIONAL`.
- Ninguna de las dos elude la transacción ni se habilita por manipular el frontend.

### 6.4 Por qué no usar unique simple

`UNIQUE(doctor_id, fecha_cita, hora_cita)` no cubre:

- citas con duraciones distintas;
- intervalos que se solapan con inicios diferentes;
- sobreagenda autorizada;
- adicionales sin slot regular;
- sedes y futuros recursos;
- holds previos a la cita.

La serialización por profesional/día más validación de rangos dentro de transacción representa esas reglas. Los índices aceleran la búsqueda, pero la garantía depende del lock y de la transacción.

## 7. Datos históricos y backfill

Orden seguro:

1. crear catálogos/estructuras vacías;
2. crear sede inicial validada;
3. añadir FK nullable e índices;
4. backfill de `site_id` por lotes e idempotente;
5. no completar `responsible_user_id` hasta decisión explícita; si se usa `user_id`, marcar origen del backfill;
6. no crear detalles `REGULAR` para todas las citas históricas por suposición;
7. validar conteos antes/después y muestreos sin PII;
8. endurecer nulabilidad solo en una migration posterior y únicamente para registros nuevos si la historia no puede completarse.

## 8. Rollback conceptual

- Cada migration es aditiva y reversible en orden inverso mientras el flag permanezca apagado.
- Una vez existan operaciones nuevas, rollback funcional significa apagar escritores y volver a la UI previa; no destruir tablas nuevas.
- Las evidencias, autorizaciones y auditoría nunca se eliminan para simular rollback.
- La reversión de código debe comprender columnas/tablas nuevas ignoradas por la versión anterior.
- Antes de retirar cualquier estructura se exportan conteos y se confirma que no existan consumidores.

## 9. Deudas expresamente pospuestas

- separar por completo obligaciones, ventas, pagos, vouchers y caja;
- cambiar `ON DELETE CASCADE` legacy después de auditar deletes;
- normalizar personas/trabajadores/profesionales;
- historia clínica;
- contrato técnico final con llamador;
- almacén/farmacia/lotes;
- modelo fiscal/SUNAT definitivo.

El MVP mantiene campos financieros legacy por compatibilidad y evita declararlos modelo financiero final.
