# Esquema productivo — verificación pendiente

## 1. Estado de la verificación

**PENDIENTE DE PRODUCCIÓN:** la verificación del esquema no fue ejecutada. Este archivo es el registro/plantilla para incorporar evidencia futura; su nombre de archivo no afirma que el DDL vivo ya esté reconciliado.

No se recibió una sesión de solo lectura, exportación de metadatos, `SHOW CREATE TABLE` ni acceso al panel productivo. El `.env` local tiene una conexión MySQL configurada, pero no se inspeccionaron sus credenciales ni se intentó conectar porque su destino no está demostrado y la fase prohíbe usar credenciales productivas.

Este documento separa estrictamente:

- **CONFIRMADO EN CÓDIGO:** observable en el repositorio local.
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
| DDL vivo de las 32 tablas | Pendiente | No obtenido |
| Conteos o calidad de datos | Pendiente | No consultados |
| Versión MySQL/MariaDB productiva | Pendiente | No consultada |

La coincidencia 32/27 no demuestra igualdad de columnas, tipos, índices, claves o datos.

## 3. Diferencia conocida en la migration de `appointments`

### 3.1 Tres estados distintos que no deben confundirse

1. **Commit productivo declarado `6552e525`:** la migration declara `hora_llamado` dos veces.
2. **Rama local estabilizada:** el commit de Fase 0 `9519043` eliminó una de las dos declaraciones para permitir construir la base aislada.
3. **Tabla productiva real:** pendiente de obtener mediante `SHOW CREATE TABLE appointments`.

Solo la tercera evidencia dirá qué columna existe realmente y con qué tipo/default. No se editará de nuevo la migration histórica durante esta fase.

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

Esto es una inconsistencia **confirmada en código**. Queda pendiente comprobar si el enum productivo fue ampliado manualmente, si el motor aceptó valores coercionados o si esos flujos fallan.

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

Comprobaciones esperadas:

- existencia única de `hora_llamado`;
- tipo y precisión de `hora_llegada`, `hora_llamado`, `hora_atencion`, `hora_atendido`;
- lista exacta del enum `estado_cita`;
- enum y default de `estado_pagado`;
- precisión `decimal(10,2)` de importes;
- nulabilidad de las cinco claves foráneas;
- restricciones e índices sobre fecha, hora, médico y estados;
- unicidad de `numero_cita`;
- reglas `ON DELETE` reales.

## 8. Resultado actual

| Área | Clasificación |
|---|---|
| Conteo declarado de tablas/migrations | Antecedente confirmado |
| Esquema versionado actual | Confirmado en código |
| DDL productivo | Pendiente de comprobar |
| Drift columna por columna | Pendiente de comprobar |
| `appointments.hora_llamado` vivo | Pendiente de comprobar |
| Enum productivo de citas | Pendiente de comprobar, prioridad crítica |
| Índices, FK y defaults productivos | Pendiente de comprobar |

No se ejecutó ninguna consulta ni conexión a producción.
