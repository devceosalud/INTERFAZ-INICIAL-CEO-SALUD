# PR #5 — normalización delimitada de guards heredados
Hallazgo Hostinger confirmado: ADMINISTRADOR, ADMISION, CAJA, FACTURACION y RECEPCION tienen guard_name exactamente vacío; COMERCIAL tiene web. Los guards reales de permissions siguen PENDIENTES de la lectura adjunta. No inferirlos de roles.

## Efecto y solución
La versión instalada de Spatie consulta nombres por guard; findByName(..., web), assignRole y givePermissionTo pueden fallar con los guards vacíos. Sin embargo hasRole por nombre/pivots y ciertos permisos web ya vinculados pueden funcionar: no afirmar que todos los accesos previos estaban denegados. Bootstrap/editor de roles/usuarios filtran web y excluirían cinco perfiles. El bootstrap ahora muestra guard_issues y rechaza apply si hay guards vacíos o permisos vinculados incompatibles en los objetivos.

Nuevo comando **agenda:mvp-guards**, previa por defecto, separado de agenda:mvp-permissions. Enumera roles/permisos, guards, conteos de relaciones directas y de rol, incompatibilidades y cambios propuestos sin nombres/IDs de pacientes o usuarios. Solo cambia guard_name de las filas seleccionadas por ID conservando IDs, nombres, updated_at, usuarios y todos los pivots. No crea/fusiona roles/permisos ni asigna, elimina o recalcula dinero/históricos.

Rechaza duplicados/colisiones (igualdad según collation de la BD), cambios concurrentes de catálogo/pivots, guards api/otros/null/espacios, modelos asignados distintos a User e incompatibilidades de permisos compartidos. Configuración teams/wildcard/custom tables/pivots/auth incompatible bloquea la reparación delimitada. Nunca convertir indiscriminadamente guards vacíos en web con UPDATE masivo.

Los permisos ordinarios vacíos aunque no tengan relaciones deben seleccionarse explícitamente para evitar missing_definitions del bootstrap. Si un permiso vinculado también está vacío, el plan bloquea hasta seleccionar su ID explícito mediante --permission-id repetido. Si existe otro permiso homónimo web, NO se fusionan filas ni se reemplazan relaciones; revisar esa colisión como decisión independiente. Convertir un permiso vacío puede hacer efectivos grants directos/extraordinarios YA EXISTENTES: revisar los IDs/nombres y conteos de usuarios afectados antes de aprobar. Normalizar guards no concede por sí mismo las seis capacidades ordinarias.

En MariaDB exige las cinco tablas Spatie InnoDB y UNIQUE(name,guard_name) en roles/permissions; si faltan, bloquea sin alterar esquema. Transacción + locks de catálogo/pivots, SHA256 del plan y estado, referencia de autorización, recibo privado before/after/changes y logs de auditoría; invalida caché Spatie. Solo guarda guards originales y huellas de relaciones, sin publicar identidades. Reintentar no vuelve a cambiar guards; conservar primer recibo CON changes para reversión. Si recibo/cache/log/commit falla o hay interrupción, contrastar BD y recibo antes de inferir éxito: almacenamiento de archivos y commit de BD no son atómicos.

## Secuencia operativa — no ejecutada en producción
1. Ahora SOLO SELECT en docs/CEO_SALUD_MVP_GUARDS_SELECT.sql; devolver guards/nombres/IDs de catálogo y conteos sanitizados. No repetir versiones/suites/los dos UNIQUE financieros ya confirmados. Aprobar roles participantes y cualquier ID de permiso vacío requerido; no asumir que todos los permisos son web.
2. Cerrar condiciones del preflight general, incluida privacidad Clarity. Con autorización FINAL, corte de escritores/editores/colas y backup completo de BD/archivos/storage, incluidos cinco conjuntos Spatie (roles, permissions y tres pivots), cachés/configuración bajo custodia y recibos privados. Restauración ensayada en destino nuevo aislado. No basta un respaldo de roles sin relaciones. Si merge autodespliega, backup/corte/aprobación preceden al merge.
3. Publicar release PR #5 autorizada en mantenimiento; los comandos nuevos no existen en el main productivo anterior. Verificar destino/SHA y usuario CLI, configuración web y acceso privado/escritura del storage de recibos. Conservar Clarity, APP_KEY, .env y adjuntos. Sin migrations/seeders.
4. Generar/revisar previa exacta. Estas seis opciones son la propuesta para los seis perfiles confirmados, su ejecución productiva todavía requiere aprobación. Si el catálogo muestra permisos vacíos necesarios, añadir SOLO los --permission-id aprobados y regenerar el plan.

~~~bash
php artisan agenda:mvp-guards --role=ADMINISTRADOR --role=ADMISION --role=CAJA --role=COMERCIAL --role=FACTURACION --role=RECEPCION
# Aplicar mismas opciones, y mismos --permission-id si los hubo:
php artisan agenda:mvp-guards --role=ADMINISTRADOR --role=ADMISION --role=CAJA --role=COMERCIAL --role=FACTURACION --role=RECEPCION --apply --confirm=PLAN_HASH_GUARDS --reference=PR5_GUARDS_AUTORIZADOS
~~~

Exigir blockers=[] y exactamente cambios revisados; con permissions web, cambia cinco guards y COMERCIAL no cambia. Nunca aplicar previa sin objetivos explícitos ni sustituir marcadores por huellas antiguas.

5. Guardar recibo UUID/after.state_hash en storage/app/private/mvp-guard-receipts; comprobar salida 0 y SELECT posteriores: mismos IDs/nombres/cantidad/relaciones; solo guards aprobados pasan a web, sin incompatibilidades nuevas. Si un resultado difiere, mantener corte, no forzar.
6. Entonces generar nueva previa de agenda:mvp-permissions con la MISMA lista de roles participantes aprobada y confirmar su propia plan_hash. Exigir guard_issues=[] y missing_definitions=[]; aplicar con referencia separada. Conserva grants previos y añade solo access/view/create/update/reschedule/payment.submit. No reutilizar hash de guards para grants. Recargar sesiones/formularios; otros modelos persistentes deben descartar relaciones cargadas viejas. Verificar caché del servicio web.
7. Humo ficticio en staging equivalente: seis perfiles × entradas operativa/heredada, paciente/reserva/pago/confirmación/consulta/edición/reprogramación y Día/Semana/Mes; 49 % no confirma, 50 % sí, reintento no duplica y exoneración sin autorización 403. Producción: solo lectura hasta aprobación específica de pruebas/apertura.

## Reversión ordenada
Con corte autorizado y snapshot posterior, conservar dinero/citas/adjuntos. Primero revertir la asignación posterior de capacidades con su recibo de permisos (after.plan_hash); después guards con el PR todavía instalado:

~~~bash
php artisan agenda:mvp-permissions --rollback=UUID_RECIBO_GRANTS --confirm=AFTER_PLAN_HASH_GRANTS --reference=PR5_GRANTS_ROLLBACK
php artisan agenda:mvp-guards --rollback=UUID_PRIMER_RECIBO_GUARDS --confirm=AFTER_STATE_HASH_GUARDS --reference=PR5_GUARDS_ROLLBACK
~~~

Si no hubo asignación, omitir primer comando. Guards rollback rechaza cualquier cambio posterior de catálogo/relaciones hasta revisión; repetir rollback aplicado no cambia filas. Restaura SOLO los guards realmente cambiados, COMERCIAL sigue web, no borra pivots. Revertir guard puede volver a bloquear acceso/editor y no resuelve incidentes de dinero. Si retirar código, volver únicamente a release compatible que conserve Clarity; no reabrir escritores heredados vulnerables ni restaurar BD T0 encima de pagos nuevos.

## Evidencia y decisiones
Fixture exacto de seis roles, cinco vacíos/COMERCIAL web, grants originales por perfil; variantes con permissions web, todos los ordinarios vacíos, permiso extraordinario vacío/directo, varios roles, colisiones, guard api y modelo no compatible. Prueba de historial monetario heredado intacto, previa sin cambios, ausencia de grants implícitos, fracaso de recibo con rollback y reversión grants→guards.
Resultados finales en docs/evidence/urgent1/guards y reporte de entrega. Guards productivos de permissions, selección definitiva y ejecución productiva: PENDIENTES de evidencia y aprobación. No cambia precio, 50 %, privacidad, idempotencia ni concurrencia financiera. Sin nueva migración, Caja completa, retiro de heatmap o cambio Clarity.
