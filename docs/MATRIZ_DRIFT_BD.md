# Matriz de drift de base de datos

## 1. Alcance y clasificación

**PENDIENTE DE PRODUCCIÓN:** la matriz prepara la comparación entre la expectativa del código actual y el DDL vivo. Como no se obtuvo DDL de producción, ninguna tabla puede clasificarse todavía como igual o con drift real.

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
| Tablas con DDL productivo obtenido | 0 |
| Tablas clasificadas `IGUAL` | 0 |
| Tablas con drift confirmado contra producción | 0 |
| Tablas pendientes de comprobar | 32 |

No debe interpretarse “0 drift confirmado” como ausencia de drift.

## 3. Matriz de las 32 tablas

| Tabla | Expectativa confirmada en código | Evidencia productiva | Clasificación |
|---|---|---|---|
| `additional_rates` | id, nombre/tipo nullable, tarifa decimal(10,2) nullable, vigencia, estado enum, timestamps | No obtenida | PENDIENTE DE COMPROBAR |
| `appointments` | id, número único, 5 FK, agenda, 4 timestamps de llamador, 3 decimales, pago temporal, dos enums, observaciones y timestamps | No obtenida | PENDIENTE DE COMPROBAR — prioridad crítica |
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

## 4. Drift confirmado entre fuentes de código

Aunque el drift productivo sigue pendiente, sí existe una diferencia comprobable entre el código desplegado declarado y la rama estabilizada:

| Objeto | Commit productivo declarado | Rama actual | Evaluación |
|---|---|---|---|
| Migration `appointments` | Dos declaraciones de `hora_llamado` | Una declaración | Diferencia intencional de Fase 0; DDL vivo pendiente |
| Resto de migrations | 27 archivos | Los mismos 27 archivos | Sin otra diferencia de archivo frente a `6552e525` |

También existe inconsistencia dentro del código actual:

| Concepto | Migration ERP | Código consumidor | Riesgo |
|---|---|---|---|
| Estado `PACIENTE_LLEGO` | No aparece en enum | Vistas ERP y llamador lo usan | Escritura inválida o drift manual productivo |
| Estado `REEVALUACION` | No aparece en enum | Controladores, vistas y llamador lo consultan/escriben | Flujo imposible con DDL literal o drift manual |
| `hora_llamado` | Existe una vez en rama actual | Llamador temporal la escribe; visor la lee | Contrato implícito que debe preservarse temporalmente |
| Integridad `doctor_services` | No declara FK ni unique doctor+service | Consultas unen por ambos ids | Huérfanos/duplicados posibles |

## 5. Checklist columna por columna para tablas críticas

### `appointments`

- `numero_cita`: longitud, unique y nulabilidad;
- FK: signed/unsigned, nulabilidad y `ON DELETE`;
- `fecha_cita`, `hora_cita`, duración y turno;
- `hora_llegada`, `hora_llamado`, `hora_atencion`, `hora_atendido`;
- precisión/defaults de precio, pagado y saldo;
- lista exacta de ambos enums;
- índices reales para fecha, médico y estado;
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

La matriz podrá cerrarse únicamente cuando:

1. se reciba el DDL de las 32 tablas;
2. se compare automáticamente o manualmente cada atributo solicitado;
3. se revise la salida por una segunda persona;
4. las diferencias se relacionen con datos y código consumidor;
5. se documenten excepciones sin modificar producción.
