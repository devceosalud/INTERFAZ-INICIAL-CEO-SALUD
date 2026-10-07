# Fase 5B — Plan de verificación productiva read-only para el MVP

## 1. Propósito y estado

Este documento prepara la evidencia mínima necesaria antes de diseñar migrations o implementar el MVP de agendamiento.

**EJECUCIÓN PARCIAL CONFIRMADA:** se incorporó evidencia sanitizada de `SHOW CREATE TABLE appointments`, agregados de citas y roles productivos básicos. El agente no se conectó a producción ni ejecutó SQL. El resto del plan continúa **PENDIENTE DE PRODUCCIÓN**.

El plan complementa, sin reemplazar:

- `SCHEMA_PRODUCTIVO_VERIFICADO.md`, inventario estructural general;
- `PERFIL_DATOS_PRODUCTIVOS.md`, perfil agregado sin PII;
- `LLAMADOR_AS_IS.md`, auditoría local del llamador;
- `INFRAESTRUCTURA_PRODUCTIVA.md`, comprobaciones de despliegue, panel y recuperación.

## 2. Principios de ejecución segura

1. Debe ejecutar el paquete un operador autorizado con una cuenta MySQL dedicada de solo lectura.
2. La cuenta no debe tener `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `CREATE`, `DROP`, `TRIGGER`, `EVENT` ni `EXECUTE`.
3. Las consultas permitidas son `SELECT`, `SHOW` y metadatos de `information_schema`.
4. No ejecutar consultas desde la aplicación ni reutilizar credenciales encontradas en `.env` sin autorización.
5. Ejecutar primero DDL/metadatos; las consultas agregadas posteriores solo se ejecutan si los nombres de columnas fueron confirmados.
6. Si existe drift, registrar `PENDIENTE/INCOMPATIBLE CON CONSULTA PREPARADA`; no improvisar consultas que lean filas o PII.
7. No copiar resultados de negocio a tickets, chats o repositorios públicos.
8. No ejecutar `EXPLAIN ANALYZE`, procedimientos, funciones desconocidas, locks, tablas temporales ni exportaciones.
9. No ejecutar ningún `PUT`, `POST`, `PATCH` o `DELETE` contra el llamador.
10. Una captura o salida debe revisarse y anonimizarse antes de compartirla.

## 3. Datos que nunca deben copiarse

Queda prohibido copiar, mostrar o exportar:

- nombres o apellidos;
- DNI u otros documentos;
- teléfonos, correos o direcciones;
- historias clínicas, motivos, observaciones o datos clínicos;
- números de operación o imágenes/evidencias de pago;
- razones sociales o documentos de clientes;
- tokens, cookies, sesiones, API keys, contraseñas o contenido de `.env`;
- IP completas, rutas de cuenta, usuarios/hosts MySQL o nombres internos de base sin anonimizar;
- XML/CDR, respuestas SUNAT o logs sin sanitización.

Resultados autorizados: DDL, nombres/tipos de columnas, índices, constraints, estados de catálogo, nombres de roles y métricas agregadas que no permitan identificar personas.

## 4. Control previo de la sesión SQL

Ejecutar por separado y revisar antes de continuar:

```sql
SELECT CURRENT_USER() AS cuenta_efectiva,
       DATABASE() AS base_activa;

SHOW VARIABLES
WHERE Variable_name IN ('read_only', 'transaction_read_only', 'tx_read_only');

SHOW GRANTS FOR CURRENT_USER();
```

Resultado esperado:

- base activa expresamente confirmada como ERP productivo por el operador;
- cuenta efectiva dedicada;
- grants sin facultades de modificación;
- variable de sesión reportada como `transaction_read_only` o `tx_read_only`, según motor/versión.

Al compartir la salida, reemplazar cuenta, host y base por `[CUENTA_READ_ONLY]`, `[HOST]` y `[BD_ERP]`. `read_only = OFF` no prueba capacidad de escritura de la cuenta; mandan los grants. Si la cuenta tiene privilegios de escritura, detener la verificación.

## 5. Prioridad 1 — `appointments`

### 5.1 DDL completo

```sql
SHOW CREATE TABLE appointments;
```

Debe responder:

- definición real de todas las columnas;
- enum exacto de `estado_cita` y `estado_pagado`;
- existencia y tipo de `hora_llegada`, `hora_llamado`, `hora_atencion`, `hora_atendido`;
- existencia y nulabilidad de `user_id`;
- campos financieros;
- índices, foreign keys y unique constraints;
- presencia única de `hora_llamado`;
- reglas `ON UPDATE`/`ON DELETE`.

La salida no contiene filas ni PII. Redactar nombre de base si el cliente SQL lo antepone.

### 5.2 Columnas y tipos

```sql
SELECT ORDINAL_POSITION,
       COLUMN_NAME,
       COLUMN_TYPE,
       DATA_TYPE,
       IS_NULLABLE,
       COLUMN_DEFAULT,
       COLUMN_KEY,
       EXTRA,
       NUMERIC_PRECISION,
       NUMERIC_SCALE,
       DATETIME_PRECISION
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'appointments'
ORDER BY ORDINAL_POSITION;
```

Resultado esperado: una fila por columna, sin datos de pacientes. Es la evidencia canónica para comparar migration, código y tabla viva.

### 5.3 Índices y unicidad

```sql
SELECT INDEX_NAME,
       NON_UNIQUE,
       SEQ_IN_INDEX,
       COLUMN_NAME,
       COLLATION,
       SUB_PART,
       INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'appointments'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;
```

Resultado esperado: confirmar PK, unicidad de `numero_cita` y cualquier índice real de médico/fecha/hora/estado. `NON_UNIQUE = 0` identifica índices únicos.

### 5.4 Foreign keys y constraints

```sql
SELECT k.CONSTRAINT_NAME,
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
  AND k.TABLE_NAME = 'appointments'
  AND k.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION;

SELECT tc.CONSTRAINT_NAME,
       tc.CONSTRAINT_TYPE,
       cc.CHECK_CLAUSE
FROM information_schema.TABLE_CONSTRAINTS tc
LEFT JOIN information_schema.CHECK_CONSTRAINTS cc
  ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
 AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
  AND tc.TABLE_NAME = 'appointments'
ORDER BY tc.CONSTRAINT_TYPE, tc.CONSTRAINT_NAME;
```

Resultado esperado: referencias reales a usuarios, pacientes, profesionales, servicios y tarifas, además de constraints únicos/check. En motores donde `CHECK_CONSTRAINTS` no esté disponible, registrar la versión y conservar como fuente el `SHOW CREATE TABLE`; no reemplazar por lectura de filas.

### 5.5 Estados realmente utilizados

Ejecutar solo después de confirmar las columnas:

```sql
SELECT estado_cita, COUNT(*) AS total
FROM appointments
GROUP BY estado_cita
ORDER BY estado_cita;

SELECT estado_pagado, COUNT(*) AS total
FROM appointments
GROUP BY estado_pagado
ORDER BY estado_pagado;
```

Resultado esperado: catálogo efectivo usado y volumen agregado por estado. No contiene PII. Debe compararse con el enum DDL y con los estados del llamador, sin inferir que un estado sin filas esté obsoleto.

### 5.6 Presencia de actor, timestamps y campos financieros

```sql
SELECT COUNT(*) AS total,
       COUNT(DISTINCT user_id) AS usuarios_creadores_distintos,
       COALESCE(SUM(user_id IS NULL), 0) AS sin_usuario,
       COALESCE(SUM(precio_programado IS NULL), 0) AS sin_precio,
       COALESCE(SUM(total_pagado IS NULL), 0) AS sin_total_pagado,
       COALESCE(SUM(saldo_pendiente IS NULL), 0) AS sin_saldo
FROM appointments;
```

Resultado esperado: cobertura agregada de autoría y finanzas. No devuelve ids ni importes. Las columnas `hora_llegada`, `hora_llamado`, `hora_atencion` y `hora_atendido` se retiraron de esta consulta preparada porque el DDL productivo confirmó que no existen.

### 5.7 Calidad financiera y colisiones técnicas

```sql
SELECT COUNT(*) AS total,
       COALESCE(SUM(precio_programado < 0), 0) AS precio_negativo,
       COALESCE(SUM(total_pagado < 0), 0) AS pagado_negativo,
       COALESCE(SUM(saldo_pendiente < 0), 0) AS saldo_negativo,
       COALESCE(SUM(ABS(precio_programado - total_pagado - saldo_pendiente) > 0.01), 0) AS formula_no_cuadra,
       COALESCE(SUM(es_exonerado = 1), 0) AS exoneradas,
       COALESCE(SUM(es_exonerado = 1 AND (autorizado_por IS NULL OR autorizado_por = '')), 0) AS exoneradas_sin_autorizador_textual
FROM appointments;

SELECT COUNT(*) AS grupos_numero_cita_duplicado,
       COALESCE(SUM(repeticiones - 1), 0) AS filas_excedentes
FROM (
    SELECT COUNT(*) AS repeticiones
    FROM appointments
    GROUP BY numero_cita
    HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) AS grupos_misma_hora_activos,
       COALESCE(SUM(repeticiones - 1), 0) AS citas_excedentes
FROM (
    SELECT COUNT(*) AS repeticiones
    FROM appointments
    WHERE estado_cita NOT IN ('CANCELADO', 'NO_ASISTIO')
    GROUP BY doctor_id, fecha_cita, hora_cita
    HAVING COUNT(*) > 1
) d;
```

Resultado esperado: solo cantidades agregadas. La coincidencia médico/fecha/hora es un indicador de posibles dobles cupos; no demuestra por sí sola error, adicional o sobreagenda.

### 5.8 Integridad referencial observable en datos

```sql
SELECT COALESCE(SUM(u.id IS NULL), 0) AS usuario_huerfano,
       COALESCE(SUM(p.id IS NULL), 0) AS paciente_huerfano,
       COALESCE(SUM(d.id IS NULL), 0) AS doctor_huerfano,
       COALESCE(SUM(s.id IS NULL), 0) AS servicio_huerfano,
       COALESCE(SUM(ar.id IS NULL), 0) AS tarifa_huerfana
FROM appointments a
LEFT JOIN users u ON u.id = a.user_id
LEFT JOIN patients p ON p.id = a.patient_id
LEFT JOIN doctors d ON d.id = a.doctor_id
LEFT JOIN services s ON s.id = a.service_id
LEFT JOIN additional_rates ar ON ar.id = a.additional_rate_id;
```

Resultado esperado: conteos sin ids. Si una FK no existe en el DDL, la consulta sigue permitiendo medir referencias rotas.

### 5.9 Evidencia incorporada de `appointments`

**CONFIRMADO EN PRODUCCIÓN mediante DDL sanitizado:**

- InnoDB;
- `numero_cita UNIQUE`;
- cinco FK reales hacia usuarios, pacientes, médicos, servicios y tarifas adicionales, todas con `ON DELETE CASCADE`;
- `additional_rate_id NOT NULL`;
- ausencia de unique `doctor_id + fecha_cita + hora_cita`;
- campos financieros dentro de cita y `autorizado_por varchar(255)`;
- enum `PROGRAMADO`, `CONFIRMADO`, `PACIENTE_LLEGO`, `EN_ESPERA`, `LLAMANDO`, `EN_ATENCION`, `ATENDIDO`, `REEVALUACION`, `CANCELADO`, `NO_ASISTIO`, con default `PROGRAMADO`;
- ausencia de `hora_llegada`, `hora_llamado`, `hora_atencion` y `hora_atendido`.

**CONFIRMADO EN PRODUCCIÓN mediante agregados sin PII:**

| Métrica | Resultado |
|---|---:|
| Total de citas | 9 |
| Creadores distintos | 3 |
| Sin usuario/paciente/médico/servicio | 0 en cada caso |
| Duplicados exactos médico+fecha+hora | 0 |
| `ATENDIDO` | 9 |

El cero actual de duplicados no compensa la ausencia de protección estructural. `ATENDIDO = 9` no implica que los demás valores del enum estén prohibidos u obsoletos.

## 6. Prioridad 2 — tablas dependientes

### 6.1 DDL

```sql
SHOW CREATE TABLE doctor_schedules;
SHOW CREATE TABLE doctor_services;
SHOW CREATE TABLE patients;
SHOW CREATE TABLE payments;
SHOW CREATE TABLE vouchers;
SHOW CREATE TABLE voucher_items;
```

Resultado esperado: tipos, estados, índices, FK y constraints reales de cada tabla. En `patients`, el DDL no muestra personas; no ejecutar `SELECT *`.

### 6.2 Metadatos conjuntos

```sql
SELECT TABLE_NAME,
       ORDINAL_POSITION,
       COLUMN_NAME,
       COLUMN_TYPE,
       IS_NULLABLE,
       COLUMN_DEFAULT,
       COLUMN_KEY,
       EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'doctor_schedules', 'doctor_services', 'patients',
      'payments', 'vouchers', 'voucher_items'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME,
       INDEX_NAME,
       NON_UNIQUE,
       SEQ_IN_INDEX,
       COLUMN_NAME,
       INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'doctor_schedules', 'doctor_services', 'patients',
      'payments', 'vouchers', 'voucher_items'
  )
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

### 6.3 Conteos mínimos

```sql
SELECT 'doctor_schedules' AS tabla, COUNT(*) AS total FROM doctor_schedules
UNION ALL SELECT 'doctor_services', COUNT(*) FROM doctor_services
UNION ALL SELECT 'patients', COUNT(*) FROM patients
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'vouchers', COUNT(*) FROM vouchers
UNION ALL SELECT 'voucher_items', COUNT(*) FROM voucher_items;
```

### 6.4 Horarios y oferta profesional

```sql
SELECT estado, COUNT(*) AS total
FROM doctor_schedules
GROUP BY estado
ORDER BY estado;

SELECT COUNT(*) AS total,
       COALESCE(SUM(fecha_cita IS NULL), 0) AS horarios_recurrentes_candidatos,
       COALESCE(SUM(fecha_cita IS NOT NULL), 0) AS horarios_fechados,
       COALESCE(SUM(hora_fin <= hora_inicio), 0) AS rango_invalido,
       COALESCE(SUM(duracion_cita IS NULL OR duracion_cita = 0), 0) AS duracion_invalida,
       COUNT(DISTINCT duracion_cita) AS duraciones_distintas
FROM doctor_schedules;

SELECT COUNT(*) AS pares_duplicados,
       COALESCE(SUM(repeticiones - 1), 0) AS filas_excedentes
FROM (
    SELECT COUNT(*) AS repeticiones
    FROM doctor_services
    GROUP BY doctor_id, service_id
    HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) AS total,
       COALESCE(SUM(precio_primera_consulta IS NULL), 0) AS sin_precio_primera,
       COALESCE(SUM(precio_reconsulta IS NULL), 0) AS sin_precio_reconsulta,
       COALESCE(SUM(dias_reconsulta IS NULL), 0) AS sin_dias_reconsulta
FROM doctor_services;
```

Resultado esperado: distinguir horarios fechados/recurrentes y medir calidad de tarifas sin mostrar profesional, servicio ni precio individual.

### 6.5 Pacientes sin PII

```sql
SELECT COUNT(*) AS total,
       COUNT(DISTINCT user_id) AS usuarios_registradores_distintos,
       COALESCE(SUM(user_id IS NULL), 0) AS sin_usuario,
       COALESCE(SUM(channel_id IS NULL), 0) AS sin_canal,
       COALESCE(SUM(interaction_medium_id IS NULL), 0) AS sin_medio,
       COALESCE(SUM(numero_identidad IS NULL OR numero_identidad = ''), 0) AS sin_identidad
FROM patients;

SELECT COUNT(*) AS grupos_identidad_duplicada,
       COALESCE(SUM(repeticiones - 1), 0) AS filas_excedentes
FROM (
    SELECT COUNT(*) AS repeticiones
    FROM patients
    WHERE numero_identidad IS NOT NULL AND numero_identidad <> ''
    GROUP BY numero_identidad
    HAVING COUNT(*) > 1
) d;
```

La subconsulta agrupa por identidad, pero la salida externa solo devuelve cantidades; no copiar resultados internos ni modificarla para listar documentos.

### 6.6 Pagos, comprobantes y líneas

```sql
SELECT metodo_pago, COUNT(*) AS total
FROM payments
GROUP BY metodo_pago
ORDER BY metodo_pago;

SELECT COUNT(*) AS total,
       COALESCE(SUM(voucher_id IS NULL), 0) AS sin_voucher,
       COALESCE(SUM(user_id IS NULL), 0) AS sin_usuario,
       COALESCE(SUM(cashier_shift_id IS NULL), 0) AS sin_turno,
       COALESCE(SUM(monto <= 0), 0) AS monto_no_positivo,
       COALESCE(SUM(numero_operacion IS NULL OR numero_operacion = ''), 0) AS sin_numero_operacion
FROM payments;

SELECT tipo_comprobante, estado, estado_sunat, COUNT(*) AS total
FROM vouchers
GROUP BY tipo_comprobante, estado, estado_sunat
ORDER BY tipo_comprobante, estado, estado_sunat;

SELECT item_type, COUNT(*) AS total
FROM voucher_items
GROUP BY item_type
ORDER BY item_type;

SELECT COUNT(*) AS vouchers_sin_lineas
FROM vouchers v
LEFT JOIN voucher_items vi ON vi.voucher_id = v.id
WHERE vi.id IS NULL;

SELECT COUNT(*) AS pagos_sin_voucher_existente
FROM payments p
LEFT JOIN vouchers v ON v.id = p.voucher_id
WHERE v.id IS NULL;

SELECT COUNT(*) AS vouchers_con_diferencia
FROM (
    SELECT v.id,
           v.total,
           COALESCE(SUM(p.monto), 0) AS total_pagado
    FROM vouchers v
    LEFT JOIN payments p ON p.voucher_id = v.id
    WHERE v.estado <> 'ANULADO'
    GROUP BY v.id, v.total
) x
WHERE ABS(total - total_pagado) > 0.01;
```

Resultado esperado: distribuciones y cantidades agregadas. No mostrar series, correlativos, documentos del cliente ni números de operación.

## 7. Prioridad 3 — roles productivos

### 7.1 DDL

```sql
SHOW CREATE TABLE roles;
SHOW CREATE TABLE model_has_roles;
```

### 7.2 Distribución agregada

```sql
SELECT r.name AS rol,
       r.guard_name,
       COUNT(DISTINCT mhr.model_id) AS usuarios_asignados
FROM roles r
LEFT JOIN model_has_roles mhr
  ON mhr.role_id = r.id
 AND mhr.model_type = 'App\\Models\\User'
GROUP BY r.id, r.name, r.guard_name
ORDER BY r.name;

SELECT model_type,
       COUNT(*) AS asignaciones,
       COUNT(DISTINCT role_id) AS roles_distintos,
       COUNT(DISTINCT model_id) AS modelos_distintos
FROM model_has_roles
GROUP BY model_type
ORDER BY model_type;

SELECT COUNT(*) AS usuarios_sin_rol
FROM users u
LEFT JOIN model_has_roles mhr
  ON mhr.model_id = u.id
 AND mhr.model_type = 'App\\Models\\User'
WHERE mhr.role_id IS NULL;
```

Resultado esperado: nombres de roles y cantidades, nunca nombres, emails ni ids de usuarios. Si `model_type` difiere, registrar la distribución agregada; no listar `model_id`.

## 8. Matriz de evidencia y resultado esperado

| Evidencia | Consulta/paso | Resultado que habilita |
|---|---|---|
| DDL real de citas | 5.1–5.4 | CONFIRMADO; permite comparar migration/código/producción y preparar cambios aditivos. |
| Estados reales | 5.5 | Enum/distribución CONFIRMADOS; flujo productivo del llamador confirmado como temporal e independiente. |
| Autoría/timestamps | 5.6 | Conocer cobertura histórica y necesidad de backfill. |
| Finanzas/costo cero | 5.7 | Dimensionar reconciliación y excepciones legadas. |
| Colisiones | 5.7 | Dimensionar coexistencia de dobles cupos sin calificarlos automáticamente. |
| Integridad de relaciones | 5.8 | Detectar huérfanos antes de imponer FK/restricciones. |
| Horarios reales | 6.1–6.4 | Distinguir recurrencia, fechas y reglas usadas. |
| Captación disponible | 6.5 | Saber cuánto canal/medio existe; no reinterpretarlo como responsable de la cita. |
| Pagos/comprobantes | 6.6 | Evitar doble cobro y definir conciliación mínima. |
| Roles productivos | 7 | Roles básicos CONFIRMADOS; asignaciones/permisos detallados pendientes. |

## 9. Anonimización y custodia

Antes de compartir resultados:

1. conservar los encabezados y agregados;
2. sustituir cuenta, host, base y rutas por etiquetas neutras;
3. recortar paneles laterales o resultados previos del cliente SQL;
4. comprobar que no haya consultas/resultados de otras pestañas;
5. no adjuntar historial del terminal ni logs completos;
6. guardar evidencia en un espacio interno con acceso mínimo;
7. registrar fecha, responsable y origen como metadatos separados;
8. usar `PENDIENTE` en vez de rellenar por inferencia.

DDL, estados y roles pueden incorporarse a documentación técnica una vez sanitizados. Métricas económicas deben mantenerse agregadas y con acceso restringido.

## 10. Verificación pasiva del llamador

### 10.1 Evidencia de despliegue

Por panel, log de despliegue o SSH read-only:

1. registrar repositorio y rama configurados, redactando rutas/cuenta;
2. obtener `git rev-parse HEAD` si el despliegue conserva `.git`;
3. comparar con el commit auditado `80b2fd417605da3ca869450b8ae52f996045593d`;
4. si no existe `.git`, usar log de despliegue o un manifiesto/fecha/hash de archivos no sensibles;
5. no ejecutar `git pull`, checkout, composer ni Artisan que escriba cache.

Resultado: `MISMO COMMIT`, `OTRO COMMIT` o `PENDIENTE`, con fuente y fecha.

### 10.2 Flujo legacy activo

Inspección sin mutación:

- revisar `routes/web.php`, controladores desplegados y middleware;
- revisar vistas/enlaces configurados para saber si la operación usa rutas legacy o temporal;
- revisar logs de acceso sanitizados buscando solo método, path, status y timestamp agregado;
- no copiar query strings, bodies, nombres, ids, IP, cookies ni respuestas;
- registrar conteos por ruta/método/status, no eventos identificables.

Evidencia suficiente: rutas desplegadas + uso agregado reciente o configuración que selecciona el flujo. La sola presencia de código no demuestra uso.

### 10.3 Conexión `other_system`

Revisar panel/configuración efectiva sin imprimir valores:

- variables `OTHER_SYSTEM_DB_*`: reportar solo CONFIGURADAS/NO CONFIGURADAS;
- config cache efectiva: reportar solo conexión presente y driver;
- comparar, mediante cuenta read-only, la huella estructural de `appointments` vista desde `other_system` con el DDL del ERP;
- reportar `APUNTA A ERP: SÍ/NO/PENDIENTE`, sin copiar host, base, usuario o contraseña.

No ejecutar escrituras para demostrar la conexión.

### 10.4 Rutas potencialmente mutantes

Rutas bajo revisión:

```text
PUT  /admision/gestion-paciente/estado
POST /llamar-paciente
```

Estrategia segura:

1. confirmar definición y middleware mediante inspección estática;
2. revisar `route:list` solo si el operador confirma que arrancar Laravel no dispara efectos externos; filtrar por los dos paths;
3. revisar reglas de proxy/WAF, Basic Auth, VPN, allowlist o autenticación del panel;
4. revisar logs sanitizados para determinar alcance y respuestas históricas agregadas;
5. desde una red no privilegiada, usar primero DNS/TLS y `OPTIONS` únicamente si infraestructura confirma que no muta estado;
6. opcionalmente usar `HEAD` o `GET` al path exacto solo si se confirmó que no hay fallback/controlador que escriba; esperar `405/404/401/403`;
7. no obtener ni reutilizar cookies/CSRF con intención de probar escritura;
8. nunca enviar `PUT`/`POST`, ni siquiera con id inexistente o body vacío.

Un `405` ante GET/HEAD demuestra solo rechazo de ese método, no seguridad del PUT/POST. Un `OPTIONS` permisivo tampoco demuestra por sí solo que la escritura sea ejecutable.

### 10.5 Clasificación

| Estado | Criterio mínimo |
|---|---|
| **CONFIRMADO SEGURO** | Commit/rutas desplegados conocidos; PUT/POST exigen autenticación y autorización verificadas o una restricción externa efectiva; alcance de red confirmado; no existe camino anónimo al controlador mutante. |
| **CONFIRMADO EXPUESTO** | Ruta mutante desplegada sin autenticación/autorización efectiva y alcance público confirmado mediante evidencia pasiva/no mutante o configuración inequívoca. No requiere ni permite ejecutar la mutación. |
| **POTENCIALMENTE EXPUESTO** | Código/ruta sin protección visible, pero commit, alcance público o controles externos no están completamente comprobados. |
| **PENDIENTE** | Evidencia insuficiente para determinar despliegue, middleware, conexión o alcance. |

Estado actual de ambas rutas: **POTENCIALMENTE EXPUESTO** en el código auditado y **PENDIENTE** en producción.

### 10.6 Resultado de la verificación productiva

Mediante una sesión real autenticada se confirmó el flujo normal de ADMISION sin ejecutar endpoints mutantes ni modificar producción:

- flujo primario: **`visorTemporal` CONFIRMADO**;
- login ADMISION: redirección confirmada a `/admision/temporal/gestion-paciente`;
- fuente: base por defecto propia y `appointments` temporal;
- `other_system`: **NO CONFIGURADA** en el `.env` revisado y sin cache efectiva de configuración/rutas;
- protección productiva de las rutas: **PENDIENTE DE PRODUCCIÓN**;
- legacy: presente en el mismo código, pero no confirmado como flujo normal ni como dependencia externa;
- sincronización ERP → llamador: **NO ENCONTRADA/NO CONFIRMADA**;
- **Traer Datos**: recarga GET del índice temporal, no importa citas;
- consultorio/destino: ausente del modelo auditado.

La pantalla temporal estaba sin citas. Esto no prueba un fallo y puede corresponder a ausencia de registros para la fecha/filtros.

## 11. Evidencia mínima del informe de ejecución

El operador debe devolver únicamente:

- fecha y responsable interno;
- resultado de grants read-only, sin identidad de cuenta;
- DDL/metadatos sanitizados;
- tablas de agregados autorizadas;
- commit/flujo del llamador;
- estado de `other_system` sin valores sensibles; en esta ejecución documental quedó **NO CONFIGURADA**;
- matriz de protección/alcance de las dos rutas;
- errores estructurales sin stack traces ni datos;
- clasificación final y fuente de cada afirmación.

## 12. Gate actualizado para iniciar implementación

Los siguientes son bloqueos técnicos obligatorios antes de diseñar/aplicar cambios integrados sobre `appointments` o su contrato operativo:

- **A. DDL productivo de `appointments`: CONFIRMADO.** DDL sanitizado incorporado.
- **B. Estados productivos: CONFIRMADO.** Enum/default y distribución agregada actual incorporados; su compatibilidad operativa con el llamador forma parte de C.
- **C. Flujo productivo del llamador: CONFIRMADO.** El primario es `visorTemporal`, aislado en una base propia y sin sincronización confirmada con el ERP. La planificación debe introducir un contrato explícito, no escritura compartida.
- **D. Roles productivos básicos: CONFIRMADO.** Las asignaciones/permisos detallados pueden continuar pendientes sin reabrir este gate básico.

**E. Grupo exacto del piloto:** permanece **PENDIENTE DE NEGOCIO**, pero no bloquea el desarrollo genérico una vez resueltos A–D si profesionales, usuarios y roles se mantienen configurables y no se hardcodean. Sí debe estar confirmado antes de configurar participantes, ejecutar UAT dirigida o activar el piloto.

Pendientes empresariales de alcance específico, no bloqueos globales del MVP:

- COSTO 0: autoaprobación y límites económicos; bloquean solo la habilitación real de ese flujo.
- Horario no disponible/sobreagendamiento: regla exacta de guardado y autorización; bloquea esa excepción, no la agenda regular.
- Cita adicional: máximos por profesional/bloque/día y cierre/no atención; bloquean su activación real, no la agenda regular.

Ya no son bloqueos: el tipo de perfiles que pueden aprobar COSTO 0 —se administrará por maestro configurable—, la necesidad de aprobación médica manual para adicionales —no es obligatoria— ni la asignación/reasignación básica del responsable de la cita —creador como responsable inicial y cambio auditable—.

Los gates técnicos A–D están confirmados y existe evidencia suficiente para pasar a la **planificación de implementación local** del MVP. Esto no autoriza migrations productivas, despliegue ni activación. El legacy debe verificarse sin consumidores y contenerse/retirarse antes del piloto; cumplir E y cerrar las políticas específicas aplicables continúa siendo obligatorio antes de UAT/activación.

## 13. Resultado de esta fase documental

- paquete SQL parcialmente ejecutado fuera de esta sesión; evidencia sanitizada de `appointments` incorporada;
- flujo productivo primario del llamador confirmado como `visorTemporal` mediante sesión autenticada facilitada fuera de esta ejecución; el agente no modificó producción;
- agregados productivos de citas incorporados sin PII; resto del perfil pendiente;
- secretos y PII no incorporados ni inspeccionados por el agente;
- gate de implementación reevaluado: A–D confirmados; E y las políticas específicas bloquean configuración/UAT/activación, no la planificación local genérica sin hardcoding;
- paquete de evidencia suficiente para cerrar la Fase 5 documental y pasar a planificación, sin autorizar implementación productiva.
