# Matriz de drift de base de datos

## 1. Alcance y clasificación

**VERIFICACIÓN PARCIAL:** el DDL de `appointments` fue confirmado mediante `SHOW CREATE TABLE appointments` y ya permite clasificar drift real en esa tabla. Las otras 31 tablas permanecen **PENDIENTES DE PRODUCCIÓN**.

Clasificaciones:

- **IGUAL:** columnas, tipos, nulabilidad, defaults, claves, índices y timestamps coinciden.
- **DRIFT MENOR:** diferencia no destructiva que no cambia la semántica principal, pero debe documentarse.
- **DRIFT IMPORTANTE:** afecta integridad, tipos, enum, precisión, claves, índices críticos o comportamiento.
- **NO REPRESENTADO EN MIGRATIONS:** objeto productivo sin equivalente versionado.
- **PENDIENTE DE COMPROBAR:** falta evidencia productiva suficiente.

## 2. Resultado ejecutivo

| Métrica | Resultado |
|---|---:|
| Tablas esperadas por el repositorio, incluida `migrations` | 32 |
| Tablas productivas declaradas | 32 |
| Tablas con DDL productivo obtenido | 1 (`appointments`) |
| Tablas clasificadas `IGUAL` | 0 |
| Tablas con drift confirmado contra producción | 1 (`appointments`) |
| Tablas pendientes de comprobar | 31 |

No debe generalizarse el drift de `appointments` al resto del esquema ni interpretarse la falta de evidencia de las otras tablas como igualdad.

## 3. Matriz de las 32 tablas

| Tabla | Expectativa confirmada en código | Evidencia productiva | Clasificación |
|---|---|---|---|
| `additional_rates` | id, nombre/tipo nullable, tarifa decimal(10,2) nullable, vigencia, estado enum, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `appointments` | Migration/código esperan número único, 5 FK, agenda, 4 timestamps de llamador, finanzas y enums | DDL obtenido: InnoDB; `numero_cita` unique; 5 FK cascade; `additional_rate_id NOT NULL`; enum real ampliado; 4 horas ausentes; sin unique médico+fecha+hora | DRIFT IMPORTANTE CONFIRMADO |
| `cash_movements` | turno FK, tipo INGRESO/EGRESO, concepto, monto decimal(10,2), usuario FK, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `cashier_shifts` | caja/usuario FK, apertura, montos decimales, cierre, estado ABIERTO/CERRADO, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `cashiers` | nombre, estado ACTIVO/INACTIVO default ACTIVO, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `channels` | nombre nullable, estado ACTIVO/INACTIVO, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `departments` | departamento, ubigeo, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `districts` | distrito, ubigeo, FK provincia/departamento con cascade, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `doctor_schedules` | doctor FK, día tinyint, fecha nullable, horas, duración unsigned tinyint, estado, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `doctor_services` | doctor_id/service_id sin FK declaradas, dos precios decimal(10,2), días de reconsulta, estado, timestamps | No obtenida | PENDIENTE DE COMPROBAR — integridad esperada débil |
| `doctors` | especialidad FK cascade, nombre/CMP/RNE nullable, estado, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `failed_jobs` | estructura Laravel con UUID único y payload/exception | No obtenida | PENDIENTE DE COMPROBAR |
| `interaction_media` | nombre nullable, estado ACTIVO/INACTIVO, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `items` | catálogo, tipo SERVICIO/PRODUCTO, precios decimal, stock integer nullable, tributación, estado, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `migrations` | registro framework de migration y batch | Solo conteo declarado de 27 | PENDIENTE DE COMPROBAR |
| `model_has_permissions` | pivote polimórfico Spatie con PK/índices según configuración | No obtenida | PENDIENTE DE COMPROBAR |
| `model_has_roles` | pivote polimórfico Spatie con PK/índices según configuración | No obtenida | PENDIENTE DE COMPROBAR |
| `password_resets` | email indexado, token y created_at nullable | No obtenida | PENDIENTE DE COMPROBAR |
| `patients` | datos identificatorios/contacto, número de identidad único, dos campos de historia, 3 FK nullable, estado, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `payments` | voucher FK, método, monto decimal(10,2), operación, usuario/turno FK, entidades origen/destino, timestamps | No obtenida | PENDIENTE DE COMPROBAR — prioridad crítica |
| `permissions` | nombre/guard y unique compuesto Spatie | No obtenida | PENDIENTE DE COMPROBAR |
| `personal_access_tokens` | relación polimórfica, token único, abilities y último uso | No obtenida | PENDIENTE DE COMPROBAR |
| `provinces` | provincia, ubigeo, departamento FK cascade, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `responsibles` | paciente FK cascade, identificación, nombres/teléfono/`parentezco`, estado, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `role_has_permissions` | pivote Spatie con PK compuesta y dos FK cascade | No obtenida | PENDIENTE DE COMPROBAR |
| `roles` | nombre/guard y unique compuesto Spatie | No obtenida | PENDIENTE DE COMPROBAR |
| `services` | especialidad FK cascade, nombre nullable, estado, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `specialties` | nombre nullable, estado, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `users` | nombre, email único, verificación, password, remember token, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `voucher_items` | voucher FK, morph `item`, cantidades/importes, datos SUNAT, médico FK, comisiones, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `voucher_series` | tipo enum, serie varchar(4), correlativo unsigned, caja nullable, estado, unique tipo+serie | No obtenida | PENDIENTE DE COMPROBAR — prioridad crítica |
| `vouchers` | documento/serie/correlativo, snapshots de cliente, totales, estado/pago, nota padre, turno/usuario, detracción y SUNAT | No obtenida | PENDIENTE DE COMPROBAR — prioridad crítica |

## 4. Drift confirmado entre código y producción

El DDL recibido permite separar diferencias del archivo de migration y drift vivo:

| Objeto | Commit productivo declarado | Rama actual | Evaluación |
|---|---|---|---|
| Migration `appointments` | Dos declaraciones de `hora_llamado` | Una declaración | Producción no contiene `hora_llamado`; ninguna migration auditada representa el DDL vivo |
| Resto de migrations | 27 archivos | Los mismos 27 archivos | Sin otra diferencia de archivo frente a `6552e525` |

Drift e inconsistencias confirmadas:

| Concepto | Migration/código | Producción confirmada | Evaluación/riesgo |
|---|---|---|---|
| `PACIENTE_LLEGO` | No aparece en enum versionado; ERP/llamador lo usan | Existe en enum | DRIFT CONFIRMADO; preservar compatibilidad |
| `REEVALUACION` | No aparece en enum versionado; ERP/llamador lo usan | Existe en enum | DRIFT CONFIRMADO; preservar compatibilidad |
| Horas del llamador | La rama contiene `hora_llegada`, `hora_llamado`, `hora_atencion`, `hora_atendido` | Las cuatro están ausentes | DRIFT IMPORTANTE; contrato productivo del llamador pendiente |
| `additional_rate_id` | Concepto de tarifa adicional legado | `NOT NULL` y FK cascade | No equivale a cita adicional; investigar función histórica |
| Colisión de cupo | Código valida principalmente antes de guardar | Sin unique médico+fecha+hora | Riesgo de concurrencia/doble reserva aunque hoy haya 0 duplicados exactos |
| Borrado de citas | Cinco relaciones de cita | Todas `ON DELETE CASCADE` | Riesgo de pérdida histórica si la aplicación realiza deletes físicos; ocurrencia no demostrada |
| Integridad `doctor_services` | No declara FK ni unique doctor+service | DDL no recibido | PENDIENTE DE PRODUCCIÓN |

## 5. Checklist columna por columna para tablas críticas

### `appointments`

- `numero_cita`: unique confirmado; otros detalles menores según DDL conservado;
- cinco FK y `ON DELETE CASCADE` confirmados; `additional_rate_id NOT NULL`;
- `fecha_cita`, `hora_cita`, duración y turno;
- `hora_llegada`, `hora_llamado`, `hora_atencion`, `hora_atendido`: confirmadas ausentes;
- precisión/defaults de precio, pagado y saldo;
- lista exacta de `estado_cita` confirmada; otros enums/detalles deben conservarse desde el DDL recibido;
- ausencia de unique `doctor_id + fecha_cita + hora_cita` confirmada;
- timestamps y precisión temporal.

### Finanzas

- todos los importes `decimal(10,2)` y nunca float/double;
- nulabilidad de `cashier_shift_id`, `voucher_id` y `user_id`;
- unique de documento/serie/correlativo;
- unique de tipo+serie;
- morph columns de `voucher_items` y sus índices;
- reglas cascade/set null reales;
- enums fiscales y comerciales.

### Identidad y agenda

- unique real de `patients.numero_identidad` y `users.email`;
- signed/unsigned compatible en todas las FK;
- índices en ubigeo e identificadores técnicos;
- FK y unique faltantes o agregados manualmente en `doctor_services`;
- mezcla de plantilla/fecha en `doctor_schedules`.

## 6. Consulta para detectar tablas no representadas

Una vez exportado el listado productivo, compararlo con esta lista esperada. Consulta productiva:

```sql
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;
```

Si aparece una tabla adicional, clasificarla `NO REPRESENTADO EN MIGRATIONS`. Si falta una tabla esperada, clasificar su módulo como `DRIFT IMPORTANTE` hasta determinar cómo funciona el código sin ella.

## 7. Criterio de cierre de la reconciliación

La reconciliación completa de la matriz podrá cerrarse únicamente cuando:

1. se reciba el DDL de las 31 tablas restantes, además del `appointments` ya incorporado;
2. se compare automáticamente o manualmente cada atributo solicitado;
3. se revise la salida por una segunda persona;
4. las diferencias se relacionen con datos y código consumidor;
5. se documenten excepciones sin modificar producción.
