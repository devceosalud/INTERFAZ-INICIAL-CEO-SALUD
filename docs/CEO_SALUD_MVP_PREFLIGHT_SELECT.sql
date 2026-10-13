-- CEO Salud / MVP PR #5: exclusivamente SELECT; ejecutar en la BD correcta con cuenta existente.
-- No demuestra que los registros sean ficticios/reales, solo su existencia y volumen.
SELECT name,guard_name FROM roles ORDER BY guard_name,name;
SELECT 'patients' AS table_name,COUNT(*) AS rows_count FROM patients
UNION ALL SELECT 'appointments',COUNT(*) FROM appointments
UNION ALL SELECT 'payments',COUNT(*) FROM payments
UNION ALL SELECT 'vouchers',COUNT(*) FROM vouchers
UNION ALL SELECT 'voucher_items',COUNT(*) FROM voucher_items
UNION ALL SELECT 'appointment_operations',COUNT(*) FROM appointment_operations
UNION ALL SELECT 'appointment_documents',COUNT(*) FROM appointment_documents;
SELECT MAX(id) AS maximum_operator_id FROM users;
SELECT estado_agenda,estado_cita,COUNT(*) AS rows_count FROM appointments GROUP BY estado_agenda,estado_cita;
SELECT @@sql_mode AS sql_mode,@@session.time_zone AS session_timezone,VERSION() AS server_version;
