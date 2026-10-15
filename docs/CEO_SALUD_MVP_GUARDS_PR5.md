# PR #5 — normalización delimitada de guards heredados
Hallazgo Hostinger confirmado: ADMINISTRADOR, ADMISION, CAJA, FACTURACION y RECEPCION tienen guard_name exactamente vacío; COMERCIAL tiene web. Hostinger confirmó **23 permisos, todos guard_name=web**. No se normaliza ninguno de ellos.

## Efecto y solución
La versión instalada de Spatie consulta nombres por guard; findByName(..., web), assignRole y givePermissionTo pueden fallar con los guards vacíos. Sin embargo hasRole por nombre/pivots y ciertos permisos web ya vinculados pueden funcionar: no afirmar que todos los accesos previos estaban denegados. Bootstrap/editor de roles/usuarios filtran web y excluirían cinco perfiles. El bootstrap ahora muestra guard_issues y rechaza apply si hay guards vacíos o permisos vinculados incompatibles en los objetivos.

Nuevo comando **agenda:mvp-guards**, previa por defecto, separado de agenda:mvp-permissions. Enumera roles/permisos, guards, conteos de relaciones directas y de rol, incompatibilidades y cambios propuestos sin nombres/IDs de pacientes o usuarios. Solo cambia guard_name de las filas seleccionadas por ID conservando IDs, nombres, updated_at, usuarios y todos los pivots. No crea/fusiona roles/permisos ni asigna, elimina o recalcula dinero/históricos.

Rechaza duplicados/colisiones (igualdad según collation de la BD), cambios concurrentes de catálogo/pivots, guards api/otros/null/espacios, modelos asignados distintos a User e incompatibilidades de permisos compartidos. Configuración teams/wildcard/custom tables/pivots/auth incompatible bloquea la reparación delimitada. Nunca convertir indiscriminadamente guards vacíos en web con UPDATE masivo.

**Alcance definitivo de esta corrección:** únicamente ADMINISTRADOR, ADMISION, CAJA, FACTURACION y RECEPCION. COMERCIAL no es objetivo y conserva web. No usar --permission-id: selected_permission_ids debe ser []; changes debe contener exactamente cinco filas de roles, todas vacío→web. Los 23 permisos y sus relaciones se preservan íntegros. Normalizar roles no concede por sí mismo las seis capacidades ordinarias.

### Facultades extraordinarias preexistentes
Catálogo comunicado por el responsable productivo:

| Rol | Permisos actuales por rol | Facultades extraordinarias explícitamente confirmadas |
|---|---:|---|
| ADMINISTRADOR | 9 | Nombres completos no aportados en este resumen; no inferir facultades por el rol. |
| ADMISION | 8 | appointment.withdraw y appointment.additional.create |
| CAJA | 1 | GESTION_CAJA, preservado; no se deduce alcance financiero adicional. |
| COMERCIAL | 6 | appointment.withdraw y appointment.additional.create |
| FACTURACION | 0 | Ninguna por rol; no descarta otras asignaciones del usuario. |
| RECEPCION | 1 | GESTION_RECEPCION, preservado. |

En esta versión instalada, User::can resuelve el permiso web y hasPermissionViaRole comprueba relaciones por ID sin filtrar el guard del rol. Por ello **ADMISION puede tener ambas capacidades efectivas incluso con guard vacío**; COMERCIAL ya tiene web. Una prueba HTTP ficticia antes/después de reparar solo los cinco roles demuestra retiro autorizado en ambos perfiles en ambas fases. No afirmar que normalizar concede por primera vez appointment.withdraw, ni usar guards vacíos como protección financiera.

Con MVP habilitado y permisos de acceso/view efectivos, appointment.withdraw permite marcar RETIRO (libera ocupación, mantiene dinero e historial) y también solicitar devolución mediante la ruta existente. La solicitud reserva saldo disponible para evitar reutilización; no paga una devolución ni genera egreso automático. Siguen las condiciones de estado/presencia, motivo, privacidad, idempotencia y atribución/importe de vouchers. El acceso a historia también contempla esa capacidad. appointment.additional.create permite el flujo explícito de cita adicional; no pertenece al conjunto ordinario ni implica exoneración.

Al habilitar pantallas/flags del MVP o renovar sesiones/caché, estas facultades conservadas pueden quedar visibles/usables. Tratarlas como derechos extraordinarios activos para ADMISION y COMERCIAL, sin prometer el resultado individual productivo sin revisar sus otros roles/directos/configuración. Su conservación está instruida; cualquier futura retirada/cambio requiere decisión separada y no se ejecuta aquí. El bootstrap no las añade a otros perfiles ni revoca las existentes.

En MariaDB exige las cinco tablas Spatie InnoDB y UNIQUE(name,guard_name) en roles/permissions; si faltan, bloquea sin alterar esquema. Transacción + locks de catálogo/pivots, SHA256 del plan y estado, referencia de autorización, recibo privado before/after/changes y logs de auditoría; invalida caché Spatie. Solo guarda guards originales y huellas de relaciones, sin publicar identidades. Reintentar no vuelve a cambiar guards; conservar primer recibo CON changes para reversión. Si recibo/cache/log/commit falla o hay interrupción, contrastar BD y recibo antes de inferir éxito: almacenamiento de archivos y commit de BD no son atómicos.

## Secuencia operativa — no ejecutada en producción
1. Catálogo/guards cerrado con evidencia productiva: seis roles y 23 permisos web. No repetirlo ahora. Continuar únicamente los conteos, modo/configuración piloto y metadatos pendientes del preflight general. Reservar docs/CEO_SALUD_MVP_GUARDS_SELECT.sql para evidencia/check posterior al corte, no para corregir datos.
2. Cerrar condiciones del preflight general, incluida privacidad Clarity. Con autorización FINAL, corte de escritores/editores/colas y backup completo de BD/archivos/storage, incluidos cinco conjuntos Spatie (roles, permissions y tres pivots), cachés/configuración bajo custodia y recibos privados. Restauración ensayada en destino nuevo aislado. No basta un respaldo de roles sin relaciones. Si merge autodespliega, backup/corte/aprobación preceden al merge.
3. Publicar release PR #5 autorizada en mantenimiento; los comandos nuevos no existen en el main productivo anterior. Verificar destino/SHA y usuario CLI, configuración web y acceso privado/escritura del storage de recibos. Conservar Clarity, APP_KEY, .env y adjuntos. Sin migrations/seeders.
4. Generar/revisar previa exacta de los CINCO roles vacíos. Su ejecución productiva todavía requiere aprobación. No incluir COMERCIAL ni --permission-id. Si la previa difiere del catálogo confirmado, detener y presentar la diferencia.

~~~bash
php artisan agenda:mvp-guards --role=ADMINISTRADOR --role=ADMISION --role=CAJA --role=FACTURACION --role=RECEPCION
# Solo con autorización FINAL, aplicar exactamente las mismas cinco opciones:
php artisan agenda:mvp-guards --role=ADMINISTRADOR --role=ADMISION --role=CAJA --role=FACTURACION --role=RECEPCION --apply --confirm=PLAN_HASH_GUARDS --reference=PR5_GUARDS_AUTORIZADOS
~~~

Las diferencias anteriores vacío/web en existing_link_mismatches son el hallazgo esperado; no equivalen a blockers del estado proyectado. Exigir blockers=[], selected_permission_ids=[] y exactamente cinco changes de roles vacío→web. Ningún change de permissions; COMERCIAL no figura entre cambios. Si falta InnoDB/UNIQUE u otro contrato, detener sin corregir schema. Nunca aplicar previa sin objetivos explícitos ni sustituir marcadores por huellas antiguas.

5. Guardar recibo UUID/after.state_hash en storage/app/private/mvp-guard-receipts; comprobar salida 0 y SELECT posteriores: mismos IDs/nombres/cantidad/relaciones; solo guards aprobados pasan a web, sin incompatibilidades nuevas. Si un resultado difiere, mantener corte, no forzar.
6. Después, en una operación SEPARADA, generar previa de agenda:mvp-permissions con los SEIS roles participantes propuestos (incluye COMERCIAL) o la lista definitiva aprobada, y confirmar su propia plan_hash. La lista de normalización de cinco no sustituye la lista de habilitación ordinaria de seis. Exigir guard_issues=[] y missing_definitions=[]; aplicar con referencia separada. Conserva grants previos y añade solo access/view/create/update/reschedule/payment.submit. No reutilizar hash de guards para grants. Recargar sesiones/formularios; otros modelos persistentes deben descartar relaciones cargadas viejas. Verificar caché del servicio web.
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
Resultados previos en docs/evidence/urgent1/guards y reporte de entrega. Nueva comprobación extraordinaria enfocada: PHP 8.3.35/SQLite y MariaDB 11.8.9 aislada, 1 prueba/40 aserciones aprobadas en cada runtime; evidencia web-extraordinary-*.txt. No se repitieron suites completas ni JS. Catálogo confirmado, normalización exclusivamente de cinco roles preparada; ejecución productiva sigue pendiente de aprobación. Conteos/mode piloto y demás puertas del preflight continúan pendientes. No cambia precio, 50 %, privacidad, idempotencia ni concurrencia financiera. Sin nueva migración, Caja completa, retiro de heatmap o cambio Clarity.
