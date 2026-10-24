# Preflight, esquema, respaldo y rollback — revisión integral PR72

**Documento de preparación. No se accedió a Hostinger, producción ni pacientes reales. No se publicó ni se hizo merge.** Main contrastado en GitHub: `a4e2a9b0f9e517c55be10232321391b1dc970e73`. La revisión y autorización productiva siguen pendientes.

## Cinco migraciones, en orden

| Archivo exacto | Efecto | Dependencia |
|---|---|---|
| `2026_10_23_010000_add_procedure_request_identity.php` | Solicitudes: `procedure_family` nullable varchar32, `label_snapshot` nullable varchar500 | `appointment_requested_procedures` previa |
| `2026_10_23_020000_create_patient_operational_alerts.php` | Crea `patient_operational_alerts` y `patient_operational_alert_events` | patients, appointments, users |
| `2026_10_23_030000_create_medical_procedure_followups.php` | Crea `medical_procedure_followups` y `medical_procedure_followup_events` | Solicitudes, appointments, patients, doctors, users |
| `2026_10_23_040000_preserve_clinical_order_details.php` | Solicitudes: `request_details` JSON nullable. Revisiones: `clinical_order_ciphertext` MEDIUMTEXT nullable | Solicitudes y `appointment_operational_reviews` previas |
| `2026_10_23_050000_enable_personal_procedures_and_manual_followups.php` | Crea `doctor_procedure_suggestions`; solicitudes: FK UUID nullable `reusable_procedure_id`; seguimiento: FK `requested_procedure_id` pasa a nullable, `origin` varchar16 default AUTOMATIC, `manual_reason_ciphertext` MEDIUMTEXT nullable, `general_key` varchar16 nullable | Las cuatro anteriores, users y doctors |

Total: cinco tablas nuevas. No se alteran tablas de pagos, comprobantes, pacientes ni citas. No hay roles, categorías de permisos ni estados MySQL de citas nuevos. No se agregan precios o servicios tarifados.

## Columnas, índices y FKs

[DDL exacto](ESQUEMA-PR72.md) y [JSON completo](qa/round2/local-schema.json), obtenidos con SHOW CREATE TABLE en MariaDB11.8.9 local, sin filas. No son un esquema productivo inspeccionado.

- Alertas: id, patient_id, active, version, category, incident_on, reason_ciphertext, created_by_user_id, updated_by_user_id, timestamps. Único `patient_operational_alerts_patient_id_unique`; FKs a paciente y ambos usuarios.
- Eventos de alerta: id, alert_id, appointment_id, actor_user_id, request_key, action, snapshot_ciphertext MEDIUMTEXT, occurred_at. Único `poa_events_actor_request_unique` (actor/UUID); FKs a alerta, cita y actor.
- Seguimiento: id, requested_procedure_id nullable, appointment_id, patient_id, doctor_id, procedure_family, procedure_label, requested_at, source_active, status, version, planned_review_on, notes_ciphertext, updated_by_user_id, timestamps y las tres columnas050000. Únicos `mpf_request_unique` (solicitud) y `mpf_general_unique` (médico/cita/general). Cola `mpf_doctor_queue` (médico/estado/fecha). FKs a solicitud, cita, paciente, médico y actor.
- Eventos de seguimiento: id, followup_id, actor_user_id, request_key, action, snapshot_ciphertext MEDIUMTEXT, occurred_at. Único `mpfe_operation_unique` (seguimiento/actor/UUID). FKs a seguimiento y actor.
- Sugerencias: UUID id, user_id, doctor_id, normalized_hash varchar64, label_ciphertext MEDIUMTEXT, procedure_family varchar32, timestamps. Único `dps_owner_name_unique` (usuario/médico/hash). FKs a usuario/médico. No FK de paciente/cita, ni precio. El nombre se cifra.
- Solicitudes: nuevo índice/FK `appointment_requested_procedures_reusable_procedure_id_foreign` en MariaDB; SQLite utiliza `arp_reusable_idx`. Las claves PK y los índices implícitos de cada FK se enumeran íntegramente en el DDL. FKs restrictivas: no cascadas de borrado de auditoría.
- Ledger, eventos de procedimientos y controles de versión existentes se reutilizan sin otra tabla.

## Datos históricos y compatibilidad

Las tres migraciones históricas se conservan sin cambios: `2026_10_21_200000_define_direct_appointment_economic_permissions.php`, `2026_10_21_210000_link_users_to_doctors.php`, `2026_10_21_210100_define_medical_role_and_permissions.php`. No se supone que deban ejecutarse otra vez. `users.doctor_id` y ClinicalAccess siguen siendo obligatorios.

Las columnas nuevas son nullable/default. No se importan masivamente etiquetas antiguas a sugerencias ni se reescriben eventos. Al confirmar una solicitud, el médico vinculado puede crear explícitamente su referencia personal. No se promueve automáticamente al catálogo compartido ni tarifario. Las solicitudes clínicas siguen siendo independientes por cita.

Los seguimientos anteriores reciben origin AUTOMATIC, coherente con el único mecanismo anterior. No cambia su estado, IDs, notas ni historia. No se insertan seguimientos generales retroactivos. La FK nula de general evita fabricar solicitudes; GENERAL es única por cita/médico. Los registros por procedimiento mantienen su único por solicitud.

Diagnóstico, observaciones, motivos, notas y snapshots conservan cifrado Laravel. **Conservar APP_KEY y configuración de cifrado**: no rotarlos en esta publicación. Clientes que omiten campos nuevos conservan la orden existente.

Permisos reutilizados: `appointment.clinical.view`, `appointment.clinical.procedures.request`, capacidades clínicas y autorizaciones operativas/documentales anteriores. No se asignan permisos productivos desde esta revisión. Seguimiento requiere cuenta2 verificada, MEDICO, doctor_id válido y acceso propio; un administrador puro no recibe acceso clínico. Roles mixtos médico/administrador siguen bloqueados para retorno manual.

## Riesgos y ensayo

MariaDB ejecuta DDL fuera de una transacción global.050000 puede tomar metadata locks al volver nullable la FK; medir tiempo/espacio con volumen real en copia segura. Sus guardas de existencia permiten reanudar un DDL parcial, pero hay que comprobar tipos, índices y estado antes de reintentar. No marcar migraciones aplicadas manualmente para ocultar fallos.

SQLite reconstruye solo la tabla local de seguimiento para retirar NOT NULL, conservando IDs, secuencia, índices y triggers, con foreign_key_check. Si está poblada requiere ejecutarse fuera de una transacción envolvente. No es el camino de publicación MariaDB/MySQL.

Se probó crear limpio, migrar el esquema anterior poblado con datos ficticios, comparar filas/snapshots y reejecutar050000 en SQLite y MariaDB. **No está disponible una copia del esquema productivo actual**. Antes de publicar: obtener export de solo esquema mediante procedimiento autorizado, guardarlo privado, recrearlo en otra BD aislada y repetir ensayo con datos sintéticos. Esta verificación productiva continúa pendiente; no se presenta como realizada.

## Preflight futuro autorizado

1. Registrar SHA realmente instalado en Hostinger, SHA aprobado de GitHub y diferencias. Volver a verificar despliegue automático: el estado conocido deshabilitado no es garantía. Aprobar artefacto exacto y ventana; no usar un HEAD móvil.
2. Confirmar carpeta física conocida `/home/u676842872/domains/srv-prd01.ceosalud.com/public_html`, document root efectivo y PHP CLI `/usr/bin/php`8.3.35. Verificar versión real MySQL/MariaDB, InnoDB, utf8mb4, espacio y extensiones. No asumir symlinks/releases ni cambiar estructura del host.
3. Comparar migrate:status y columnas/índices/FKs con el DDL y las tres migraciones históricas. Verificar identidad de cuenta2/vínculo, sin modificarla. Resolver migraciones fuera de Git o parcialmente aplicadas y ensayar en copia aislada antes de continuar.
4. Revisar clínicamente el catálogo aportado, clasificación para seguimiento y responsables de alertas. Verificar matriz de roles, incluidos mixtos; no habilitar cuentas adicionales para facilitar el smoke test.

## Respaldo y publicación futura

1. Activar mantenimiento, cerrar nuevas escrituras y drenar trabajos según el mecanismo del ERP. Registrar hora, SHA anterior y manifest de archivos.
2. Respaldar BD de forma consistente, tabla migrations, código y dependencias bloqueadas, configuración, documentos y archivos médicos privados. Guardar .env/APP_KEY cifrados y restringidos; nunca imprimir secretos ni subirlos al repositorio. Registrar hashes/tamaños y probar restauración/lectura cifrada fuera de producción.
3. Preservar storage/documentos y enlaces, propietarios y permisos. No sincronizar una carpeta vacía ni configuración de demo sobre el host. No copiar cuentas, contraseñas ni scripts DEMO70.
4. Publicar únicamente el artefacto aprobado mediante el procedimiento manual Hostinger autorizado. Reutilizar lockfiles, sin composer update. En mantenimiento, aplicar solo migraciones pendientes con PHP confirmado. Nunca migrate:fresh, seed/reset de demo ni migraciones inversas en producción.
5. Renovar cachés mediante procedimiento existente, verificar assets versionados, revisar errores/locks e índices. Ante fallo parcial mantener mantenimiento y examinar el estado antes de reintentar.
6. Smoke test autorizado: login/vínculo, médicos correctos/ajenos, roles mixtos, documentos, llegada/consulta/reevaluación/checks/salida, confirmación sin adelanto, exoneración, economía, privacidad, reprogramación/foco, alertas, popup/reapertura/reutilización, seguimiento automático/manual/general y dropdown. Comprobar fondos completos, descargas y ausencia de citas/pagos por solicitar o seguir procedimientos.
7. Reabrir solo con aceptación humana; observar errores, colas y auditorías. Registrar persona autorizante y SHA instalado.

## Rollback sin pérdida de historia

- Mantener mantenimiento y respaldar todas las escrituras posteriores antes de revertir código. Conservar catálogos personales, solicitudes, motivos, eventos, seguimientos, pagos y documentos.
- Preferir corrección hacia delante. Si se revierte código, usar artefacto previamente ensayado y **mantener las cinco tablas y columnas ampliadas**.050000 es forward-only: down rechaza eliminación incluso sin filas. Otros down rechazan borrar datos presentes. No usar migrate:rollback para publicar.
- Git rollback no revierte BD. Código antiguo puede desconocer generales o restaurar permisos anteriores; no reabrir escrituras sin validar compatibilidad y restricciones nuevas. La prueba de esquema no sustituye ese ensayo.
- No restaurar automáticamente un dump antiguo sobre operaciones nuevas: perdería pagos y auditorías. Reconciliar escrituras posteriores en una copia aislada bajo un plan específico aprobado. Conservar APP_KEY, cifrados y documentos correspondientes.

## Demo

Solo127.0.0.1:8000 y MariaDB33345, esquema descartable `ceosalud_demo_enterprise`, sin .env compartido ni servicios externos. Configuración, credenciales y dumps privados/excluidos de Git. Reset con frase explícita, verificación de nombre/puerto/datadir y respaldo previo; no toca otros worktrees ni procesos ajenos.
