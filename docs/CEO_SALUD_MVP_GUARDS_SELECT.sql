-- Catálogo ya confirmado: 23 permisos web; cinco roles vacíos y COMERCIAL web.
-- No repetir ahora los bloques de catálogo. Conservar para comprobación del corte/post-cambio.
-- Hostinger PR #5: SOLO SELECT; BD correcta, tablas/pivots estándar Spatie verificados.
-- Catálogo sin datos personales. No actualizar ni crear filas/roles/permisos desde este archivo.
SELECT guard_name,LENGTH(guard_name) AS guard_bytes,COUNT(*) AS permissions_count
FROM permissions GROUP BY guard_name,LENGTH(guard_name) ORDER BY guard_name;
SELECT id,name,guard_name,LENGTH(guard_name) AS guard_bytes
FROM permissions ORDER BY guard_name,name,id;
SELECT r.id AS role_id,r.name AS role_name,r.guard_name AS role_guard,
 p.id AS permission_id,p.name AS permission_name,p.guard_name AS permission_guard,
 (SELECT COUNT(*) FROM model_has_permissions mp WHERE mp.permission_id=p.id) AS direct_assignment_count
FROM role_has_permissions rp JOIN roles r ON r.id=rp.role_id
 JOIN permissions p ON p.id=rp.permission_id
WHERE NOT (BINARY r.guard_name <=> BINARY p.guard_name)
ORDER BY r.id,p.id;
SELECT p.id,p.name,p.guard_name,COUNT(*) AS direct_assignment_count
FROM model_has_permissions mp JOIN permissions p ON p.id=mp.permission_id
WHERE NOT (BINARY p.guard_name <=> BINARY 'web')
GROUP BY p.id,p.name,p.guard_name ORDER BY p.id;
-- Las siguientes dos lecturas identifican objetivos/collisiones, y después sirven para comprobación.
SELECT id,name,guard_name,LENGTH(guard_name) AS guard_bytes
FROM roles ORDER BY id;
SELECT name,COUNT(*) AS same_name_rows,
 SUM(guard_name='web') AS web_rows,SUM(LENGTH(guard_name)=0) AS empty_rows
FROM permissions GROUP BY name
HAVING SUM(guard_name='web')>0 AND SUM(LENGTH(guard_name)=0)>0;
SELECT 'roles' AS table_name,COUNT(*) AS rows_count FROM roles
UNION ALL SELECT 'permissions',COUNT(*) FROM permissions
UNION ALL SELECT 'role_has_permissions',COUNT(*) FROM role_has_permissions
UNION ALL SELECT 'model_has_roles',COUNT(*) FROM model_has_roles
UNION ALL SELECT 'model_has_permissions',COUNT(*) FROM model_has_permissions;

-- Condición de transacción/uniqueness que el comando comprueba antes de aplicar/revertir.
SELECT table_name,engine FROM information_schema.tables
WHERE table_schema=DATABASE() AND table_name IN
('roles','permissions','role_has_permissions','model_has_roles','model_has_permissions')
ORDER BY table_name;
SELECT table_name,index_name,non_unique,
 GROUP_CONCAT(column_name ORDER BY seq_in_index) AS indexed_columns
FROM information_schema.statistics WHERE table_schema=DATABASE()
 AND table_name IN ('roles','permissions') AND non_unique=0
GROUP BY table_name,index_name,non_unique ORDER BY table_name,index_name;
