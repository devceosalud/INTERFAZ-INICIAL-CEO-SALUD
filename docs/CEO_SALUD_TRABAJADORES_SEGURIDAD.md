# R17–R18 · Identidad y revocación de trabajadores

Rama independiente: codex/mvp-trabajadores-seguridad. Base: PR #5 fc4f278. Sin despliegue ni cambios productivos.

## Comportamiento

- Reutiliza Usuarios y su editor de roles. ADMINISTRADOR activo puede crear/editar identidad y desactivar/reactivar con motivo y confirmación. No concede capacidades financieras ni asigna roles al crear usuarios. El editor mantiene su contrato anterior de un rol; R19 de arrastrar y soltar sigue pendiente.
- DNI de nuevos trabajadores: cadena numérica de ocho dígitos, conserva ceros iniciales, índice único. Legados permanecen sin DNI; un valor heredado sin corregir se conserva. Corregir requiere ocho dígitos y no permite borrar uno registrado. Correo heredado intacto se conserva; nuevos valores se validan. Contraseña nueva mínimo ocho caracteres; vacía al editar conserva el hash.
- is_active=false impide login y nuevas solicitudes autenticadas, incluidos los tokens Sanctum. Rotar auth_version y remember_token impide recuperar sesiones antiguas después de reactivar. Se eliminan tokens y, con driver database, sesiones del destinatario. Con file/Redis/cookie la revocación es lógica en el siguiente request, sin borrar archivos ajenos. No cancela transacciones que ya estaban ejecutándose; no altera caja abierta ni transfiere responsables automáticamente.
- Actuaciones de acceso usan UUID, hash, versión esperada, transacción y bloqueo del rol ADMINISTRADOR y usuarios ordenados. Reintento idéntico no vuelve a revocar; contenido distinto o versión obsoleta falla. No se permite autodesactivación ni retirar el último administrador activo. Dos administradores no pueden desactivarse mutuamente.
- Usuarios desactivados mantienen relaciones, roles, auditoría, pagos y citas históricas. No se pueden asignar como nuevos responsables. La auditoría DB registra actor, destinatario, motivo y campos cambiados, sin contraseñas/documentos/tokens. Logs de aplicación solo IDs y tipo. DNI oculto en serialización general; DTO del editor protegido devuelve solo id/nombre/correo/DNI, no datos de sesión.

## Archivos

- Migración 2026_10_14_170000_add_worker_identity_and_access_state: users.dni nullable único, users.is_active=true y auth_version=0; nueva user_account_events. No copia documentos de pacientes ni actualiza importes o estados de citas.
- User, UserAccountEvent y UserAccountAccess: estado, protección de asignación masiva, auditoría y bloqueos.
- AuthController, Authenticate y AuthServiceProvider: login, control de época y denegación de cuentas inactivas.
- Controladores web/API de usuarios, routes/administrador y formularios/JS existentes: DNI, búsqueda y confirmación de acceso.
- CreateAppointmentService y OperationalRegistrationService: únicamente rechazan un nuevo responsable inactivo.
- Pruebas WorkerAccessTest, WorkerAccessMariaDbTest, WorkerConcurrencyTest, UserEditorSessionTest y worker-access.test.js; runtime estricto workers-11.8.9 en 33321.

## Evidencia ejecutada con datos ficticios

- PHP 8.3.35 / SQLite, suite de esta rama: **714 pruebas / 5.285 aserciones aprobadas**.
- MariaDB 11.8.9 dedicado: **21 pruebas / 204 aserciones aprobadas** (18 funcionales + tres carreras multiproceso reales). Incluye duplicado DNI, reintento de desactivación y desactivación mutua de administradores.
- JavaScript de esta rama: **211/211 aprobadas**, incluidas respuestas obsoletas de edición y errores de sesión/HTML.
- Navegador local 8025: login administrador ficticio; edición de DNI heredado sin cambiar contraseña; búsqueda por DNI; desactivación y reactivación con motivo y confirmación; rol ADMISION conservado. La creación por formulario y dispositivos móviles no se probaron manualmente; creación/validación sí tiene pruebas HTTP automatizadas.
- Sesión real file: cookie antigua rechazada tras reactivar; login nuevo aceptado. Tokens revocados no reviven. Prueba de migración reversible sin uso conserva identidad/hashes/roles/timestamps; rollback después de uso se bloquea.
- Primer intento de suite general: una expectativa antigua de DTO falló por DNI añadido. Se acotó DTO a campos usados por editor y se actualizó el contrato exacto; la suite final indicada arriba pasó. Logs locales ignorados en storage/app/qa-workers.
- Integración acumulada con PR #12: PENDIENTE al registrar este checkpoint.

## Publicación supervisada, sin ejecución autorizada aún

1. Respaldo verificable de código, .env/APP_KEY, BD y storage privado; restaurar en staging aislado antes de publicar. Confirmar autodespliegue y mantener mantenimiento durante migración/cambio de código. No restaurar producción para hacer la prueba.
2. Solo lectura: comprobar PHP8.3/Maria11.8.9, migrations y INFORMATION_SCHEMA.COLUMNS de users para dni/is_active/auth_version; INFORMATION_SCHEMA.TABLES para user_account_events. Si existen previamente, detenerse: la migración rechaza sobrescribirlas. Verificar normalización controlada de guard ADMINISTRADOR=web y al menos un administrador válido; no asignar roles automáticamente.
3. En staging equivalente, aplicar la migración aditiva y ejecutar el flujo de usuarios y Agenda/pagos. Preservar el estado de workers y operaciones existentes. Validar sesión file/database según driver real; no se verificó Redis real.
4. Solo con autorización final: mantenimiento, backup actualizado, publicar SHA aprobado, ejecutar migraciones pendientes previamente revisadas, limpiar cachés por procedimiento de PR #5, smoke y retirar mantenimiento. No ejecutar seeders ni migrate:fresh. La migración y el código de autenticación deben publicarse coordinadamente: el código requiere las columnas nuevas.
5. Smoke: legado activo sigue entrando; nuevo DNI válido/duplicado; desactivar trabajador de prueba bloquea sesión y token; reactivar exige login; cita/pago histórico y permisos sin cambio; Agenda rechaza responsable inactivo. No usar pacientes reales para pruebas.
6. Reversión: antes del uso y sin eventos/DNI/revocaciones, down solo puede ejecutarse tras autorización y verificación. Después del uso, **conservar columnas, auditoría y guard de acceso**: restaurar código anterior sin estos controles reactivaría de hecho cuentas desactivadas. Mantener mantenimiento y corregir hacia delante o preparar release anterior con el guard conservado. No restaurar una BD antigua encima de pagos/citas posteriores.

## Riesgos y pendientes

- La privacidad de Microsoft Clarity sigue BLOQUEANTE antes de datos reales, incluidos DNI de trabajadores. Instalación preservada sin modificación; enmascaramiento no acredita admisibilidad.
- Backups/restauración, autorización de publicación y migración coordinada, guards de roles productivos y prueba de staging siguen pendientes.
- No se ensayó Redis real; el control de época común se probó con file, array y database. Tokens y sesiones se bloquean en nuevas solicitudes, no se aborta una petición ya en curso.
- Esta entrega no implementa R19, no elimina trabajadores y no concede permisos extraordinarios.
