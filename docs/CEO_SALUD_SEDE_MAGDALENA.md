# R11 — alta controlada de Magdalena del Mar
14/10/2026. Rama codex/mvp-sede-magdalena, sobre PR #5 fc4f278. Catálogo productivo comunicado: sites sin filas; no se consultó ni modificó producción.

El comando agenda:magdalena-site es SOLO LECTURA por defecto. Resuelve por nombre canónico/código y usa el ID real. Si ya existe Magdalena activa, la reutiliza conservando código, ID y timestamps. No asigna sede a citas/horarios existentes ni cambia pacientes, pagos o permisos.

Bloquea nombre ambiguo, código ocupado por otra sede, Magdalena inactiva, otra sede operativa o referencias huérfanas. Así un alta no puede atribuir accidentalmente históricos a un ID recuperado. Apply comprueba InnoDB, UNIQUE(codigo) y las dos FK de sites esperadas; no altera esquema. Serializa este comando mediante el rol ADMINISTRADOR web y verifica de nuevo la huella dentro de transacción.

## Ejecución futura supervisada
1. Confirmar backup actualizado de BD/archivos/adjuntos y APP_KEY, con restauración aislada probada. Verificar primero esquema/relaciones y normalización aprobada de guards del PR #5; el responsable de apply debe ser ADMINISTRADOR web. No modifica el catálogo de permisos.
2. Aprobar un código estable (propuesto MAGDALENA; no es un site_id). Ejecutar:
   php artisan agenda:magdalena-site --code=MAGDALENA
3. Revisar action, target_id, catálogo, conteos, money_counts y blockers. Si hay incompatibilidades o datos cambiaron, detenerse. La vista previa no crea archivos ni filas.
4. Solo tras autorización explícita, con hash/ID/referencia reales:
   php artisan agenda:magdalena-site --code=MAGDALENA --apply --confirm=PLAN_HASH --actor-id=ADMIN_ID --reference=APROBACION_ID
5. Conservar recibo UUID firmado en storage/app/private/magdalena-site-receipts, fuera del público y en respaldo. Comprobar una sede real activa, mismo ID en una nueva vista previa REUSE y relaciones/históricos intactos. Repetir apply con nueva vista previa reutiliza la fila; la huella anterior caducada se rechaza.
6. Nicole resolverá el ID desde catálogo y conservará la lectura de registros heredados sin sede; este comando no los rellena ni construye una vinculación trabajador-sede.

No se ejecutaron los pasos de escritura en Hostinger. Este PR no agrega migraciones ni seeders y no habilita autodespliegue.

## Reversión limitada
Solo una sede creada por este recibo, sin cambios, sin relaciones ni cambios en los conteos protegidos puede eliminarse. Nunca una sede preexistente/reutilizada.
php artisan agenda:magdalena-site --rollback=RECIBO_UUID --confirm=AFTER_STATE_HASH --actor-id=ADMIN_ID --reference=APROBACION_REVERSA

Usa after.state_hash del recibo. El comando valida HMAC y entorno. Un reintento de rollback terminado es inocuo. Si entraron citas, horarios o pagos/conteos distintos, conserva la sede y exige revisión; no fuerza borrados, reasignaciones, migrate:rollback ni restauraciones sobre dinero posterior. La reversión productiva también requiere autorización.

## QA ejecutada
- PHP 8.3.35 / SQLite aislado: 16 pruebas, 68 aserciones, aprobadas.
- PHP 8.3.35 / MariaDB 11.8.9 aislada puerto 33320: 16 pruebas, 68 aserciones, aprobadas.
- Cobertura: preview CLI real, alta/reuso, guard vacío/no administrador, ambigüedad/inactividad/código ajeno, huellas caducadas, reversión/reintento, recibo manipulado, históricos NULL intactos, referencia huérfana y dos pagos ficticios posteriores que impiden revertir.
- No se ejecutó una carrera multiproceso de este comando; la operación de catálogo se prepara para una ventana supervisada sin escrituras concurrentes. No hay interfaz JavaScript en este diff.
- Se corrigió snapshot Eloquent para comparar atributos frescos en rollback; el problema de captura de consola del fixture se resolvió sin modificar el comportamiento productivo.

Pendientes: aprobación del código, esquema/guards efectivos productivos, respaldo y autorización de apply. El panel Nicole se entrega aparte sobre las dependencias revisadas. Clarity permanece intacto.
