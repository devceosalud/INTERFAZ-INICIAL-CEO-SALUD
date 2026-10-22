# Instalación propuesta y rollback — NO EJECUTADOS

## Conclusión operativa

La producción verificada por el responsable está en d245918793014621066c8f35822dfca51b9ec296, detached HEAD limpio; /home/u676842872/domains/srv-prd01.ceosalud.com/public_html es una carpeta física y contiene el repositorio/Laravel. /usr/bin/php es PHP 8.3.35, Laravel 9.52.21. No se accedió al servidor durante este trabajo.

Hostinger documenta soporte de symlinks según plan/configuración, incluso restricciones por tipo de alojamiento: https://www.hostinger.com/support/1583694-how-to-create-a-symlink-at-hostinger/ y https://www.hostinger.com/support/which-file-transfer-and-server-access-options-are-supported-at-hostinger/ . Eso NO acredita un mecanismo atómico activado en esta cuenta. Convertir ahora public_html en symlink o cambiar el document root requeriría validar configuración del servidor, almacenamiento compartido, OPcache, sesiones y peticiones activas. Queda fuera de esta corrección urgente y no se propone ejecutarlo a ciegas.

Recomendación para esta entrega: preparar y verificar el commit fuera del tráfico; intervención breve programada, sin atenciones/escrituras activas, mantenimiento prerenderizado, actualización y validación, reapertura. No se promete cero interrupciones ni una duración exacta. Laravel 9 documenta la respuesta prerenderizada: https://laravel.com/framework/docs/9.x/configuration#pre-rendering-the-maintenance-mode-view . Mantener desactivado el despliegue automático.

## Condiciones antes de autorizar instalación

1. Publicación y revisión de la rama candidata autorizadas; SHA final aprobado y disponible en el remoto. No instalar desde main ni desde FETCH_HEAD sin verificar.
2. CI de ese SHA aprobada; revisar diff tanto contra PR64 (3e1ca22) como contra producción (d245918).
3. No hay nuevas migraciones, dependencias, cambios .env ni workflows respecto a producción. Las migraciones clínicas ya aplicadas deben comprobarse con migrate:status, NO ejecutar migrate ni rollback.
4. Respaldo privado verificado de base de datos, .env, archivos privados/uploads, configuración web y código actual. No copiar respaldos a public_html ni adjuntarlos al PR. Usar el procedimiento existente del responsable/Hostinger y registrar el identificador del backup.
5. Verificar esquema y doctor_id nullable/índice/relaciones ya instalados, sin consultar datos personales en la entrega. Verificar cuenta clínica autorizada y permisos reales administrativamente; no crear ni asociar cuentas automáticamente.
6. Coordinar cierre de operaciones web y trabajadores/cron existentes antes de sustituir archivos. Confirmar que no quedan POST, cobros, cargas, llamadas o procesos en curso. Artisan down NO cancela una petición ya iniciada. No basta un sleep fijo. Si no se puede comprobar el drenaje, detener instalación y coordinar ventana con soporte.
7. Verificar PHP web además de CLI. Determinar política OPcache del SAPI web: si no revalida timestamps, acordar reinicio/purga del pool desde hPanel antes de reabrir. opcache_reset desde CLI no garantiza invalidar el pool web.
8. Mantener fuera del tráfico a clientes con pestañas antiguas; exigir recarga al reabrir. Los assets usan AgendaAssets (versionado existente).

## Preflight SSH — solo tras autorización

Ejecutar en Bash, en una única sesión. Sustituir SHA_APROBADO por el SHA completo de la entrega revisada y BACKUP_VERIFICADO por la referencia real del respaldo aprobado. Estos marcadores son deliberados: no existe todavía autorización para instalar un SHA local.

```bash
set -euo pipefail
APP='/home/u676842872/domains/srv-prd01.ceosalud.com/public_html'
PHP='/usr/bin/php'
PREVIOUS='d245918793014621066c8f35822dfca51b9ec296'
CANDIDATE='SHA_APROBADO'
BACKUP_REFERENCE='BACKUP_VERIFICADO'
[[ "$CANDIDATE" =~ ^[0-9a-f]{40}$ ]]
[ "$BACKUP_REFERENCE" != 'BACKUP_VERIFICADO' ]
[ -d "$APP" ] && [ ! -L "$APP" ]
cd "$APP"
[ "$(pwd -P)" = "$APP" ]
[ "$(git rev-parse HEAD)" = "$PREVIOUS" ]
[ -z "$(git status --porcelain)" ]
"$PHP" -v
"$PHP" artisan --version
"$PHP" artisan migrate:status
# Solo descargar objetos: nunca pull, merge ni cambiar main.
git fetch --no-tags origin codex/admision-flujo-integrado-20261022
[ "$(git rev-parse FETCH_HEAD)" = "$CANDIDATE" ]
git merge-base --is-ancestor "$PREVIOUS" "$CANDIDATE"
git diff --check "$PREVIOUS" "$CANDIDATE"
git diff --stat "$PREVIOUS" "$CANDIDATE"
[ -z "$(git diff --name-only "$PREVIOUS" "$CANDIDATE" -- database/migrations composer.json composer.lock package.json package-lock.json .github/workflows)" ]
# Comparar configuración web y archivos no versionados conservados con el respaldo.
# No imprimir .env, credenciales, archivos de pacientes ni contenidos privados.
```

La comprobación de cambios versionados no cubre archivos ignorados conservados por Hostinger. El responsable debe identificar personalizaciones de arranque/rutas/servidor, verificar contra la versión preparada y detenerse si afectan compatibilidad.

## Intervención breve — segunda autorización y drenaje confirmado

Antes del bloque: respaldos verificados; cron/trabajadores que escriben pausados por su mecanismo existente; operaciones en curso terminadas; ventanas del ERP cerradas. No inventar comandos de Supervisor/servicio que no están confirmados.

```bash
cd "$APP"
[ "$(git rev-parse HEAD)" = "$PREVIOUS" ]
[ -z "$(git status --porcelain)" ]
"$PHP" artisan down --render='errors::503' --retry=60
# Confirmar drenaje efectivo con el responsable antes de continuar.
# Si falla cualquier comando: NO reabrir automáticamente; ejecutar rollback.
git switch --detach "$CANDIDATE"
"$PHP" artisan route:clear
"$PHP" artisan view:clear
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan route:list --path=scheduling-mvp/admission
[ "$(git rev-parse HEAD)" = "$CANDIDATE" ]
[ -z "$(git status --porcelain)" ]
# Comprobar OPcache web según preflight y completar smoke autorizado.
# No ejecutar composer install, npm, migrate, cache:clear global ni permission resets.
"$PHP" artisan up
```

No modifica .env ni configuración PHP; conserva config cache existente porque no cambia config/. Si existen particularidades de caché/rutas distintas del código, probarlas primero en la versión preparada. La reconstrucción de route/view cache debe quedar validada antes de reabrir. No se ejecutó este bloque en producción.

## Rollback de código (sin borrar auditoría)

Si falla instalación o smoke: mantener mantenimiento, detener nuevas escrituras y usar mismo entorno/control de drenaje. No restaurar automáticamente una BD anterior sobre operaciones nuevas.

```bash
cd "$APP"
[ "$(pwd -P)" = "$APP" ]
"$PHP" artisan down --render='errors::503' --retry=60
git switch --detach "$PREVIOUS"
"$PHP" artisan route:clear
"$PHP" artisan view:clear
"$PHP" artisan route:cache
"$PHP" artisan view:cache
[ "$(git rev-parse HEAD)" = "$PREVIOUS" ]
# Comprobar OPcache web, rutas y lectura del ERP antes de reabrir.
"$PHP" artisan up
```

Sin migration:rollback, sin borrar eventos, asociaciones, procedimientos o pagos. Los eventos/transiciones nuevos usan contratos y tablas ya existentes en d245918; se conserva la cita única. Volver al código anterior retira la interfaz integrada y las ampliaciones operativas, no revierte las actuaciones ya registradas. El código anterior ya comprende sus estados/eventos. Preservar respaldos y registros; restauración de BD solo mediante procedimiento específico aprobado.

## Validación posterior

- Admisión carga con menú administrativo conservado; Mi agenda médica no figura; enlace antiguo redirige con autenticación.
- Comercial ve confirmadas pero no reservas privadas ajenas y registra llegada sin edición general.
- Médico vinculado, incluido ADMINISTRADOR + MEDICO validado, ve acciones propias; otro médico recibe 403.
- Reevaluación pendiente bloquea fin y conserva color principal; 2/3 continúa pendiente; 3/3 cambia a listo y naranja conforme prioridad.
- Retorno manual sin procedimientos y autorización parcial conservan sus auditorías; no falsifican realización.
- Finalización no registra salida; Comercial/Admisión registran salida una sola vez.
- Reprogramaciones, reservas, slots, pagos, saldo y privilegios administrativos permanecen consistentes.
- Confirmación explícita de exoneración autorizada revalida disponibilidad, no crea pago y hace visible la cita confirmada.
- Usar una cita de prueba autorizada o validar solo lectura; nunca inventar pacientes/cobros productivos para el smoke.
