# Instalación controlada y reversión — NO EJECUTADAS

La entrega es una candidata local. Push, merge, PR, despliegue, migraciones y acceso productivo requieren autorización posterior. No se han realizado.

## Preflight antes de autorizar

1. Registrar SHA/fecha del código realmente instalado. No asumir que `main`, el panel o la release visual describen todos los archivos retenidos. Obtener inventario de archivos locales, diferencias, cachés y symlinks sin incluir secretos en el informe. Verificar el estado de `SCHEDULING_MVP_ENABLED` y la versión real PHP/Laravel/Spatie/MariaDB, sin cambiarlos en esta revisión.
2. Comparar código normalizando CRLF con el manifiesto adecuado: `DEPENDENCIAS_PRODUCTIVO_ANTERIOR.json` para `1f840e9`, o `DEPENDENCIAS_VISUAL.json` para `6719af5`. El verificador heredado es solo lectura:

   ```text
   php tools/release/reschedule-preflight.php /ruta/erp /ruta/manifest.json
   ```

   Una divergencia requiere comparación y resolución explícita; no sobrescribir archivos conservados a ciegas. Estos manifiestos cubren el conjunto crítico declarado, no detectan todos los archivos extra del servidor: complementar con inventario. Se incluyen entradas nulas para clases/migración nuevas cuya presencia previa sería inesperada.
3. Verificar migraciones ya aplicadas e índices y columnas existentes para `appointments`, `appointment_events`, `appointment_operations` (único actor/UUID), `payments` (identidad bancaria única), `vouchers`, `voucher_items`, `cashier_shifts`, créditos, devoluciones y documentos. Si falta el esquema de la base previa, detener la activación y diagnosticar: esta entrega no lo instala ni lo presupone igual por estar en GitHub.
4. Inventariar si ya existen los dos nombres nuevos de permisos y sus asignaciones. Si tienen concesiones previas, detener para revisión de esas autorizaciones; `findOrCreate` las preserva, no las revoca ni concede otras. No usar seeders generales ni sincronización masiva de roles.
5. Preparar respaldo consistente de código instalado, DB, pivotes de permisos, `migrations`, archivos privados y configuración; conservarlos cifrados/restringidos fuera del webroot. Verificar restauración en un entorno aislado. No incluirlos en el ZIP de revisión. Coordinar una ventana corta sin ediciones concurrentes para cambiar código de forma coherente.

## Instalación propuesta, solo tras autorización

1. Probar el artefacto en staging aislado con el mismo esquema/versiones y datos sintéticos. Revisar los dos diffs: incremental respecto a visual y completo respecto a productivo anterior. Elegir el conjunto correspondiente a la base comprobada; no mezclar versiones de servicios, rutas y JS.
2. Instalar código mediante el procedimiento habitual aprobado. No copiar `tests`, `tools/qa`, evidencias ni documentos al webroot como aplicación. No cambiar `.env` ni dependencias por esta entrega. Mantener configuración y storage privados. Revisar cualquier drift conservado antes de sustituir archivos.
3. Ejecutar únicamente la migración nueva, en la raíz del ERP, con el PHP validado:

   ```text
   php artisan migrate --path=database/migrations/2026_10_21_200000_define_direct_appointment_economic_permissions.php --force
   ```

   Es una migración de datos; define dos permisos `web`, sin tablas/columnas ni concesiones. No ejecutar `migrate:fresh`, `db:wipe`, seeders de prueba o todas las migraciones pendientes. Guardar salida y estado de migraciones. Si hay pendientes inesperadas, detener.
4. Limpiar/reconstruir cachés de rutas y vistas conforme al despliegue actual; renovar OPcache y caché del JS en cliente cuando corresponda. La migración invalida caché Spatie. No regenerar comprobantes. Comprobar fingerprints con `DEPENDENCIAS_CANDIDATO.json`.
5. Con los permisos aún sin conceder, comprobar carga de Agenda, marca histórica, Admisión, acceso denegado a economía y endpoints. No crear citas ni pagos ficticios en producción. La aceptación funcional de importes se hace en staging.
6. El administrador asigna explícitamente en Roles los permisos elegidos al rol Comercial o a un grupo piloto reducido. Conservar los permisos existentes al editar. El usuario necesita además acceso MVP/Agenda y visibilidad. Habilitar capacidad de pagos solo a quienes deban rectificar dinero; capacidad de precios por separado.
7. Monitorizar errores, conflictos409/422, operaciones/auditoría y conciliación con Caja. Un bloqueo fiscal/cerrado/legado no se sortea editando DB ni habilitando campos desde JS. No activar capacidades adicionales como consecuencia de esta entrega.

## Rollback seguro

Primero revocar las dos capacidades de edición desde Roles, revisar también permisos directos del usuario y limpiar caché Spatie. Esto deshabilita nuevas correcciones y conserva las auditorías. Si corresponde, cerrar sesiones conforme al procedimiento existente. No eliminar eventos ni operaciones.

Restituir el conjunto de código previamente respaldado (`6719af5` si estaba instalada la visual, o `1f840e9` si todavía no lo estaba), con sus rutas/JS/cachés coherentes. Las correcciones ya comprometidas son datos financieros válidos: se mantienen los pagos rectificados, precios y tickets consistentes. Volver al código anterior NO significa revertir importes. Una rectificación inversa requiere otra operación autorizada y auditada y volver a comprobar que el turno sigue abierto y no existe documento fiscal.

El `down` de la migración es deliberadamente no destructivo: conserva definiciones/concesiones para no romper trazabilidad ni autorizaciones decididas posteriormente. No usar un rollback general de migraciones, eliminar permisos ni restaurar toda la DB para deshacer solo código; podría borrar operaciones reales posteriores. La desactivación efectiva se hace revocando las capacidades, no con `down`. El historial general anterior tolera el nuevo tipo de evento sin exponer sus instantáneas; los nuevos campos no son necesarios porque no se añaden columnas.

Riesgos que requieren revisión: recargos históricos sin base desglosada, reglas comerciales de catálogo, conciliación de operaciones antiguas, archivos retenidos en Hostinger, versiones de runtime y carga concurrente real. Las pruebas locales no sustituyen el preflight productivo. No se garantiza la totalidad de escenarios fiscales/legados: permanecen bloqueados de forma explícita.
