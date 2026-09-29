# Esquema productivo — verificación parcial

## 1. Estado de la verificación

**CONFIRMADO EN PRODUCCIÓN:** se recibió evidencia sanitizada obtenida directamente mediante `SHOW CREATE TABLE appointments`. El DDL de `appointments` queda confirmado con el alcance documentado aquí.

**PENDIENTE DE PRODUCCIÓN:** el DDL de las otras 31 tablas, la versión efectiva del motor y el resto de la reconciliación. El agente no abrió una sesión productiva, no inspeccionó credenciales y no ejecutó consultas; incorporó únicamente la evidencia confirmada proporcionada.

Este documento separa estrictamente:

- **CONFIRMADO EN CÓDIGO:** observable en el repositorio local.
- **CONFIRMADO EN PRODUCCIÓN:** demostrado por evidencia productiva sanitizada.
- **ANTECEDENTE CONFIRMADO:** dato entregado previamente por Rodrigo, todavía no reproducido en esta fase.
- **PENDIENTE DE PRODUCCIÓN:** requiere evidencia de solo lectura.
- **NO OBSERVADO:** no existe evidencia en el repositorio inspeccionado.

## 2. Evidencia disponible

| Evidencia | Estado | Resultado |
|---|---|---|
| Commit productivo declarado | Antecedente confirmado | `6552e52521ac59d9c1bf8bc3efd880533a87d11d` |
| Tablas productivas | Antecedente confirmado | 32 `BASE TABLES` |
| Registros productivos en `migrations` | Antecedente confirmado | 27 |
| Archivos de migration en repositorio | Confirmado en código | 27 |
| Tablas creadas por esos archivos | Confirmado en código | 31 más la tabla framework `migrations` = 32 esperadas |
| DDL vivo de `appointments` | Confirmado en producción | Obtenido mediante `SHOW CREATE TABLE appointments` |
| DDL vivo de las otras 31 tablas | Pendiente de producción | No obtenido |
| Datos agregados de `appointments` | Confirmado en producción | 9 citas; métricas sanitizadas incorporadas |
| Conteos o calidad de otras tablas | Pendiente de producción | No consultados/incorporados |
| Versión MySQL/MariaDB productiva | Pendiente | No consultada |

La coincidencia 32/27 no demuestra igualdad de columnas, tipos, índices, claves o datos.

## 3. Diferencia conocida en la migration de `appointments`

### 3.1 Tres estados distintos que no deben confundirse

1. **Commit productivo declarado `6552e525`:** la migration declara `hora_llamado` dos veces.
2. **Rama local estabilizada:** el commit de Fase 0 `9519043` eliminó una de las dos declaraciones para permitir construir la base aislada.
3. **Tabla productiva real:** confirmada mediante `SHOW CREATE TABLE appointments`; no contiene ninguna de las cuatro columnas horarias del llamador.

La tabla productiva confirma que `hora_llamado` no existe; por tanto, la duplicación de la migration no describe el DDL vivo. Tampoco existen `hora_llegada`, `hora_atencion` ni `hora_atendido`. No se editará la migration histórica durante esta fase.

### 3.2 Enum de `estado_cita`

La migration actual del ERP declara:

```text
PROGRAMADO, CONFIRMADO, EN_ESPERA, LLAMANDO,
EN_ATENCION, ATENDIDO, CANCELADO, NO_ASISTIO
```

Sin embargo, código del ERP y del llamador también usa:

```text
PACIENTE_LLEGO, REEVALUACION
```

El enum productivo real queda **CONFIRMADO EN PRODUCCIÓN**:

```text
PROGRAMADO, CONFIRMADO, PACIENTE_LLEGO, EN_ESPERA, LLAMANDO,
EN_ATENCION, ATENDIDO, REEVALUACION, CANCELADO, NO_ASISTIO
```

Su default es `PROGRAMADO`. Se confirma drift: producción contiene `PACIENTE_LLEGO` y `REEVALUACION`, mientras la migration versionada no los representa correctamente.

### 3.3 Estructura productiva confirmada de `appointments`

- motor `InnoDB`;
- `numero_cita` con restricción `UNIQUE`;
- FK reales `user_id → users.id`, `patient_id → patients.id`, `doctor_id → doctors.id`, `service_id → services.id` y `additional_rate_id → additional_rates.id`;
- las cinco FK usan actualmente `ON DELETE CASCADE`;
- `additional_rate_id` es `NOT NULL`;
- no existe restricción `UNIQUE (doctor_id, fecha_cita, hora_cita)`;
- `autorizado_por` es `varchar(255)`;
- la tabla contiene `precio_programado`, `total_pagado`, `saldo_pendiente`, `metodo_pago`, `es_exonerado`, `autorizado_por`, `estado_pagado` y `numero_operacion`.

**RIESGO:** la eliminación física de una entidad padre puede eliminar citas históricas por cascada. La evidencia no demuestra que esos deletes estén ocurriendo; debe revisarse después el comportamiento de eliminación de la aplicación.

`additional_rate_id`/`additional_rates` es un concepto legado distinto de **CITA ADICIONAL** del MVP. Su función histórica exacta continúa pendiente de investigación.

La ausencia de unique médico+fecha+hora deja la concurrencia/doble reserva sin protección estructural. Los agregados actuales no muestran duplicados exactos, pero eso no garantiza que no puedan producirse.

## 4. Protocolo seguro de obtención

La ejecución debe realizarla un operador autorizado con una cuenta MySQL dedicada de solo lectura.

Requisitos:

- permisos limitados a `SELECT`, `SHOW VIEW` y lectura de `information_schema`;
- sin permisos `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `CREATE`, `DROP`, `TRIGGER`, `EVENT` ni `EXECUTE`;
- seleccionar explícitamente la base correcta;
- guardar la salida en un repositorio seguro de evidencia, sin filas de negocio;
- revisar que el resultado contenga únicamente metadatos;
- registrar fecha, motor, host lógico y responsable, sin credenciales.

Verificación previa sugerida:

```sql
SELECT CURRENT_USER() AS cuenta_efectiva,
       DATABASE() AS base_activa,
       @@read_only AS servidor_read_only,
       @@transaction_read_only AS sesion_read_only;

SHOW GRANTS FOR CURRENT_USER();
```

`@@read_only = 0` no invalida por sí solo una cuenta de solo lectura; deben revisarse sus grants. No continuar si la cuenta tiene facultades de escritura no justificadas.

## 5. Consultas de inventario estructural

### 5.1 Motor y base activa

```sql
SELECT VERSION() AS version_motor,
       DATABASE() AS base_activa,
       @@character_set_database AS charset_bd,
       @@collation_database AS collation_bd,
       @@sql_mode AS sql_mode,
       @@global.time_zone AS timezone_global,
       @@session.time_zone AS timezone_sesion;
```

### 5.2 Tablas y tamaño aproximado

```sql
SELECT TABLE_NAME,
       ENGINE,
       TABLE_COLLATION,
       TABLE_ROWS,
       DATA_LENGTH,
       INDEX_LENGTH,
       CREATE_TIME,
       UPDATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;
```

### 5.3 Columnas completas

```sql
SELECT TABLE_NAME,
       ORDINAL_POSITION,
       COLUMN_NAME,
       COLUMN_TYPE,
       DATA_TYPE,
       IS_NULLABLE,
       COLUMN_DEFAULT,
       COLUMN_KEY,
       EXTRA,
       CHARACTER_SET_NAME,
       COLLATION_NAME,
       NUMERIC_PRECISION,
       NUMERIC_SCALE,
       DATETIME_PRECISION
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

### 5.4 Índices y unicidad

```sql
SELECT TABLE_NAME,
       INDEX_NAME,
       NON_UNIQUE,
       SEQ_IN_INDEX,
       COLUMN_NAME,
       COLLATION,
       SUB_PART,
       INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

### 5.5 Foreign keys

```sql
SELECT k.TABLE_NAME,
       k.CONSTRAINT_NAME,
       k.COLUMN_NAME,
       k.REFERENCED_TABLE_NAME,
       k.REFERENCED_COLUMN_NAME,
       r.UPDATE_RULE,
       r.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.TABLE_NAME = k.TABLE_NAME
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION;
```

### 5.6 Constraints adicionales

```sql
SELECT tc.TABLE_NAME,
       tc.CONSTRAINT_NAME,
       tc.CONSTRAINT_TYPE,
       cc.CHECK_CLAUSE
FROM information_schema.TABLE_CONSTRAINTS tc
LEFT JOIN information_schema.CHECK_CONSTRAINTS cc
  ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
 AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_TYPE, tc.CONSTRAINT_NAME;
```

### 5.7 Migrations registradas

```sql
SELECT id, migration, batch
FROM migrations
ORDER BY id;
```

La salida no contiene PII y permitirá verificar los 27 registros y su orden real.

## 6. DDL solicitado para tablas críticas

Ejecutar cada sentencia por separado y conservar la salida completa:

```sql
SHOW CREATE TABLE appointments;
SHOW CREATE TABLE payments;
SHOW CREATE TABLE vouchers;
SHOW CREATE TABLE voucher_items;
SHOW CREATE TABLE voucher_series;
SHOW CREATE TABLE cashier_shifts;
SHOW CREATE TABLE cash_movements;
SHOW CREATE TABLE patients;
SHOW CREATE TABLE doctors;
SHOW CREATE TABLE doctor_services;
SHOW CREATE TABLE items;
SHOW CREATE TABLE users;
```

Segunda prioridad:

```sql
SHOW CREATE TABLE responsibles;
SHOW CREATE TABLE doctor_schedules;
SHOW CREATE TABLE services;
SHOW CREATE TABLE cashiers;
SHOW CREATE TABLE roles;
SHOW CREATE TABLE permissions;
SHOW CREATE TABLE model_has_roles;
SHOW CREATE TABLE model_has_permissions;
SHOW CREATE TABLE role_has_permissions;
```

No ejecutar `SHOW TABLE STATUS` sobre otra base por error ni exportar registros. La evidencia requerida es solo DDL.

## 7. Validación específica de `appointments`

```sql
SELECT ORDINAL_POSITION,
       COLUMN_NAME,
       COLUMN_TYPE,
       IS_NULLABLE,
       COLUMN_DEFAULT,
       COLUMN_KEY,
       EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'appointments'
ORDER BY ORDINAL_POSITION;
```

Resultados incorporados:

- las cuatro columnas horarias del llamador no existen;
- enum y default de `estado_cita` confirmados;
- cinco FK reales con `ON DELETE CASCADE`;
- `additional_rate_id NOT NULL`;
- `numero_cita UNIQUE`;
- ausencia de unique médico+fecha+hora;
- campos financieros y `autorizado_por varchar(255)` confirmados.

Permanecen pendientes para otras etapas los detalles no suministrados de tipos/defaults secundarios y el DDL de las demás tablas.

## 8. Resultado actual

| Área | Clasificación |
|---|---|
| Conteo declarado de tablas/migrations | Antecedente confirmado |
| Esquema versionado actual | Confirmado en código |
| DDL productivo de `appointments` | Confirmado en producción |
| DDL productivo de otras 31 tablas | Pendiente de producción |
| Drift de `appointments` | Confirmado: enum ampliado y cuatro columnas horarias ausentes |
| `appointments.hora_llamado` vivo | Confirmado ausente |
| Enum productivo de citas | Confirmado, 10 valores; default `PROGRAMADO` |
| FK/unique principales de `appointments` | Confirmados |
| Compatibilidad estructural del legacy con `appointments` | Confirmada en código frente al DDL productivo; no confirma despliegue |
| Compatibilidad directa de `visorTemporal` con `appointments` ERP | No compatible: requiere cuatro horas ausentes; podría usar tabla propia |
| Flujo productivo primario del llamador | Confirmado en producción: `visorTemporal` |
| Fuente de datos del flujo primario | Base propia y `appointments` temporal del llamador |
| Sincronización ERP ↔ llamador | No encontrada en código ni confirmada en producción |

El agente no ejecutó consultas ni abrió conexiones a producción. La Fase 5 permanece abierta.

## 9. Lectura del DDL para el Gate C

**CONFIRMADO EN PRODUCCIÓN:** `appointments` admite los estados que consume el legacy y dispone de `updated_at`, pero no de `hora_llegada`, `hora_llamado`, `hora_atencion` ni `hora_atendido`.

**CONFIRMADO EN CÓDIGO:** el legacy puede operar con esa forma de tabla porque usa relaciones, `estado_cita` y `updated_at`; `visorTemporal` no puede usar directamente la tabla productiva confirmada porque depende de las cuatro horas ausentes.

**CONFIRMADO EN PRODUCCIÓN:** el login de ADMISION redirige a `/admision/temporal/gestion-paciente`; el controlador temporal usa la base por defecto propia. El `.env` revisado no contiene `OTHER_SYSTEM_DB_*` y no existe cache efectiva de configuración/rutas que aporte esa conexión. El flujo productivo primario no depende de `other_system`.

**RIESGO PRODUCTIVO POTENCIAL:** el código desplegado conserva rutas/controladores legacy y `updated_at` como señal de rellamado. No está confirmado que tengan consumidores ni que alcancen al ERP; deben contenerse o retirarse después de verificar no uso.

Resultado: **GATE C = CONFIRMADO — FLUJO PRODUCTIVO PRIMARIO: `visorTemporal`**. La incompatibilidad directa entre ambas tablas no es un defecto del DDL confirmado, sino una separación AS-IS que exige un contrato explícito de integración posterior.
