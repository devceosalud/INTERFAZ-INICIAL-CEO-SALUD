# Esquema y publicación futura — no ejecutada

## Cambios de esquema

Cuatro migraciones nuevas y aditivas; probadas únicamente en bases descartables:

1. `2026_10_23_010000_add_procedure_request_identity.php`: `procedure_family` nullable32 y `label_snapshot` nullable500 en `appointment_requested_procedures`. Los registros heredados siguen leyéndose; no se reescriben sus historiales.
2. `2026_10_23_020000_create_patient_operational_alerts.php`: `patient_operational_alerts` (único por paciente, versión, vigencia y motivo cifrado) y `patient_operational_alert_events` (actor, cita, UUID, acción, fecha y snapshots cifrados). El índice de idempotencia tiene nombre corto compatible con MariaDB.
3. `2026_10_23_030000_create_medical_procedure_followups.php`: `medical_procedure_followups` (único por solicitud, paciente/cita/médico, estado, fuente activa, fecha prevista, notas cifradas y versión) y `medical_procedure_followup_events` (auditoría clínica privada). Los snapshots cifrados usan MEDIUMTEXT para conservar correctamente el máximo Unicode admitido y la expansión del cifrado/base64; existe regresión de contenido superior a64KiB.

4. `2026_10_23_040000_preserve_clinical_order_details.php`: JSON nullable `request_details` por solicitud y MEDIUMTEXT nullable `clinical_order_ciphertext` en la revisión operativa existente. Conserva lateralidad, carga, proyecciones y dedo; diagnóstico y observaciones cifrados, también en auditoría. No crea tablas. Los clientes anteriores conservan datos al omitir campos nuevos. Su down rechaza datos existentes.

Se conservan todas las tablas y columnas previas. No hay nuevos estados MySQL de citas, roles, categorías de permisos, servicios tarifados ni modificación de pagos. Las relaciones históricas usan claves foráneas restrictivas; no hay cascadas de eliminación de auditoría. Los estados longitudinales se validan con un catálogo explícito de aplicación.

Las tres migraciones históricas permanecen sin cambios respecto de main:

- `2026_10_21_200000_define_direct_appointment_economic_permissions.php`.
- `2026_10_21_210000_link_users_to_doctors.php`.
- `2026_10_21_210100_define_medical_role_and_permissions.php`.

Su presencia no implica que deban volver a ejecutarse en producción. Se conserva `users.doctor_id`, su relación segura y la autorización de `ClinicalAccess`.

## Preflight para una publicación autorizada posterior

1. Revisar y aprobar el Draft PR. Confirmar el main y artefacto exactos; volver a comprobar el mecanismo Hostinger conectado a main antes de cualquier publicación.
2. Programar mantenimiento y respaldo consistente de BD, código, documentos privados y configuración/`APP_KEY`, sin imprimir secretos. Registrar SHA previo y manifest de archivos. Verificar restauración fuera de producción.
3. Comparar `migrate:status`, tipos y claves del esquema real con el código, incluyendo las tres migraciones históricas y los vínculos médicos. Revisar catálogo de la orden aportada y responsables de alertas. Verificar que `medical_followups.authorized_user_id=2` identifica la cuenta verificada de Quiroz, sin sustituir el vínculo `doctor_id`. Esta comprobación productiva está pendiente de autorización; no fue simulada como realizada.
4. Con mantenimiento activo y respaldo comprobado, publicar el artefacto aprobado y aplicar únicamente las cuatro migraciones nuevas pendientes. No usar migraciones inversas, `migrate:fresh` ni reset de datos en producción. Conservar el `APP_KEY` y los archivos privados.
5. Validar autenticación, doctor vinculado, permisos mixtos, documentos, alertas, seguimiento y flujos existentes. Comprobar filas cromáticas y Operativa antes de reabrir escrituras. Revisar índices y errores de migración antes de reintentar si existiera una interrupción.

Hostinger usa una carpeta física: `/home/u676842872/domains/srv-prd01.ceosalud.com/public_html`. No se supone un esquema de symlinks/release. El PHP CLI conocido es `/usr/bin/php`8.3.35. Esta información orienta el plan; no se accedió al host ni se cambió su HEAD, archivos, datos, `.env` o configuración de despliegue.

## Rollback sin pérdida de trazabilidad

- Mantener mantenimiento y conservar un respaldo adicional de cualquier operación nueva antes de revertir código.
- Restaurar el artefacto previo verificado **sin eliminar las cuatro tablas nuevas ni las columnas nullable**. Los métodos `down()` se niegan a eliminar datos auditados; esa negativa está probada.
- No restaurar automáticamente un dump antiguo encima de operaciones posteriores: perdería pagos o auditorías. Una recuperación de BD requiere reconciliación y conservación de las escrituras nuevas, fuera de este trabajo.
- Un código anterior desconoce nuevas solicitudes/seguimientos y puede restaurar permisos manuales anteriores. No reabrir escrituras con ese código sin una candidata validada que conserve las restricciones nuevas. Preferir corrección hacia delante y repetir permisos/lecturas antes de salir de mantenimiento.
- Conservar `APP_KEY`, snapshots y archivos privados para poder leer toda la información cifrada e histórica.

## Demo local y restauración

La demo final usa solo `ceosalud_demo_enterprise`, MariaDB en127.0.0.1:33345 y PHP en127.0.0.1:8000. Los scripts comprueban esquema, cuenta restringida, puerto, versión y datadir físico antes de mantenimiento. El reinicio exige confirmación, hace respaldo SQL y restaura veinte citas DEMO70 (incluida CE-EX exonerada); nunca consume `.env`.

La demo anterior y sus evidencias se conservaron en sus worktrees. Solo se reemplaza el proceso web local que se identificó por PID y comando; no se eliminan checkouts ni se modifica producción. Credenciales, configuraciones, dumps y launchers quedan privados y excluidos de Git.
