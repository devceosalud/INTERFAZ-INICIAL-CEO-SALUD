-- CEO Salud: inventario SOLO LECTURA para el responsable autorizado.
-- Ejecutar por secciones, con cuenta SELECT y esquema productivo seleccionado.
-- No contiene escrituras ni datos identificables de pacientes.
-- Primero ejecutar 0. Si falta una tabla/columna, NO ejecutar su bloque posterior.
-- Compatibilidad: MySQL/MariaDB; sin CTE, funciones window ni cambios de sesion.

-- 0. Version, esquema real, migrations, claves y deduplicacion financiera.
SELECT DATABASE() AS database_name, VERSION() AS database_version;
SELECT migration, batch FROM migrations ORDER BY id;
SELECT table_name, column_name, column_type, is_nullable
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name IN
('specialties','doctors','services','doctor_services','doctor_schedules','sites',
 'appointments','voucher_items','vouchers','payments','cashier_shifts','voucher_series',
 'appointment_documents','appointment_events','appointment_contingencies',
 'appointment_credit_applications','appointment_refund_requests','appointment_pilot_cash_contexts')
ORDER BY table_name, ordinal_position;
SELECT table_name, column_name, referenced_table_name, referenced_column_name
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL
AND (table_name IN ('doctor_services','doctor_schedules','appointments','voucher_items','payments')
     OR referenced_table_name IN ('doctors','services','doctor_services','doctor_schedules','appointments'))
ORDER BY table_name, column_name;
SELECT index_name, non_unique, column_name
FROM information_schema.statistics
WHERE table_schema = DATABASE() AND table_name = 'payments' AND column_name = 'bank_identity_key';

-- 1. Catalogos (nombres profesionales y comerciales, nunca pacientes).
SELECT s.id, s.nombre, s.estado, s.created_at, s.updated_at,
 (SELECT COUNT(*) FROM doctors d WHERE d.specialty_id=s.id) AS doctors_count,
 (SELECT COUNT(*) FROM services v WHERE v.specialty_id=s.id) AS services_count
FROM specialties s ORDER BY s.estado, s.nombre, s.id;
SELECT d.id, d.nombre, d.estado, d.specialty_id, s.nombre AS specialty,
 d.created_at, d.updated_at,
 (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id=d.id) AS appointments_count,
 (SELECT COUNT(*) FROM doctor_schedules h WHERE h.doctor_id=d.id) AS schedules_count,
 (SELECT COUNT(*) FROM voucher_items i WHERE i.doctor_id=d.id) AS voucher_items_count
FROM doctors d LEFT JOIN specialties s ON s.id=d.specialty_id ORDER BY d.estado, d.nombre, d.id;
SELECT v.id, v.nombre, v.estado, v.specialty_id, s.nombre AS specialty,
 v.created_at, v.updated_at,
 (SELECT COUNT(*) FROM appointments a WHERE a.service_id=v.id) AS appointments_count
FROM services v LEFT JOIN specialties s ON s.id=v.specialty_id ORDER BY v.nombre, v.id;
SELECT ds.id, ds.doctor_id, d.nombre AS doctor, d.estado AS doctor_state,
 ds.service_id, v.nombre AS service, v.estado AS service_state,
 ds.precio_primera_consulta, ds.precio_reconsulta, ds.dias_reconsulta, ds.estado,
 ds.created_at, ds.updated_at,
 (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id=ds.doctor_id AND a.service_id=ds.service_id) AS pair_history_count
FROM doctor_services ds JOIN doctors d ON d.id=ds.doctor_id JOIN services v ON v.id=ds.service_id
ORDER BY d.nombre, v.nombre, ds.estado, ds.id;
-- pair_history_count NO demuestra uso de una fila doctor_services concreta:
-- appointments guarda doctor_id/service_id y snapshot, no doctor_service_id.
SELECT doctor_id, service_id, COUNT(*) AS active_count,
 GROUP_CONCAT(id ORDER BY id) AS assignment_ids,
 MIN(precio_primera_consulta) AS min_first, MAX(precio_primera_consulta) AS max_first,
 MIN(precio_reconsulta) AS min_followup, MAX(precio_reconsulta) AS max_followup
FROM doctor_services WHERE estado='ACTIVO'
GROUP BY doctor_id, service_id HAVING COUNT(*)>1;
SELECT id, doctor_id, service_id, estado, precio_primera_consulta, precio_reconsulta
FROM doctor_services WHERE precio_primera_consulta IS NULL OR precio_reconsulta IS NULL
 OR precio_primera_consulta<=0 OR precio_reconsulta<0;
SELECT UPPER(TRIM(nombre)) AS normalized_name, COUNT(*) AS copies, GROUP_CONCAT(id ORDER BY id) AS ids
FROM doctors GROUP BY UPPER(TRIM(nombre)) HAVING COUNT(*)>1;
SELECT specialty_id, UPPER(TRIM(nombre)) AS normalized_name, COUNT(*) AS copies,
 GROUP_CONCAT(id ORDER BY id) AS ids
FROM services GROUP BY specialty_id, UPPER(TRIM(nombre)) HAVING COUNT(*)>1;

-- 2. Sospechas de prueba: SOLO candidatos; CEO debe confirmar identidad oficial.
SELECT d.id, d.nombre, d.estado,
 (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id=d.id) AS appointments_count,
 (SELECT COUNT(*) FROM voucher_items i WHERE i.doctor_id=d.id) AS voucher_items_count
FROM doctors d WHERE UPPER(d.nombre) REGEXP 'PRUEBA|FICTICIO|DEMO|TEST|(^|[[:space:]])QA([[:space:]]|$)';
SELECT id, nombre, estado FROM specialties
WHERE UPPER(nombre) REGEXP 'PRUEBA|FICTICIO|DEMO|TEST|(^|[[:space:]])QA([[:space:]]|$)';
SELECT id, nombre, estado FROM services
WHERE UPPER(nombre) REGEXP 'PRUEBA|FICTICIO|DEMO|TEST|(^|[[:space:]])QA([[:space:]]|$)';

-- 3. Horarios: requiere doctor_schedules.site_id y sites del bloque 0.
SELECT h.id, h.doctor_id, d.nombre AS doctor, d.estado AS doctor_state,
 h.site_id, s.nombre AS site, h.fecha_cita, h.dia_semana,
 h.hora_inicio, h.hora_fin, h.duracion_cita, h.estado, h.created_at, h.updated_at,
 (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id=h.doctor_id
  AND (a.fecha_cita=h.fecha_cita OR (h.fecha_cita IS NULL AND WEEKDAY(a.fecha_cita)+1=h.dia_semana))
  AND a.hora_cita>=h.hora_inicio AND a.hora_cita<h.hora_fin) AS appointments_starting_in_block
FROM doctor_schedules h JOIN doctors d ON d.id=h.doctor_id LEFT JOIN sites s ON s.id=h.site_id
ORDER BY d.nombre, h.fecha_cita, h.dia_semana, h.hora_inicio, h.id;
-- Count anterior incluye historia/reservas; no equivale a ocupacion ni a FK de horario.
SELECT h1.id AS block_a, h2.id AS block_b, h1.doctor_id,
 h1.fecha_cita AS date_a, h2.fecha_cita AS date_b,
 h1.dia_semana AS weekday_a, h2.dia_semana AS weekday_b,
 h1.site_id AS site_a, h2.site_id AS site_b,
 h1.duracion_cita AS duration_a, h2.duracion_cita AS duration_b,
 CASE WHEN h1.site_id <=> h2.site_id AND h1.duracion_cita=h2.duracion_cita
 THEN 'REVISAR_UNION_COMPATIBLE' ELSE 'INCOMPATIBLE_REVISAR_ADMISION' END AS classification
FROM doctor_schedules h1 JOIN doctor_schedules h2 ON h1.id<h2.id AND h1.doctor_id=h2.doctor_id
 AND h1.estado='ACTIVO' AND h2.estado='ACTIVO'
 AND h1.hora_inicio<h2.hora_fin AND h2.hora_inicio<h1.hora_fin
 AND (h1.fecha_cita=h2.fecha_cita
  OR (h1.fecha_cita IS NULL AND h2.fecha_cita IS NULL AND h1.dia_semana=h2.dia_semana)
  OR (h1.fecha_cita IS NULL AND h2.fecha_cita IS NOT NULL AND h1.dia_semana=WEEKDAY(h2.fecha_cita)+1)
  OR (h2.fecha_cita IS NULL AND h1.fecha_cita IS NOT NULL AND h2.dia_semana=WEEKDAY(h1.fecha_cita)+1));
SELECT id, doctor_id, fecha_cita, dia_semana, hora_inicio, hora_fin, duracion_cita, estado
FROM doctor_schedules WHERE hora_inicio>=hora_fin OR duracion_cita IS NULL OR duracion_cita=0;

-- 4. Historia agregada y dependencias economicas. No IDs/documentos de pacientes.
SELECT doctor_id, service_id, estado_cita, estado_agenda, tipo_agendamiento,
 precio_programado, COUNT(*) AS appointments_count, MIN(fecha_cita) AS first_date, MAX(fecha_cita) AS last_date
FROM appointments GROUP BY doctor_id, service_id, estado_cita, estado_agenda, tipo_agendamiento, precio_programado
ORDER BY doctor_id, service_id, precio_programado;
SELECT a.doctor_id, a.service_id, COUNT(DISTINCT a.id) AS appointments_linked,
 COUNT(DISTINCT v.id) AS documents_count, COUNT(DISTINCT p.id) AS payments_count
FROM appointments a JOIN voucher_items i ON i.item_id=a.id
 AND i.item_type IN ('cita', CONCAT('App',CHAR(92),'Models',CHAR(92),'Appointment'))
JOIN vouchers v ON v.id=i.voucher_id
LEFT JOIN vouchers child ON child.parent_voucher_id=v.id
LEFT JOIN payments p ON p.voucher_id=v.id OR p.voucher_id=child.id
GROUP BY a.doctor_id, a.service_id;
-- Servicios vendidos directamente: solo si el sistema usa este morph.
SELECT i.item_id AS service_id, COUNT(*) AS lines_count, COUNT(DISTINCT i.voucher_id) AS documents_count
FROM voucher_items i WHERE i.item_type IN ('servicio', CONCAT('App',CHAR(92),'Models',CHAR(92),'Service'))
GROUP BY i.item_id;
-- Dependencias nuevas, ejecutar cada query solo si tabla existe en 0.
SELECT a.doctor_id, a.service_id, COUNT(*) AS documents_count
FROM appointment_documents x JOIN appointments a ON a.id=x.appointment_id GROUP BY a.doctor_id, a.service_id;
SELECT a.doctor_id, a.service_id, COUNT(*) AS events_count
FROM appointment_events x JOIN appointments a ON a.id=x.appointment_id GROUP BY a.doctor_id, a.service_id;
SELECT a.doctor_id, a.service_id, COUNT(*) AS contingencies_count
FROM appointment_contingencies x JOIN appointments a ON a.id=x.appointment_id GROUP BY a.doctor_id, a.service_id;
SELECT a.doctor_id, a.service_id, COUNT(*) AS credit_links_count
FROM appointment_credit_applications x JOIN appointments a
 ON a.id=x.source_appointment_id OR a.id=x.destination_appointment_id
GROUP BY a.doctor_id, a.service_id;
SELECT a.doctor_id, a.service_id, COUNT(*) AS refund_requests_count
FROM appointment_refund_requests x JOIN appointments a ON a.id=x.appointment_id
GROUP BY a.doctor_id, a.service_id;

-- 5. Roles/capabilities: union de permisos directos y del rol, sin nombres/emails/hashes.
SELECT mr.model_id AS user_id, r.name AS role, r.guard_name
FROM model_has_roles mr JOIN roles r ON r.id=mr.role_id
WHERE mr.model_type=CONCAT('App',CHAR(92),'Models',CHAR(92),'User')
 AND r.name IN ('COMERCIAL','ADMISION','ADMINISTRADOR') ORDER BY mr.model_id, r.name;
SELECT mr.model_id AS user_id, p.name AS capability, p.guard_name, 'ROLE' AS source
FROM model_has_roles mr JOIN role_has_permissions rp ON rp.role_id=mr.role_id JOIN permissions p ON p.id=rp.permission_id
JOIN roles r ON r.id=mr.role_id
WHERE mr.model_type=CONCAT('App',CHAR(92),'Models',CHAR(92),'User')
 AND r.name IN ('COMERCIAL','ADMISION','ADMINISTRADOR') AND p.name LIKE 'appointment.%'
UNION
SELECT mp.model_id AS user_id, p.name AS capability, p.guard_name, 'DIRECT' AS source
FROM model_has_permissions mp JOIN permissions p ON p.id=mp.permission_id
WHERE mp.model_type=CONCAT('App',CHAR(92),'Models',CHAR(92),'User') AND p.name LIKE 'appointment.%';

-- 6. Caja piloto: estados/series, no operaciones bancarias ni saldos individuales.
SELECT user_id, COUNT(*) AS open_shifts FROM cashier_shifts WHERE estado='ABIERTO' GROUP BY user_id;
SELECT id, cashier_id, tipo_comprobante, serie, correlativo_actual, estado
FROM voucher_series WHERE tipo_comprobante='TICKET' ORDER BY serie, id;
SELECT tipo_comprobante, serie, COUNT(*) AS copies FROM voucher_series
GROUP BY tipo_comprobante, serie HAVING COUNT(*)>1;
SELECT actor_user_id, operational_date, cashier_id, cashier_shift_id, origin
FROM appointment_pilot_cash_contexts ORDER BY operational_date, actor_user_id;
