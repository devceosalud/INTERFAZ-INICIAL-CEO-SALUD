# Nicol — observaciones y pendientes por cita (#32)

## Qué agrega este PR (sin despliegue)
- Una lista de tareas operativas en el detalle lateral «Ver» de cada cita.
- ADMINISTRADOR (o permiso optativo appointment.admission_tasks.manage) puede crear tareas. Son append-only: el texto original no se modifica ni elimina.
- ADMISION y COMERCIAL (o permiso optativo appointment.admission_tasks.complete) pueden consultar y marcar Realizado / Pendiente; no editar ni crear, salvo que tengan además la autorización de administración.
- Cada usuario sigue necesitando appointment.mvp.access y appointment.view, cuenta activa y visibilidad existente de la cita. NO hay bypass de reservas privadas para ADMIN.
- Tareas por appointment_id, no notas globales del paciente. Solo aparecen dentro del detalle autorizado, no en feed, listado, Agenda o Historia Clínica.
- La UI usa texto DOM seguro, enmascarado orientativo (clarity-mask), y no muestra nombres de autores al personal no administrador.
- No registra ni acredita órdenes médicas, radiografías, resultados, pagos, horarios o cambios clínicos. Es un checklist operativo de seguimiento.

## Seguridad y auditoría
- Migración aditiva: database/migrations/2026_10_16_010000_create_appointment_admission_tasks.php.
- appointment_admission_tasks conserva autor, texto, marca y responsable de realización. La creación utiliza UUID por autor más hash de contenido para soportar reintentos sin duplicar.
- appointment_admission_task_events registra eventos CREATED / COMPLETED / REOPENED con actor y fecha. Cambios de estado usan expected_completed y bloqueo transaccional para detectar concurrencia.
- Autorización comprobada en backend y usuarios con cita no visible reciben 404; sin permiso reciben 403. Campos extra del request devuelven 422.
- No se añade texto a observaciones existentes, historial genérico ni telemetría.

## Riesgo Clarity antes de publicar
El documento docs/CEO_SALUD_CLARITY_REVISION_PENDIENTE.md constata una revisión pendiente de privacidad y términos de Microsoft Clarity en la instalación común. La clase clarity-mask es defensa adicional, NO autoriza por sí sola el tratamiento de contenido médico ni certifica cumplimiento. Mantener este PR SIN DESPLEGAR hasta que el responsable autorice expresamente el tratamiento de información potencialmente sensible y el alcance de capturas/telemetría.

## Pruebas
- Tests PHP aislados AdmissionTaskTest: creador admin, lector/ejecutor Comercial y Admisión, otros perfiles sin acceso, visibilidad privada, reintentos, concurrencia, integridad financiera y auditabilidad.
- Tests JavaScript: estructura UI, sintaxis y regresión de tablero Nicol.
- Falta smoke de navegador y prueba con perfiles autorizados en staging, con datos ficticios.

## Procedimiento futuro
1. Aprobar el PR después de revisar seguridad, usuario real, funcionamiento en staging y privacidad Clarity.
2. Confirmar respaldo, autodespliegue desactivado y SHA de GitHub/Hostinger.
3. Hacer mantenimiento controlado. Redistribuir al SHA final autorizado.
4. PREVISUALIZAR la migración específica con php artisan migrate --path=database/migrations/2026_10_16_010000_create_appointment_admission_tasks.php --pretend --force --no-ansi.
5. Solo tras confirmar las dos CREATE TABLE y permisos, aplicar la migración específica con --force en ventana autorizada.
6. Validar lectura y marcado con cuentas de prueba; no usar información clínica real para QA.
7. Rollback: conservar las tablas y auditoría; revertir únicamente código compatible. La migración down bloquea eliminación cuando existen tareas.
