# Perfil de datos productivos sin PII

## 1. Resultado actual

**VERIFICACIÓN PARCIAL:** se incorporaron agregados sanitizados de `appointments` y la confirmación de roles productivos básicos. El resto del perfil continúa **PENDIENTE DE PRODUCCIÓN**.

El agente no ejecutó consultas ni abrió conexiones contra producción. La evidencia proporcionada no incluye nombres, documentos, teléfonos, correos, direcciones, historias clínicas, comprobantes de pago ni imágenes.

Las consultas siguientes devuelven únicamente agregados, estados o nombres de roles. Deben ejecutarse con una cuenta de solo lectura después de verificar la base activa y los grants.

## 2. Reglas de manejo de resultados

- no exportar filas de negocio;
- no añadir identificadores de personas a filtros o salidas;
- no mostrar valores de `numero_identidad`, `numero_operacion`, email o teléfono;
- no capturar pantallas con datos identificables alrededor del resultado;
- registrar `0` como resultado válido y `PENDIENTE` si no se ejecutó;
- si una consulta falla por drift de columnas, registrar el error estructural sin improvisar una consulta con PII.

## 3. Conteos exactos de tablas relevantes

```sql
SELECT 'users' tabla, COUNT(*) total FROM users
UNION ALL SELECT 'patients', COUNT(*) FROM patients
UNION ALL SELECT 'responsibles', COUNT(*) FROM responsibles
UNION ALL SELECT 'doctors', COUNT(*) FROM doctors
UNION ALL SELECT 'doctor_services', COUNT(*) FROM doctor_services
UNION ALL SELECT 'doctor_schedules', COUNT(*) FROM doctor_schedules
UNION ALL SELECT 'services', COUNT(*) FROM services
UNION ALL SELECT 'appointments', COUNT(*) FROM appointments
UNION ALL SELECT 'items', COUNT(*) FROM items
UNION ALL SELECT 'cashiers', COUNT(*) FROM cashiers
UNION ALL SELECT 'cashier_shifts', COUNT(*) FROM cashier_shifts
UNION ALL SELECT 'cash_movements', COUNT(*) FROM cash_movements
UNION ALL SELECT 'vouchers', COUNT(*) FROM vouchers
UNION ALL SELECT 'voucher_items', COUNT(*) FROM voucher_items
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'voucher_series', COUNT(*) FROM voucher_series
UNION ALL SELECT 'roles', COUNT(*) FROM roles
UNION ALL SELECT 'permissions', COUNT(*) FROM permissions;
```

## 4. Pacientes y responsables

### 4.1 Nulos relevantes y duplicados técnicos

```sql
SELECT COUNT(*) total,
       COALESCE(SUM(user_id IS NULL), 0) sin_usuario,
       COALESCE(SUM(historia_clinica IS NULL OR historia_clinica = ''), 0) sin_historia_legacy,
       COALESCE(SUM(historia_clinica_nueva IS NULL OR historia_clinica_nueva = ''), 0) sin_historia_nueva,
       COALESCE(SUM(numero_identidad IS NULL OR numero_identidad = ''), 0) sin_identidad,
       COALESCE(SUM(channel_id IS NULL), 0) sin_canal,
       COALESCE(SUM(interaction_medium_id IS NULL), 0) sin_medio
FROM patients;

SELECT COUNT(*) grupos_identidad_duplicada,
       COALESCE(SUM(repeticiones - 1), 0) filas_excedentes
FROM (
    SELECT COUNT(*) repeticiones
    FROM patients
    WHERE numero_identidad IS NOT NULL AND numero_identidad <> ''
    GROUP BY numero_identidad
    HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) grupos_historia_duplicada,
       COALESCE(SUM(repeticiones - 1), 0) filas_excedentes
FROM (
    SELECT COUNT(*) repeticiones
    FROM patients
    WHERE historia_clinica_nueva IS NOT NULL AND historia_clinica_nueva <> ''
    GROUP BY historia_clinica_nueva
    HAVING COUNT(*) > 1
) d;
```

### 4.2 Huérfanos

```sql
SELECT COALESCE(SUM(p.user_id IS NOT NULL AND u.id IS NULL), 0) paciente_usuario_huerfano,
       COALESCE(SUM(p.channel_id IS NOT NULL AND c.id IS NULL), 0) paciente_canal_huerfano,
       COALESCE(SUM(p.interaction_medium_id IS NOT NULL AND im.id IS NULL), 0) paciente_medio_huerfano
FROM patients p
LEFT JOIN users u ON u.id = p.user_id
LEFT JOIN channels c ON c.id = p.channel_id
LEFT JOIN interaction_media im ON im.id = p.interaction_medium_id;

SELECT COUNT(*) responsables_sin_paciente
FROM responsibles r
LEFT JOIN patients p ON p.id = r.patient_id
WHERE p.id IS NULL;
```

## 5. Profesionales, servicios y horarios

```sql
SELECT COALESCE(SUM(s.id IS NULL), 0) doctores_sin_especialidad
FROM doctors d
LEFT JOIN specialties s ON s.id = d.specialty_id;

SELECT COUNT(*) filas_doctor_service_huerfanas
FROM doctor_services ds
LEFT JOIN doctors d ON d.id = ds.doctor_id
LEFT JOIN services s ON s.id = ds.service_id
WHERE d.id IS NULL OR s.id IS NULL;

SELECT COUNT(*) pares_doctor_servicio_duplicados,
       COALESCE(SUM(repeticiones - 1), 0) filas_excedentes
FROM (
    SELECT COUNT(*) repeticiones
    FROM doctor_services
    GROUP BY doctor_id, service_id
    HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) horarios_huerfanos
FROM doctor_schedules ds
LEFT JOIN doctors d ON d.id = ds.doctor_id
WHERE d.id IS NULL;

SELECT COALESCE(SUM(hora_fin <= hora_inicio), 0) horario_fin_no_posterior,
       COALESCE(SUM(duracion_cita IS NULL OR duracion_cita = 0), 0) duracion_invalida,
       COALESCE(SUM(dia_semana < 0 OR dia_semana > 7), 0) dia_fuera_rango
FROM doctor_schedules;
```

El rango semántico exacto de `dia_semana` debe confirmarse en código/negocio; la última condición solo detecta valores claramente incompatibles.

## 6. Citas

### 6.1 Estados realmente utilizados

```sql
SELECT estado_cita, COUNT(*) total
FROM appointments
GROUP BY estado_cita
ORDER BY estado_cita;

SELECT estado_pagado, COUNT(*) total
FROM appointments
GROUP BY estado_pagado
ORDER BY estado_pagado;
```

Los nombres de estado no son PII y son imprescindibles para reconciliar el enum.

### 6.2 Nulos, importes y huérfanos

```sql
SELECT COUNT(*) total,
       COALESCE(SUM(patient_id IS NULL), 0) sin_patient_id,
       COALESCE(SUM(doctor_id IS NULL), 0) sin_doctor_id,
       COALESCE(SUM(service_id IS NULL), 0) sin_service_id,
       COALESCE(SUM(additional_rate_id IS NULL), 0) sin_additional_rate_id,
       COALESCE(SUM(fecha_cita IS NULL), 0) sin_fecha,
       COALESCE(SUM(hora_cita IS NULL), 0) sin_hora,
       COALESCE(SUM(precio_programado < 0), 0) precio_negativo,
       COALESCE(SUM(total_pagado < 0), 0) pagado_negativo,
       COALESCE(SUM(saldo_pendiente < 0), 0) saldo_negativo,
       COALESCE(SUM(ABS(precio_programado - total_pagado - saldo_pendiente) > 0.01), 0) formula_no_cuadra
FROM appointments;

SELECT COALESCE(SUM(u.id IS NULL), 0) usuario_huerfano,
       COALESCE(SUM(p.id IS NULL), 0) paciente_huerfano,
       COALESCE(SUM(d.id IS NULL), 0) doctor_huerfano,
       COALESCE(SUM(s.id IS NULL), 0) servicio_huerfano,
       COALESCE(SUM(ar.id IS NULL), 0) tarifa_huerfana
FROM appointments a
LEFT JOIN users u ON u.id = a.user_id
LEFT JOIN patients p ON p.id = a.patient_id
LEFT JOIN doctors d ON d.id = a.doctor_id
LEFT JOIN services s ON s.id = a.service_id
LEFT JOIN additional_rates ar ON ar.id = a.additional_rate_id;
```

### 6.3 Duplicados técnicos

```sql
SELECT COUNT(*) grupos_numero_cita_duplicado,
       COALESCE(SUM(repeticiones - 1), 0) filas_excedentes
FROM (
    SELECT COUNT(*) repeticiones
    FROM appointments
    GROUP BY numero_cita
    HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) posibles_colisiones_horarias
FROM (
    SELECT doctor_id, fecha_cita, hora_cita, COUNT(*) repeticiones
    FROM appointments
    WHERE estado_cita NOT IN ('CANCELADO', 'NO_ASISTIO')
    GROUP BY doctor_id, fecha_cita, hora_cita
    HAVING COUNT(*) > 1
) d;
```

La segunda consulta es un indicador, no prueba por sí sola un error: podrían existir duraciones, recursos o reglas todavía no modeladas.

### 6.4 Resultados productivos confirmados

| Métrica agregada | Resultado |
|---|---:|
| Total de citas | 9 |
| Usuarios creadores distintos | 3 |
| Citas sin `user_id` | 0 |
| Citas sin `patient_id` | 0 |
| Citas sin `doctor_id` | 0 |
| Citas sin `service_id` | 0 |
| Duplicados exactos `doctor_id + fecha_cita + hora_cita` | 0 |
| Estado `ATENDIDO` | 9 |

**CONFIRMADO EN PRODUCCIÓN:** las nueve filas actuales están en `ATENDIDO`. Esto describe la distribución observada, no reduce el enum: el DDL confirma otros nueve estados válidos además de `ATENDIDO`.

La ausencia actual de duplicados exactos no sustituye una protección de concurrencia. El DDL no contiene unique médico+fecha+hora.

## 7. Comprobantes, líneas y pagos

### 7.1 Distribuciones seguras

```sql
SELECT tipo_comprobante, COUNT(*) total
FROM vouchers
GROUP BY tipo_comprobante
ORDER BY tipo_comprobante;

SELECT estado, COUNT(*) total
FROM vouchers
GROUP BY estado
ORDER BY estado;

SELECT estado_sunat, COUNT(*) total
FROM vouchers
GROUP BY estado_sunat
ORDER BY estado_sunat;

SELECT metodo_pago, COUNT(*) total, SUM(monto) monto_agregado
FROM payments
GROUP BY metodo_pago
ORDER BY metodo_pago;
```

### 7.2 Huérfanos e inconsistencias

```sql
SELECT COUNT(*) vouchers_sin_lineas
FROM vouchers v
LEFT JOIN voucher_items vi ON vi.voucher_id = v.id
WHERE vi.id IS NULL;

SELECT COUNT(*) lineas_sin_voucher
FROM voucher_items vi
LEFT JOIN vouchers v ON v.id = vi.voucher_id
WHERE v.id IS NULL;

SELECT COUNT(*) pagos_sin_voucher
FROM payments p
LEFT JOIN vouchers v ON v.id = p.voucher_id
WHERE v.id IS NULL;

SELECT COUNT(*) pagos_sin_turno
FROM payments p
LEFT JOIN cashier_shifts cs ON cs.id = p.cashier_shift_id
WHERE cs.id IS NULL;

SELECT COALESCE(SUM(monto <= 0), 0) pagos_no_positivos,
       COALESCE(SUM(numero_operacion IS NULL OR numero_operacion = ''), 0) pagos_sin_operacion
FROM payments;

SELECT COUNT(*) grupos_operacion_duplicada,
       COALESCE(SUM(repeticiones - 1), 0) filas_excedentes
FROM (
    SELECT COUNT(*) repeticiones
    FROM payments
    WHERE numero_operacion IS NOT NULL AND numero_operacion <> ''
    GROUP BY numero_operacion
    HAVING COUNT(*) > 1
) d;
```

No se listan los números de operación duplicados.

### 7.3 Conciliación agregada voucher/pago

```sql
SELECT COUNT(*) vouchers_con_diferencia,
       COALESCE(SUM(ABS(total - total_pagado)), 0) diferencia_absoluta_agregada
FROM (
    SELECT v.id,
           v.total,
           COALESCE(SUM(p.monto), 0) total_pagado
    FROM vouchers v
    LEFT JOIN payments p ON p.voucher_id = v.id
    WHERE v.estado <> 'ANULADO'
    GROUP BY v.id, v.total
) x
WHERE ABS(total - total_pagado) > 0.01;
```

Esta comparación no incorpora políticas de crédito, adelantos o devoluciones todavía no modeladas; sirve para identificar el universo a revisar.

## 8. Caja

```sql
SELECT estado, COUNT(*) total
FROM cashier_shifts
GROUP BY estado
ORDER BY estado;

SELECT COUNT(*) cajas_con_mas_de_un_turno_abierto
FROM (
    SELECT cashier_id
    FROM cashier_shifts
    WHERE estado = 'ABIERTO'
    GROUP BY cashier_id
    HAVING COUNT(*) > 1
) d;

SELECT COALESCE(SUM(estado = 'ABIERTO' AND cerrado_en IS NOT NULL), 0) abierto_con_fecha_cierre,
       COALESCE(SUM(estado = 'CERRADO' AND cerrado_en IS NULL), 0) cerrado_sin_fecha,
       COALESCE(SUM(estado = 'CERRADO' AND monto_contado IS NULL), 0) cerrado_sin_conteo,
       COALESCE(SUM(estado = 'CERRADO' AND diferencia IS NULL), 0) cerrado_sin_diferencia
FROM cashier_shifts;

SELECT COUNT(*) movimientos_sin_turno
FROM cash_movements cm
LEFT JOIN cashier_shifts cs ON cs.id = cm.cashier_shift_id
WHERE cs.id IS NULL;

SELECT tipo, COUNT(*) total, SUM(monto) monto_agregado
FROM cash_movements
GROUP BY tipo
ORDER BY tipo;
```

## 9. Inventario

```sql
SELECT COUNT(*) total_items,
       COALESCE(SUM(tipo = 'PRODUCTO'), 0) productos,
       COALESCE(SUM(tipo = 'SERVICIO'), 0) servicios,
       COALESCE(SUM(tipo = 'PRODUCTO' AND stock_actual IS NULL), 0) productos_sin_stock,
       COALESCE(SUM(tipo = 'PRODUCTO' AND stock_actual < 0), 0) productos_stock_negativo,
       COALESCE(SUM(tipo = 'SERVICIO' AND stock_actual IS NOT NULL), 0) servicios_con_stock
FROM items;

SELECT COUNT(*) codigos_barra_duplicados,
       COALESCE(SUM(repeticiones - 1), 0) filas_excedentes
FROM (
    SELECT COUNT(*) repeticiones
    FROM items
    WHERE codigo_barras IS NOT NULL AND codigo_barras <> ''
    GROUP BY codigo_barras
    HAVING COUNT(*) > 1
) d;
```

## 10. Series y correlativos

```sql
SELECT COUNT(*) grupos_serie_duplicada,
       COALESCE(SUM(repeticiones - 1), 0) filas_excedentes
FROM (
    SELECT COUNT(*) repeticiones
    FROM voucher_series
    GROUP BY tipo_comprobante, serie
    HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) documentos_correlativo_duplicado,
       COALESCE(SUM(repeticiones - 1), 0) filas_excedentes
FROM (
    SELECT COUNT(*) repeticiones
    FROM vouchers
    GROUP BY tipo_comprobante, serie, correlativo
    HAVING COUNT(*) > 1
) d;

SELECT COUNT(*) series_con_contador_menor_al_emitido
FROM voucher_series vs
JOIN (
    SELECT tipo_comprobante, serie, MAX(correlativo) max_emitido
    FROM vouchers
    GROUP BY tipo_comprobante, serie
) v
  ON v.tipo_comprobante = vs.tipo_comprobante
 AND v.serie = vs.serie
WHERE vs.correlativo_actual < v.max_emitido;
```

## 11. Roles y permisos productivos

La salida autorizada incluye el nombre exacto del rol y cantidades, nunca usuarios.

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

SELECT COUNT(*) usuarios_sin_rol
FROM users u
LEFT JOIN model_has_roles mhr
  ON mhr.model_id = u.id
 AND mhr.model_type = 'App\\Models\\User'
WHERE mhr.role_id IS NULL;

SELECT COUNT(*) total_permisos FROM permissions;

SELECT COUNT(*) asignaciones_rol,
       COUNT(DISTINCT role_id) roles_con_permisos,
       COUNT(DISTINCT permission_id) permisos_asignados
FROM role_has_permissions;

SELECT COUNT(*) asignaciones_directas_usuario
FROM model_has_permissions
WHERE model_type = 'App\\Models\\User';
```

**CONFIRMADO EN PRODUCCIÓN:** los roles productivos básicos requeridos por el gate fueron confirmados. Las distribuciones detalladas de asignaciones/permisos permanecen pendientes y no se registran identidades de usuarios.

## 12. Resultados incorporados y pendientes

| Área | Resultado |
|---|---|
| Total de `appointments` | CONFIRMADO: 9 |
| Autoría básica de `appointments` | CONFIRMADO: 3 creadores distintos; 0 sin `user_id` |
| FK obligatorias observadas en citas | CONFIRMADO: 0 sin paciente, médico o servicio |
| Estados de citas | CONFIRMADO: `ATENDIDO = 9`; enum completo confirmado por DDL |
| Colisión exacta médico+fecha+hora | CONFIRMADO: 0 grupos actuales |
| Roles productivos básicos | CONFIRMADO |
| Conteos exactos de otras tablas | PENDIENTE |
| Nulos y duplicados | PENDIENTE |
| Huérfanos | PENDIENTE |
| Distribución de comprobantes | PENDIENTE |
| Pagos y conciliación | PENDIENTE |
| Caja | PENDIENTE |
| Stock negativo | PENDIENTE |
| Series/correlativos | PENDIENTE |
| Asignaciones detalladas de roles/permisos | PENDIENTE |
