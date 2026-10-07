# MVP-4A — Schema readiness para creación de citas

## Estado y alcance

Este incremento prepara el esquema versionado para el futuro write de `Appointment`. No crea
citas desde Agenda, no ejecuta migrations sobre `ERPCEOSALUD` y no modifica datos productivos.

## Comparación comprobada

### MariaDB heredada `ERPCEOSALUD`

- Tiene 27 migrations registradas.
- No tiene aplicadas `create_sites_table`,
  `add_scheduling_foundation_to_appointments_table` ni
  `add_site_to_doctor_schedules_table`.
- No existe `sites`.
- `appointments` no tiene `site_id`, `responsible_user_id` ni `updated_by_user_id`.
- `doctor_schedules` no tiene `site_id`.
- El enum real de `appointments.estado_cita` ya contiene los diez estados operativos.
- `payments` existe y su migration está registrada, pero la tabla heredada no contiene
  `entidad_origen` ni `entidad_destino`.

### Esquema versionado antes de MVP-4A

- La migration base de `appointments` contenía ocho estados y omitía `PACIENTE_LLEGO` y
  `REEVALUACION`.
- Las tres migrations Scheduling agregan de forma nullable la sede y los usuarios de gestión.
- La migration de `payments` usaba `after()` dentro de `Schema::create`; Laravel lo emitía
  literalmente y MariaDB 10.4 rechazaba el `CREATE TABLE`.

## Reparación de bootstrap de `payments`

Se retiraron únicamente los dos modificadores `after()` inválidos. No cambia nombre, tipo,
nullabilidad ni relaciones de ninguna columna. La migration ya está registrada como aplicada
en la BD heredada, por lo que esta reparación solo permite reconstrucciones nuevas.

La ausencia de `entidad_origen` y `entidad_destino` en la tabla heredada es drift independiente.
No se corrige en MVP-4A y debe evaluarse antes de evolucionar pagos.

## Reconciliación del enum

Una migration nueva amplía `estado_cita` a:

1. `PROGRAMADO`
2. `CONFIRMADO`
3. `PACIENTE_LLEGO`
4. `EN_ESPERA`
5. `LLAMANDO`
6. `EN_ATENCION`
7. `ATENDIDO`
8. `REEVALUACION`
9. `CANCELADO`
10. `NO_ASISTIO`

El default continúa siendo `PROGRAMADO`. `down()` se niega a reducir el enum si existen filas
con `PACIENTE_LLEGO` o `REEVALUACION`, evitando pérdida o coerción de datos.

## Tarifa estándar de una cita normal

El catálogo heredado contiene una única fila activa `TARIFA ESTANDAR`, de tipo `MONTO_FIJO`,
importe `0.00` y vigencia abierta. `StandardAdditionalRateResolver` resuelve por esos atributos
y vigencia, nunca por un id hardcodeado. Falla de forma cerrada si falta o hay más de una.

`additional_rate_id` continúa siendo obligatorio y no representa una cita adicional.

## Validación MariaDB limpia

Se creó desde cero la base exclusiva `ERPCEOSALUD_MVP4A_VALIDATION` sobre MariaDB 10.4.32.
Las 31 migrations pasaron con `php artisan migrate`, incluida la reparación de `payments`, las
tres migrations Scheduling y la reconciliación del enum. El `down()` del enum se ejecutó sin
datos incompatibles y la migration volvió a aplicarse correctamente.

En una transacción se crearon fixtures completamente ficticios y dos citas técnicas: una con
`site_id` real y otra con `site_id`, `responsible_user_id` y `updated_by_user_id` nulos. Se
comprobaron duración, estado `PROGRAMADO` y el estado conciliado `PACIENTE_LLEGO`. La
transacción fue revertida y la tabla quedó sin citas.

## Preflight para `ERPCEOSALUD`

No ejecutar sin autorización y backup lógico verificado.

Orden esperado:

1. crear `sites`;
2. agregar a `appointments` tres columnas nullable, tres foreign keys y el índice
   `(site_id, fecha_cita, doctor_id)`;
3. agregar `doctor_schedules.site_id` nullable con foreign key;
4. reconciliar el enum de `estado_cita` —en la heredada debería ser un cambio idempotente de
   definición porque ya contiene los diez valores—.

Riesgos:

- los `ALTER TABLE` pueden bloquear escrituras mientras MariaDB reconstruye o valida tablas;
- las foreign keys requieren que cualquier valor futuro exista en `sites`/`users`;
- rollback de las columnas elimina metadatos Scheduling que se hayan poblado;
- rollback del enum queda bloqueado si usa alguno de los dos estados conciliados;
- las cinco foreign keys históricas de `appointments` siguen en `ON DELETE CASCADE` y conservan
  el riesgo de pérdida de citas al borrar físicamente entidades padre.

Antes de autorizar la aplicación local heredada: backup lógico, ventana sin escrituras,
verificación de espacio, conteos antes/después, `SHOW CREATE TABLE` y prueba de rollback en una
copia desechable.
