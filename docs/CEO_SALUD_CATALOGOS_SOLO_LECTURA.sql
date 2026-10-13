-- Ejecutar únicamente SELECT; no pacientes, DNI, teléfonos, CMP ni documentos.
SELECT id,codigo,nombre,estado FROM sites ORDER BY id;
SELECT d.id AS doctor_id,d.nombre AS medico,d.estado AS medico_estado,ds.id AS doctor_service_id,ds.service_id,s.nombre AS servicio,ds.precio_primera_consulta,ds.precio_reconsulta,ds.dias_reconsulta,ds.estado AS asignacion_estado FROM doctors d JOIN doctor_services ds ON ds.doctor_id=d.id JOIN services s ON s.id=ds.service_id WHERE d.nombre LIKE '%Quiroz%' ORDER BY d.id,ds.service_id,ds.id;
SELECT id,nombre,tipo_tarifa,tarifa,fecha_inicio,fecha_fin,estado FROM additional_rates ORDER BY id;
