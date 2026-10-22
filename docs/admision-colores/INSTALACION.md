# Actualización visual y rollback específico — propuesta, NO ejecutada

## Fuente correcta antes de cualquier cambio

GitHub main continúa en 1f840e90912d2ce8c55ce7502a22a1d582492bc3. El PR #65 se fusionó en fix/comercial-registro-llegada-20261022, merge f29019a1f3649149a31af3447e67e009970a7277. El panel Hostinger configurado con main no obtiene por ello el flujo integrado. No utilizar Redistribuir suponiendo que cambia de fuente. No hacer merge a main ni cambiar hPanel automáticamente.

La corrección tiene UN archivo de producto: public/css/scheduling/admission-board.css. JavaScript, PHP de aplicación, Blade, rutas, permisos, filtros y transiciones son idénticos al merge. Sin migraciones, .env ni dependencias. El CSS ya usa la misma variable que establece el JavaScript. AgendaAssets versiona por hash de contenido: esta edición del bloque renueva la URL del CSS al servir la vista correcta.

El responsable debe comprobar primero HEAD y hashes de los archivos efectivos; no se accedió a producción desde este trabajo. No asumir que main de hPanel es el HEAD instalado. Tampoco reemplazar solo CSS sobre 1f840e9 para pretender instalar el flujo integrado.

## Preflight SSH — únicamente tras autorización

```bash
set -euo pipefail
APP='/home/u676842872/domains/srv-prd01.ceosalud.com/public_html'
PHP='/usr/bin/php'
BASE='f29019a1f3649149a31af3447e67e009970a7277'
FIX='SHA_REVISADO_DE_ESTA_CORRECCION'
cd "$APP"
[ "$(pwd -P)" = "$APP" ]
git rev-parse HEAD
git status --short
sha256sum public/css/scheduling/admission-board.css public/js/scheduling/admission-board.js
# STOP si HEAD no es la base integrada esperada o hay cambios locales.
[ "$(git rev-parse HEAD)" = "$BASE" ]
[ -z "$(git status --porcelain)" ]
[[ "$FIX" =~ ^[0-9a-f]{40}$ ]]
# El fetch no instala ni cambia main.
git fetch --no-tags origin codex/fix-admision-colores-20261022
[ "$(git rev-parse FETCH_HEAD)" = "$FIX" ]
git merge-base --is-ancestor "$BASE" "$FIX"
git diff --name-status "$BASE" "$FIX"
[ "$(git diff --name-only "$BASE" "$FIX" -- app config routes resources composer.json composer.lock package.json package-lock.json database .github public/js)" = '' ]
[ "$(git diff --name-only "$BASE" "$FIX" -- public)" = 'public/css/scheduling/admission-board.css' ]
```

Si falla el control del SHA base: detenerse y documentar la fuente instalada. Instalar primero el flujo integrado completo requiere su propia autorización y el procedimiento revisado del PR #65; esta entrega no autoriza esa operación ni la selección de otra rama en Hostinger.

## Aplicación controlada de código (no ejecutada)

public_html es carpeta física, no releases atómicos. Aunque solo cambia un CSS de producto, no se promete que un git switch sea un despliegue atómico. Preparar antes copia privada del CSS y referencia del commit actual fuera de public_html. Coordinar una pausa breve y drenaje de peticiones; no alterar .env ni registros. El procedimiento no necesita migraciones, composer, npm ni limpiar permisos/caché global.

```bash
# Requiere preflight anterior, respaldo privado verificado y autorización específica.
BACKUP_DIR="$HOME/ceosalud-backups/colores-$(date +%Y%m%d-%H%M%S)"
umask 077
mkdir -p "$BACKUP_DIR"
git rev-parse HEAD > "$BACKUP_DIR/previous-sha.txt"
cp -p public/css/scheduling/admission-board.css "$BACKUP_DIR/admission-board.css"
"$PHP" artisan down --render='errors::503' --retry=60
# Confirmar que no quedan peticiones/escrituras activas antes de continuar.
git switch --detach "$FIX"
[ "$(git rev-parse HEAD)" = "$FIX" ]
sha256sum public/css/scheduling/admission-board.css
[ -z "$(git status --porcelain)" ]
"$PHP" artisan up
```

No regenerar cachés si no hay evidencias de que sea necesario: no cambia ninguna vista/ruta/configuración. Si hay caché HTTP/CDN o CSS conservado, invalidar únicamente la URL del asset y la respuesta HTML afectada mediante el mecanismo que el responsable tenga aprobado. Recargar completamente los navegadores. Comprobar en Network que la URL CSS con ?v= corresponde a los primeros 20 caracteres del SHA-256 del archivo instalado y que su cuerpo contiene las tres reglas. No exponer cookies, tokens ni HTML de pacientes en la revisión.

## Rollback exclusivo de la corrección, conservando el flujo integrado

```bash
cd "$APP"
[ "$(pwd -P)" = "$APP" ]
[ "$(git rev-parse HEAD)" = "$FIX" ]
[ -z "$(git status --porcelain)" ]
"$PHP" artisan down --render='errors::503' --retry=60
git switch --detach "$BASE"
[ "$(git rev-parse HEAD)" = "$BASE" ]
"$PHP" artisan up
```

BASE es f29019a, NO 1f840e9 ni d245918: así no se revierte el flujo integrado #65. No restaurar BD ni borrar auditoría/eventos. Validar source/URL/huella de CSS después del rollback. Recordar que la base integrada ya contiene las reglas: la modificación CSS de esta entrega las deja explícitas y cambia la huella, pero no cambia su mapeo.

## Validación posterior

- Amarillo de pago real cubre las cinco celdas, incluida fila seleccionada/hover.
- Llegada/espera/atención turquesa; pendiente no cambia el color; listo naranja; finalizada turquesa; cancelada roja; blanco y programada conservan sus reglas históricas.
- Exoneración conserva etiqueta explícita y no aparece falsamente como pago acreditado.
- Misma presentación operativa para roles autorizados, respetando visibilidad y datos económicos restringidos.
- Llegada, atención, procedimientos, retorno, salida y filtros integrados siguen presentes; no reaparece Mi agenda médica.
