# Preflight, esquema, respaldo y rollback — revisión integral PR72

## Ensayo con estructura exportada — 24/10/2026

**APTO CON CONDICIONES para las cinco ampliaciones de esquema; no autoriza publicación.** [Informe y condiciones](ENSAYO-ESQUEMA-PRODUCTIVO-20261024.md), [evidencia sin datos](qa/schema-production-20261024/). El usuario suministró versiones PHP8.3.35/MariaDB11.8.9, main productivo `a4e2a9b0f9e517c55be10232321391b1dc970e73` y la salida SSH de migrate:status. No se conectó a Hostinger.

El SQL recibido **no era solo estructura**: contenía131 INSERT, incluidos pacientes, citas, usuarios y pagos, y13 hashes bcrypt. No se importó ninguna de esas filas ni se incorporó el SQL a Git. Se extrajo DDL permitido en una copia privada:52 tablas/189 sentencias; sin CREATE DATABASE, USE, DEFINER ni referencias a otro esquema. Original intacto, comprobado por SHA256. No considerar el export libre de secretos.

Se reconstruyeron60 entradas históricas exclusivamente desde la salida aportada, conservando nombres/lotes, incluido batch0, y se contrastaron con el historial técnico del export. Migrate:status local reconoció exactamente las cinco pendientes. Dos bases QA aisladas en127.0.0.1:33346 reprodujeron el esquema y aplicaron las cinco en orden:57 tablas/65 migraciones. La segunda ejecutó desde cero la quinta ya corregida. Se verificaron614 columnas,247 partes de índices y116 relaciones FK, sin huérfanos. Son pruebas de estructura y datos sintéticos; no de volumen ni contenido productivo.

**Cambio indispensable en el PR72:**050000 recupera índices/FKs que falten después de un DDL parcial, valida definiciones existentes y evita repetir el ALTER nullable cuando ya está aplicado. El código anterior `f0290435` no recuperaba el único general ni la FK de referencia reutilizable si la columna ya existía. No se añade una sexta migración. Corrección y runbook permanecen locales; no se hizo push ni se actualizó el PR remoto durante este ensayo.

**Condiciones previas a publicar:** revisar e incorporar esa corrección a la candidata exacta; resolver o aprobar explícitamente el tratamiento del riesgo heredado `cashier_shifts.abierto_en ON UPDATE current_timestamp()` (el cierre sintético cambió la apertura); revisar la diferencia heredada `vouchers.observacion`/`observaciones`; completar respaldo restaurable, APP_KEY, capacidad/locks, permisos efectivos y ventana autorizada. No corregir estas diferencias heredadas mediante SQL improvisado ni confundirlas con efectos de las cinco migraciones.

## Última corrección del envío directo

El botón solo envía solicitudes activas sin seguimiento, con un clic y sin popup. Motivo/fecha iniciales quedan null; los históricos permanecen. Esa corrección no añadió migraciones. El ensayo posterior sí fortalece la recuperación parcial de050000, manteniendo el mismo esquema final y las mismas cinco migraciones. Antes de publicar, volver a contrastar la tabla `migrations` real y ejecutar únicamente las pendientes: no asumir que la demo determina qué se aplicó en producción. Invalidar assets versionados para evitar un cliente antiguo que intente enviar los campos retirados (respuesta422 controlada).

APP_KEY: comprobar su presencia/configuración sin mostrar el valor; validar lectura de un cifrado existente mediante un ensayo autorizado que informe solo éxito/fallo, sin imprimir contenido clínico. No ejecutar key:generate ni rotar la clave durante esta publicación. Conservar respaldo cifrado de configuración y documentos.

Smoke adicional: cero procedimientos→sin botón/API422; radiografía+curación→un clic/redirección; automático cirugía/RM→sin botón; agregar laboratorio→solo nuevo; doble clic/reintento→sin duplicado; motivos históricos intactos y pagos/reevaluación/salida sin cambios. El rollback de código debe mantener esquema, nuevos eventos/filas y motivos previos; preferir corrección hacia delante si el artefacto previo restaura el antiguo envío general.

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

[DDL esperado](ESQUEMA-PR72.md) y [JSON completo](qa/round2/local-schema.json), obtenidos con SHOW CREATE TABLE en MariaDB11.8.9 local, sin filas. El [ensayo nuevo](ENSAYO-ESQUEMA-PRODUCTIVO-20261024.md) los contrasta con el DDL extraído del export suministrado: las siete tablas afectadas coinciden en estructura; las dos diferencias detectadas pertenecen a caja/comprobantes heredados. El export y el DDL neutralizado siguen privados y fuera de Git.

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

MariaDB ejecuta DDL fuera de una transacción global.050000 puede tomar metadata locks al volver nullable la FK; medir tiempo/espacio con volumen representativo en copia segura. Las guardas corregidas recuperan índices/FKs ausentes independientemente de que exista su columna. Se ensayaron cinco interrupciones y reintentos dobles sin alterar filas/auditoría. Una definición existente incompatible, duplicados o referencias huérfanas deben producir rechazo y revisión, nunca borrado automático. No ejecutar dos migradores simultáneos. No marcar migraciones aplicadas manualmente para ocultar fallos.

Las cuatro primeras no son universalmente reentrantes ante cualquier DDL parcial: si fallan, mantener mantenimiento, inventariar objetos y comparar con el DDL esperado antes de preparar una reparación hacia delante revisada. No reintentar a ciegas, no eliminar tablas ni borrar filas de migrations en producción. Que050000 reanude los puntos ensayados no convierte todo el lote en una transacción atómica.

SQLite reconstruye solo la tabla local de seguimiento para retirar NOT NULL, conservando IDs, secuencia, índices y triggers, con foreign_key_check. Si está poblada requiere ejecutarse fuera de una transacción envolvente. No es el camino de publicación MariaDB/MySQL.

Se probó crear limpio, migrar el esquema anterior poblado con datos ficticios, comparar filas/snapshots y reejecutar050000 en SQLite y MariaDB. El24/10 se repitió con DDL extraído de la estructura productiva aportada:60 históricos reconocidos, cinco ampliaciones aplicadas,260 valores sintéticos previos conservados, cifrados legibles, restricciones y flujo de aplicación verificados. Suites posteriores:SQLite1286/11898 aserciones, MariaDB1320/12205 yJS369, sin fallos. No se verificaron contenido real, duración con volumen productivo, backups del host ni APP_KEY productiva; no se importaron pacientes reales.

## Preflight futuro autorizado

1. Registrar SHA realmente instalado en Hostinger, SHA aprobado de GitHub y diferencias. Volver a verificar despliegue automático: el estado conocido deshabilitado no es garantía. Aprobar artefacto exacto y ventana; no usar un HEAD móvil.
2. Confirmar carpeta física conocida `/home/u676842872/domains/srv-prd01.ceosalud.com/public_html`, document root efectivo y PHP CLI `/usr/bin/php`8.3.35. Verificar versión real MySQL/MariaDB, InnoDB, utf8mb4, espacio y extensiones. No asumir symlinks/releases ni cambiar estructura del host.
3. Revalidar migrate:status y columnas/índices/FKs contra el inventario aportado el24/10 y las tres migraciones históricas. No reconstruir ni modificar migrations en producción: la reconstrucción fue únicamente local. Verificar identidad de cuenta2/vínculo, sin modificarla. Resolver diferencias heredadas, migraciones fuera de Git o parcialmente aplicadas antes de continuar. Verificar nuevamente que no exista estructura parcial de PR72.
4. Revisar clínicamente el catálogo aportado, clasificación para seguimiento y responsables de alertas. Verificar matriz de roles, incluidos mixtos; no habilitar cuentas adicionales para facilitar el smoke test.

## Respaldo y publicación futura

1. Activar mantenimiento, cerrar nuevas escrituras y drenar trabajos según el mecanismo del ERP. Registrar hora, SHA anterior y manifest de archivos.
2. Respaldar BD de forma consistente, tabla migrations, código y dependencias bloqueadas, configuración, documentos y archivos médicos privados. Guardar .env/APP_KEY cifrados y restringidos; nunca imprimir secretos ni subirlos al repositorio. Registrar hashes/tamaños y probar restauración/lectura cifrada fuera de producción.
3. Preservar storage/documentos y enlaces, propietarios y permisos. No sincronizar una carpeta vacía ni configuración de demo sobre el host. No copiar cuentas, contraseñas ni scripts DEMO70.
4. Publicar únicamente el artefacto aprobado mediante el procedimiento manual Hostinger autorizado. Reutilizar lockfiles, sin composer update. En mantenimiento y con un único operador/migrador, aplicar solo las cinco rutas pendientes, una por una en el orden del inventario, con `/usr/bin/php artisan migrate --path=database/migrations/ARCHIVO_EXACTO.php --force`. Sustituir ARCHIVO_EXACTO por cada archivo revisado; verificar exit/status/esquema después de cada uno y detenerse ante el primer fallo. El ensayo usó lotes32–36 al ejecutar una ruta por comando; los60 lotes históricos permanecieron intactos. Nunca migrate:fresh, seed/reset de demo ni migraciones inversas en producción.
5. Renovar cachés mediante procedimiento existente, verificar assets versionados, revisar errores/locks e índices. Ante fallo parcial mantener mantenimiento y examinar el estado antes de reintentar.
6. Smoke test autorizado: login/vínculo, médicos correctos/ajenos, roles mixtos, documentos, llegada/consulta/reevaluación/checks/salida, confirmación sin adelanto, exoneración, economía, privacidad, reprogramación/foco, alertas, popup/reapertura/reutilización, seguimiento automático/manual/general y dropdown. Comprobar fondos completos, descargas y ausencia de citas/pagos por solicitar o seguir procedimientos.
7. Reabrir solo con aceptación humana; observar errores, colas y auditorías. Registrar persona autorizante y SHA instalado.

## Rollback sin pérdida de historia

- Mantener mantenimiento y respaldar todas las escrituras posteriores antes de revertir código. Conservar catálogos personales, solicitudes, motivos, eventos, seguimientos, pagos y documentos.
- Preferir corrección hacia delante. Si se revierte código, usar artefacto previamente ensayado y **mantener las cinco tablas y columnas ampliadas**.050000 es forward-only: down rechaza eliminación incluso sin filas. Otros down rechazan borrar datos presentes. No usar migrate:rollback para publicar.
- Git rollback no revierte BD. Código antiguo puede desconocer generales o restaurar permisos anteriores; no reabrir escrituras sin validar compatibilidad y restricciones nuevas. La prueba de esquema no sustituye ese ensayo.
- No restaurar automáticamente un dump antiguo sobre operaciones nuevas: perdería pagos y auditorías. Reconciliar escrituras posteriores en una copia aislada bajo un plan específico aprobado. Conservar APP_KEY, cifrados y documentos correspondientes.
- Ante fallo DDL antes de reabrir, conservar los objetos ya creados y la evidencia del error;050000 corregida puede reanudar los límites probados, previa verificación. Si existe un conflicto de índice/FK/tipo, parar y preparar reparación revisada. No ejecutar down ni registrar manualmente un Ran falso.

## Demo

Solo127.0.0.1:8000 y MariaDB33345, esquema descartable `ceosalud_demo_enterprise`, sin .env compartido ni servicios externos. Configuración, credenciales y dumps privados/excluidos de Git. Reset con frase explícita, verificación de nombre/puerto/datadir y respaldo previo; no toca otros worktrees ni procesos ajenos.
