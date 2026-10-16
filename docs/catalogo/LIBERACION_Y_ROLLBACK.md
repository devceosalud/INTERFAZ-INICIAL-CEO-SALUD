# Liberación controlada — catálogo, precios y especialidades

Este documento prepara la publicación; no ejecuta ni autoriza merge, despliegue o cambios productivos. Base: `c86ffa6e9ed04bb80272db400a63b48604a9a7ef`. La autorización final y las operaciones corresponden al responsable de CEO Salud.

## Condiciones previas

1. Aprobar el PR y registrar el SHA final autorizado de `main` y el árbol que contiene. Mantener autodespliegue desactivado y comprobar el procedimiento manual de Hostinger.
2. Comparar por SSH `git rev-parse HEAD` y `git status --short --untracked-files=no` con la versión esperada. Conservar cualquier diferencia antes de publicar. No copiar `.env` ni datos de pacientes al PR.
3. Revisar el plan concreto de datos de `STAFF_CORRESPONDENCIA_2026-10-16.md`: profesionales/IDs, servicios sin precio, asignaciones, clasificación, MFR/TF, servicios compartidos, doctor 9/servicio 1 y referencias previas de 8/10. No ejecutar volcados exportados como si fueran migraciones.
4. Resolver la privacidad/admisibilidad de Clarity con el responsable. La instalación se conserva sin modificaciones; las pruebas locales bloquearon la salida de datos mediante el router QA, lo que no acredita la privacidad productiva.
5. Confirmar PHP 8.3.35, MariaDB 11.8.9, requisitos Composer, InnoDB, almacenamiento privado y permisos de escritura de Laravel. El cambio no incorpora dependencias nuevas, Node, Tesseract nativo ni servicios OCR externos. Conservar los assets locales WASM/worker existentes.
6. Verificar administradores autorizados, sesiones y roles/guards efectivos. No normalizar ni conceder capacidades automáticamente. Si hay inconsistencia, usar el mecanismo ya existente de vista previa y aprobación en una intervención separada.

## Respaldo y restauración

- Acordar ventana de mantenimiento y detener entradas web, cron y workers que puedan escribir durante la copia y la migración.
- Exportar la BD completa con consistencia transaccional y objetos necesarios. Respaldar también código/SHA, configuración protegida, archivos privados y su relación con la BD, permisos de almacenamiento y configuración de tareas. Guardar las copias fuera de `public_html`, con acceso restringido y checksum.
- Ensayar la restauración del respaldo autorizado en un destino aislado, sin integraciones externas y con protección de datos. Comparar conteos, claves foráneas, pagos, comprobantes, auditoría y hashes de archivos privados antes de considerar el respaldo utilizable.
- QA de esta rama ensayó dump/restauración de **48 tablas y un archivo privado totalmente ficticios** en otra BD local MariaDB 11.8.9; todos sus contenidos coincidieron mediante SHA-256. Esto no sustituye restaurar ni comprobar el respaldo productivo.

## Orden de publicación (solo después de autorización)

1. Activar mantenimiento y suspender escritores. Registrar hora, último pago/comprobante y conteos de control sin divulgar personas.
2. Completar respaldos y comprobar su integridad. Conservar la versión anterior del código y la configuración.
3. Publicar el código aprobado con el mecanismo manual de Hostinger. Mantener la aplicación cerrada mientras falte cualquier columna. Instalar desde `composer.lock` con `composer install --no-dev --prefer-dist --optimize-autoloader` si el proceso habitual lo requiere; no ejecutar `composer update`.
4. Ejecutar `php artisan migrate:status` y revisar que solo se apliquen las migraciones previstas. Tras autorización específica, `php artisan migrate --force` aplica en orden:

| Migración | Efecto | Cambio automático de datos de negocio |
|---|---|---|
| `2026_10_16_190000_extend_service_price_catalog` | Metadatos de servicio, versiones, referencia comercial y procedencia en citas, `catalog_price_events` | Ninguno; precios/enlaces nuevos quedan NULL |
| `2026_10_16_190100_allow_appointments_without_price_surcharge` | Permite NULL en `appointments.additional_rate_id`; conserva FK e IDs existentes | Ninguno |
| `2026_10_16_190200_authorize_service_specialties` | `service_specialties` y evidencia de excepciones individuales con FK | Ninguno; tabla vacía y excepciones NULL |

5. Actualizar las cachés con el procedimiento Laravel habitual de este ERP. Conservar secretos, sesiones, almacenamiento y Clarity. Los assets de Agenda y del editor se versionan por contenido; verificar que el navegador carga la nueva versión.
6. Ejecutar `php artisan catalog:services-audit --assignments` (solo lectura, después de migrar). Comparar referencias, duplicados y compatibilidad con el plan aprobado.
7. Configurar **desde el ERP**, con administradores autorizados, únicamente las decisiones aprobadas: tipos/políticas, precios institucionales faltantes, precios por profesional, especialidades compartidas, asignaciones individuales y enlaces 8→1/10→3 sin referencias incompatibles. Conservar motivo y auditoría. No hay seeder ni importación automática. No activar servicios sin precio ni encubrir la asignación equivocada de Keylinn con una excepción no aprobada.
8. Ejecutar smoke, revisar errores y reabrir solo los flujos con configuración válida. Registrar SHA, migraciones, actor y resultado. Reactivar trabajos previstos al terminar.

## Smoke verificable

- ADMIN abre Maestros, filtra un profesional y ve origen, importe y estado. Verificar persistencia de una configuración aprobada; no introducir precios de prueba en producción.
- ADMISION/COMERCIAL reciben 403 al editar precios mediante HTTP.
- Agenda muestra solo servicios autorizados compatibles; Keylinn no recibe Traumatología por la fila inconsistente. MFR solo estará operativo después de configurar sus servicios y precios aprobados.
- Cotizar sin recargo retorna el precio vigente del profesional/institución. Tarifa ausente, duplicada o inválida elegida explícitamente produce error controlado. No se modifica una cita histórica.
- Primera consulta y reevaluación se distinguen; una reevaluación sin indicación válida se rechaza. No crear indicaciones o pacientes ficticios en producción para este smoke.
- Verificar flujo financiero en QA; en producción solo registrar operaciones reales autorizadas por el responsable: un adelanto positivo real permite confirmación explícita, conserva saldo, comprobante y atribución. OCR no acredita dinero por sí mismo.
- Revisar autenticación, documentos privados, errores de Laravel y funcionamiento de assets en escritorio/móvil. No enviar información sensible a herramientas externas para diagnosticar.

## Reversión sin pérdida de dinero

**No usar `migrate:rollback`, `migrate:reset`, TRUNCATE ni restaurar una BD antigua sobre pagos nuevos.** Las migraciones protegen información nueva; la tercera rechaza siempre el borrado de autorizaciones/auditoría. La segunda rechaza NOT NULL si ya existen citas sin recargo. SQLite rechaza una reversión de claves no soportada antes de borrar metadatos.

1. Ante precios erróneos, acceso incompatible, duplicados, fallos financieros o migración incompleta: mantenimiento y suspensión de escritores; conservar logs, pagos, documentos y auditoría del incidente.
2. Preferir una corrección compatible hacia adelante. Si se revierte código, conservar todas las tablas/columnas y los archivos privados nuevos. No ejecutar down destructivos.
3. **La base `c86ffa6e` no entiende precios institucionales ni la nueva autorización de especialidad y vuelve a exigir una tarifa neutral que actualmente falta.** Restaurar ese código no autoriza reabrir agendamiento/cobros. Mantener esos flujos bloqueados hasta verificar compatibilidad o publicar la corrección aprobada.
4. Si ya se editaron catálogos, un administrador realiza correcciones compensatorias auditadas desde los maestros. No se reprician citas ni se reescriben pagos previos. No eliminar las asignaciones o evidencias usadas por históricos.
5. Una restauración de BD solo procede con plan de recuperación aprobado que preserve/reconcilie **todas** las transacciones posteriores al respaldo. No sobrescribirlas ni asumir que no hubo cobros. Verificar conteos e integridad antes de reabrir.

## Lo que sigue necesitando aprobación

Merge/publicación, las tres migraciones, cualquier activación/asignación o cambio de precios reales, la reconciliación de Keylinn, especialidades compartidas y enlaces comerciales. Se necesita además validar precios MFR/TF y demás prestaciones sin importe, revisar referencias de 8/10, resolver Clarity y ensayar el respaldo productivo. El candidato local funcional no equivale a una certificación del entorno productivo.
