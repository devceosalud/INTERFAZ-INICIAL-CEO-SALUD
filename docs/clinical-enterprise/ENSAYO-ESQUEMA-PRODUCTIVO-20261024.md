# Ensayo aislado de esquema productivo — PR72 — 24/10/2026

## Dictamen

**APTO CON CONDICIONES para las cinco migraciones corregidas. No es autorización de merge ni despliegue.**

La candidata remota examinada es `f0290435b5576f10be93a5ccb97923cff44cf47c`; main y el SHA productivo informado coinciden en `a4e2a9b0f9e517c55be10232321391b1dc970e73`. Es necesario incorporar al PR72 la corrección local de050000 antes de aprobar la candidata. No se hizo push, merge, despliegue ni conexión a Hostinger durante este trabajo.

También debe decidirse el tratamiento del riesgo heredado de caja descrito abajo, y completarse respaldo/restauración, APP_KEY, volumen, locks y verificaciones finales en la futura ventana autorizada.

## Seguridad del archivo recibido

El archivo indicado por el usuario **contiene datos**, no solo estructura:4.911.705 bytes,52 CREATE TABLE,137 ALTER TABLE y131 INSERT INTO, incluyendo pacientes, usuarios, citas, pagos y documentos. Un escaneo sin imprimir valores detectó13 hashes bcrypt. No se certifica que esté libre de otros secretos. No se mostraron valores clínicos ni se copiaron sus filas a ninguna base de ensayo.

SHA256 original: `febe22b82e11f389d01c826b94d01aba0ad42b5cf9c82c4024687458c2a290ed`. Original intacto y fuera de Git. No se copió el archivo completo al repositorio.

La copia privada neutralizada contiene exclusivamente52 CREATE TABLE y137 ALTER TABLE permitidos, más SET NAMES local. Se retiraron131 INSERT completos, comentarios, directivas de sesión/transacción y40 contadores AUTO_INCREMENT de volumen. Se conservaron atributos AUTO_INCREMENT de las PK, índices, FKs, tipos, defaults y collations. No se encontraron CREATE DATABASE, USE, DEFINER, vistas, triggers, procedimientos, eventos, operaciones de archivos ni referencias cruzadas de esquema. El extractor rechaza instrucciones desconocidas; no ejecuta el dump original.

SHA256 DDL neutralizado: `58de7b4371cf63d5aa9374701f8e6d52c17eb2695ee2ccca4953aa5ad638c850`. SQL, configuración y credenciales locales permanecen privados/excluidos; la evidencia versionable contiene solo metadatos, conteos y resultados.

## Entornos y reconstrucción del historial

- PHP CLI local8.3.35 y MariaDB local11.8.9, coincidentes con las versiones informadas por el usuario. InnoDB/utf8mb4_unicode_ci. Los JSON usan la representación y CHECK propios de MariaDB; el camino MariaDB está verificado, no se certifica aquí otro MySQL exacto.
- Instancia nueva enlazada solo a127.0.0.1:33346, datadir propio, sin reutilizar la demo33345. Cuentas QA restringidas a su esquema; sin FILE ni privilegios globales. Integraciones Laravel deshabilitadas/simuladas, clave generada exclusivamente para cifrados sintéticos, sin .env compartido.
- Base de ensayo con datos sintéticos: `ceosalud_qa_prod_schema_pr72_20261024`. Segundo ensayo limpio: `ceo_qa_pr72_clean_20261024`. Nunca se creó ni se utilizó localmente el nombre de la base productiva.
- Tras restaurar el DDL se verificó que las52 tablas tuvieran cero filas. Solo después se insertó el historial técnico reconstruido desde la [salida SSH aportada](qa/schema-production-20261024/hostinger-migrate-status.txt):60 nombres/lotes exactos, incluido batch0. Se contrastaron independientemente con los60 registros técnicos de migrations del export; coinciden. Los IDs del ledger local son nuevos y no pretenden reproducir IDs productivos.
- [Migrate:status local previo](qa/schema-production-20261024/local-migrate-status-60.txt):60 Ran y exactamente cinco Pending. Ninguna migración histórica se reejecutó sobre el esquema restaurado. Las tres del21/10 están reconocidas en lotes29,30 y31.
- La revisión por migración histórica enumera las tablas referenciadas y su presencia. La comparación estructural completa con la referencia local PR72 detecta las dos divergencias heredadas señaladas abajo. Una exportación de DDL no demuestra asignaciones efectivas de permisos/roles ni datos creados por migraciones históricas: esa limitación permanece explícita.

## Resultado de las cinco migraciones

Segundo ensayo sobre una restauración limpia, usando la quinta corregida; tiempos locales sin volumen productivo:

| Orden/archivo | Resultado | Tiempo | Efecto comprobado |
|---|---|---:|---|
| `2026_10_23_010000_add_procedure_request_identity.php` | OK |0,464s| family varchar32 y label_snapshot varchar500 nullable |
| `2026_10_23_020000_create_patient_operational_alerts.php` | OK |0,202s| Dos tablas de alerta, único por paciente y actor/UUID, FKs restrictivas |
| `2026_10_23_030000_create_medical_procedure_followups.php` | OK |0,138s| Dos tablas de seguimiento, único por solicitud, índice de cola y eventos |
| `2026_10_23_040000_preserve_clinical_order_details.php` | OK |0,017s| JSON de especificación y MEDIUMTEXT cifrado de orden |
| `2026_10_23_050000_enable_personal_procedures_and_manual_followups.php` | OK |0,517s| Sugerencias UUID, referencia reutilizable, FK nullable, origen/motivo/general y únicos |

Resultado:57 tablas,65 migraciones Ran, sin pendientes. Lotes nuevos32–36 porque se ejecutó una ruta por comando. El ensayo limpio termina con cero filas de aplicación; el otro contiene exclusivamente fixtures generados en este trabajo. [Registro de las cinco](qa/schema-production-20261024/five-migrations-clean.json).

## Quinta migración: ALTER y reanudación

Se creó un seguimiento y evento cifrados sintéticos después de030000 y antes de050000. El ALTER `MODIFY requested_procedure_id BIGINT UNSIGNED NULL` conservó IDs, relaciones, valores, estados, notas, evento y cifrados. Se compararon260 valores previos en55 tablas de aplicación; no se alteraron. Las nuevas columnas reciben AUTOMATIC/null/null. La FK y el único por solicitud se mantienen.

Se probaron rechazo1062 por solicitud duplicada, rechazo1452 por solicitud inexistente, inserción nullable para seguimiento general histórico, rechazo1062 por general duplicado y rechazo1451 al intentar borrar una solicitud referenciada. Esas excepciones eran resultados esperados, no pruebas omitidas.

**Defecto reproducido en f0290435:** después de existir `general_key`, pero faltar `mpf_general_unique`, up() no recreaba el índice. Igual ocurría con `reusable_procedure_id` ya creada y su FK ausente. Son límites reales entre sentencias DDL que MariaDB confirma por separado.

**Corrección local:** recuperar índices/FKs independientemente de la existencia de tabla/columna; validar el orden de columnas, unicidad y destino/restricción FK; rechazar definiciones incompatibles sin destruirlas; verificar BIGINT UNSIGNED antes del ALTER y omitirlo si ya es nullable. El esquema final no cambia y no se añade migración.

Se simularon y recuperaron cinco estados parciales: único general ausente, FK reutilizable ausente, FK de usuario de sugerencia ausente, FK de médico ausente y único de sugerencias ausente. Cada caso ejecutó up() dos veces; las filas y auditorías permanecieron idénticas. SQLite recupera también su índice reutilizable; las FKs que SQLite integra atómicamente se validan, sin fingir reparaciones de un esquema incompatible. Un índice homónimo no único se rechaza mediante prueba específica.

Esto no autoriza reintentos ciegos de las otras cuatro migraciones ni dos migradores concurrentes. Ante DDL parcial: mantenimiento, inspección, reparación hacia delante revisada y comprobación antes de permitir que Laravel registre la migración. No marcar Ran manualmente en producción.

## Compatibilidad y divergencias heredadas

Comparación semántica contra la referencia PR72 local:57 tablas,614 columnas,247 partes de índices y116 relaciones FK. Cero diferencias de índices, FKs, CHECKs, motores o collations; cero huérfanos. Las siete tablas afectadas por PR72 coinciden con el esquema esperado. Los metadatos completos y SQL permanecen privados.

Dos diferencias previas en las52 tablas recibidas, conservadas sin modificación:

1. **`vouchers.observacion` singular**, mientras la migración histórica de Git define `observaciones`. El modelo Voucher no incluye ninguno de los dos campos en fillable; no se encontró dependencia de esa columna en los caminos de escritura inspeccionados. No se renombró ni se afirma equivalencia universal de módulos antiguos: requiere revisión/aceptación específica si se usa por integraciones externas.
2. **`cashier_shifts.abierto_en` tiene DEFAULT/ON UPDATE CURRENT_TIMESTAMP** en el export; la referencia de migraciones no contiene ON UPDATE. En una transacción sintética, cerrar un turno cambió la fecha de apertura. Se revirtió esa transacción local. El cierre actual de `CashierShifts.php` no reasigna explícitamente la apertura, por lo que el riesgo es real y anterior a PR72. No se corrigió automáticamente ni se alteró caja en producción. **Debe revisarse antes de aprobar la ventana**; una eventual corrección necesita su propio alcance y ensayo, sin reescribir aperturas históricas por suposición.

## Escenarios y pruebas

Sobre la estructura restaurada y fixtures nuevos se comprobó selección/reintento, MRI y cirugía automáticas, envío manual de radiografía/curación, alta posterior de otro procedimiento, UUID personal/FK, orden cifrada y alerta auditada/reintento. Resultado:7 seguimientos sintéticos, una sola cita, cero pagos/comprobantes creados, cero procedimientos marcados realizados y atributos de cita/atención intactos.

| Suite posterior a la corrección | Resultado |
|---|---|
| Regresión específica de migración SQLite |3 pruebas /36 aserciones, OK|
| Regresión específica de migración MariaDB11.8.9 |3 /36, OK|
| PHPUnit completo SQLite |1286 /11898, OK|
| PHPUnit completo MariaDB11.8.9 |1320 /12205, OK|
| JavaScript |369 OK|

Cero errores, fallos, warnings o skips en PHPUnit; JS cero fallos/canceladas/skips. No se eliminaron pruebas. La modificación de producto se limita a050000; no se cambiaron controladores, servicios, UI ni agenda operativa. No se repitieron recorridos Chromium en este ensayo de esquema; los resultados de navegador previos pertenecen a la revisión anterior y no se presentan como nuevos.

## Límites y preparación de la ventana

- No se ensayó con filas productivas ni se midió el ALTER bajo volumen/contención reales. Los tiempos locales no son una ETA de publicación. Hay que verificar espacio, metadata locks, transacciones abiertas, límites de hosting y disponer de ventana.
- No se comprobó APP_KEY productiva ni la restauración de backups del host. No rotarla. El cifrado aquí se verificó únicamente con una clave local generada y fixtures.
- No se probaron datos efectivos productivos de permisos, cuenta2/vínculo, integraciones ni validez clínica del catálogo. No se importaron esas filas para facilitar pruebas.
- El SQL original contiene información sensible: mantenerlo privado/restringido; no compartirlo como supuesto archivo de estructura. Para otras revisiones, exportar solo DDL desde el origen mediante un proceso autorizado.
- Las incidencias del extractor local (una coma residual al retirar un contador) y del segundo ensayo con nombre de BD largo en Windows se corrigieron solo en herramientas privadas. Este último devolvió1049 al crear tablas; repetir con un nombre QA más corto funcionó. No se atribuye ese fallo a MariaDB/Linux productivo. Se restauró desde vacío con el DDL final validado; no se alteró el original ni se ocultó ningún error de producto.
- Corrección pendiente de revisión/incorporación al PR72 y sus checks sobre el nuevo SHA. El runbook anterior ya no debe afirmar que todas las guardas originales cubrían DDL parcial.

## Respaldo y rollback

Seguir el [runbook actualizado](PREPARACION-PRODUCTIVA-PR72.md): artefacto exacto, mantenimiento y bloqueo de nuevas escrituras, respaldo consistente de BD/migrations/código/dependencias/configuración/archivos privados, hashes y restauración verificada antes de publicar. Nunca copiar dumps/configuración/fixtures QA al host.

Aplicar solo las cinco pendientes por ruta, detenerse ante el primer error y verificar estructura/ledger después de cada una. Ante fallo parcial no borrar tablas, auditorías ni registros del ledger productivo.050000 corregida recupera los casos probados, pero un conflicto de tipo/índice/FK requiere investigación.

Rollback preferente de código o corrección hacia delante, **conservando todas las ampliaciones de esquema y filas**. No ejecutar down/migrate:rollback ni restaurar automáticamente un backup anterior sobre nuevas operaciones.050000 permanece forward-only. Mantener mantenimiento hasta validar el artefacto anterior con solicitudes específicas y datos nuevos; reconciliar cualquier escritura posterior en una copia aislada bajo procedimiento aprobado. Conservar APP_KEY y documentos correspondientes.
