# Integración de Agenda 2.0 y hotfixes #47, #48 y #49

Estado vigente: [tercera ronda, permisos documentales, rendimiento y guía local](RONDA_3_DIAGNOSTICO_Y_GUIA.md). Los resultados y decisiones anteriores se conservan como antecedentes.

## Estado protegido y alcance

El 19/10/2026 se encontró `agenda-v2` en `feat/agenda-operativa-flujo-reevaluaciones-v2`, HEAD `38a0446e6c6c30e0a9ef1a9e03bf5a9563ea1849`, sin cambios pendientes. Los 14 archivos mencionados por la UI anterior no estaban modificados según Git; no se descartó ningún archivo. El commit conocido ya incluye los cambios de la segunda ronda. Se conservan sus capturas, JUnit, logs y diagnóstico original.

Se creó la referencia local `codex/agenda-v2-pre-integration-20261019` en ese SHA y un registro inicial ignorado en `storage/app/qa-v2/integration-main/INITIAL_STATE.json`. Las huellas SHA-256 de los cinco archivos modificados del checkout original permiten comprobar que permanecen intactos, incluidas diferencias de finales de línea. No se modificaron otros worktrees. No hubo reset, clean, stash ni push forzado.

Después de fetch, `origin/main` coincidió con `dcb9b2b7c9dafe75d79be95992fdbf0b955a9ace`. Se preparó merge `--no-commit --no-ff` dentro de la rama v2. Este dato verifica GitHub, no el commit instalado en Hostinger. No se accedió a producción ni se ejecutaron migraciones allí.

## Resolución funcional de conflictos

| Archivo | Coincidencia | Resolución |
|---|---|---|
| AdmissionBoardController | Conflicto textual | Conservar filtros avanzados y validación v2; aceptar `tab=citas/reevaluaciones` como compatibilidad HTTP, sin reintroducir otra pestaña |
| AdmissionBoardService | Conflicto textual | Cargar eventos directos por lote, unir orígenes, filtrar/paginar/recalcular indicadores sobre el mismo conjunto y conservar motivo autorizado |
| admission-board.css | Conflicto textual | Conservar distribución compacta v2; usar etiquetas turquesa/naranja aprobadas y acciones por origen, sin restaurar grandes fondos por fila |
| admission-board.js | Conflicto textual | Una sección de reevaluación con progreso, marca directa, decisión parcial, autoría e historial; conservar borradores, versiones y polling |
| admission/index.blade.php | Conflicto textual | Conservar cabecera/listado/detalle v2 y una única entrada a la cola; retirar pestañas/controles temporales redundantes |
| routes/scheduling.php | Merge textual automático revisado | Conservar PATCH manual directo con su autorización específica, añadir GET privado de auditoría y conservar rutas de procedimientos/reversión |

La estructura de UX procede de v2; las responsabilidades del hotfix se incorporaron expresamente. No se resolvió tomando una versión completa sin reconciliar su comportamiento.

## Implementación definitiva

`AppointmentReevaluationService` es una lectura común sobre dos ledgers independientes. No escribe estados, procedimientos, citas ni dinero.

| Origen expuesto | Fuente persistida | Elegibilidad / decisión |
|---|---|---|
| AUTOMATIC | appointment_operational_reviews + procedimientos | Solicitados > 0, todos realizados, cita/jornada activa y READY coherente |
| MANUAL_PARTIAL | Cabecera operativa + appointment_procedure_events | Envío explícito con realizados; pendientes requieren motivo y autorización vigente |
| MANUAL_DIRECT | Último evento MARCADA/DESMARCADA en appointment_events | Marca explícita de cita ATENDIDO con agenda LEGADO/CONFIRMADA; sin salida física para estar activa |

El estado listo es la unión de orígenes válidos, no una escritura que reemplace sus evidencias. Una cita ocupa una sola fila de cola aunque tenga varios orígenes. Se muestran sus orígenes activos. Retirar la marca directa no toca la cabecera de procedimientos; retirar el envío por procedimientos no toca eventos directos. Los pendientes se conservan.

La marca directa admite una cita ATENDIDO sin procedimientos y también puede coexistir con procedimientos. No acredita que un examen se haya realizado. El envío parcial por procedimientos mantiene su propia exigencia de al menos uno realizado; cero realizados no se convierte en autorización parcial por una marca directa. COMERCIAL puede usar únicamente la operación directa autorizada.

Un automático 2/2 desmarcado a 1/2 pierde ese origen. Una autorización parcial anterior sigue sus reglas: se conserva con realizados, se suspende al quedar cero y una retirada explícita no se reactiva por polling. Una marca directa anterior se reconoce mientras sea elegible, independientemente del contador. Estados cancelados, retiro, no asistencia y salida física desactivan sus condiciones de cola correspondientes sin borrar la marca ni sus eventos. La cola actual sigue limitada al día de Lima; la consulta histórica respeta el rango pedido.

El diagnóstico anterior permanece: el fixture original 1/2 naranja tenía SEND manual explícito previo. La prueba de cabecera automática READY incoherente se mantiene y sigue pasando. No se reinterpretan todos los 1/2 naranjas como errores.

## Componentes conservados, sustituidos y retirados

- Se conserva `ManualReevaluationService` como escritor del ledger directo y su controlador/ruta PATCH como contrato compatible. Permanecen tipos de eventos, `ADMISSION_MANUAL_V1`, actor, timestamp, UUID, payload_hash, expected_marked/expected_version, control ABA, locks y reintentos.
- Se conserva `OperationalProcedureService` como autoridad transaccional de solicitudes/realizados/decisión parcial. Su método de cola delega en el lector unificado. No se duplican filas ni se sincronizan dos marcas a la fuerza.
- Se sustituye `AdmissionAppointmentPresentation` por la presentación compartida de Agenda. ATENDIDO y reevaluación tienen el mismo vocabulario en ambos feeds. Se retiran CSS, pestañas y handler visual temporales que competían con v2.
- Se retira el workflow duplicado `admission-manual-reevaluation-checks.yml`: los tests del hotfix y de la unión se ejecutan dentro de las suites completas de Agenda v2, además de los workflows existentes. No se elimina ningún test funcional.
- Se retiran los cuatro helpers del runtime SQLite independiente del hotfix. Los reemplazan el router seguro/aislamiento MariaDB v2, el seed append-only de integración y Chrome existente ampliado. Los archivos SQLite y artefactos locales anteriores no se borran; los scripts y evidencias originales siguen en el historial de main.

## Interpretación de historia y auditoría completa

Se lee el último ID de MARCADA/DESMARCADA por cita; eventos de otro tipo no cambian la marca, aunque tengan una hora igual o posterior. Se conserva actor_user_id y se muestra su nombre disponible. No se inventan usuarios, procedimientos, órdenes clínicas, pagos ni fechas.

Los eventos v1 no se copian a las tablas nuevas. Los orígenes nuevos y antiguos se evalúan independientemente; se agrupan por appointment_id al consultar. Esto evita duplicados sin borrar ni transformar datos productivos.

El detalle muestra ambos ledgers y permite cargar movimientos anteriores de 40 en 40. El endpoint autenticado `/scheduling-mvp/admission/appointments/{id}/reevaluation-history` aplica visibilidad de cita y no-store. Su referencia de lectura fija el máximo ID de cada ledger: nuevas acciones no desplazan páginas ni repiten eventos, incluso con timestamps iguales. La auditoría abierta pausa la sustitución del detalle para conservar posición; la cola independiente sigue actualizándose. Al volver a consultar se obtiene una referencia actualizada.

Los timestamps se serializan como ISO con zona desde la configuración de almacenamiento y se muestran en America/Lima. No se reescriben horas históricas. La razón se muestra asociada a SEND/WITHDRAW, evitando atribuir una razón anterior a una nueva selección o realización.

## Paleta y prioridades

ATENDIDO: #74DCCB / #174D4A; el texto auxiliar del retorno usa #285D58. Reevaluación: #FFC28A / #7C2D12. Se usan etiquetas y origen escrito, sin depender del color. LEGADO conserva reglas/datos y deja de consumir una línea vacía en el listado.

Cancelación/retiro, reembolso y salida conservan su prioridad por encima del naranja. Para el origen automático/parcial permanece la cautela financiera previa. Una marca directa explícita mantiene el comportamiento aprobado del hotfix ante un snapshot legado sin atribución: naranja con advertencia CONCILIAR visible, sin convertirlo en dinero acreditado. La alerta de reembolso continúa prevaleciendo. Las aserciones de señales médicas/financieras siguen presentes.

## Permisos y seguridad

COMERCIAL activo con MVP_ACCESS/VIEW puede marcar/desmarcar únicamente la marca directa de sus citas visibles y elegibles, sin appointment.update. ADMINISTRADOR/ADMISION conservan UPDATE para esa operación directa. Los permisos de solicitudes, realizados, supervisión parcial, atención, pagos, precios y documentos no se otorgan ni se amplían por este cambio.

Se conservan CSRF real, rate limits, autorización backend, visibilidad dentro de transacción, UUID por actor, rechazo de payload adicional, conflictos de versión y auditoría atómica. Un reintento no revive una marca que fue retirada después. En 840522e una reserva privada ajena devolvía 404 también al administrador. En tercera ronda se amplía expresamente su lectura operativa; los escritores mantienen sus controles. La auditoría nueva es de lectura y no expone snapshots completos ni contenido en telemetría/Clarity.

## QA y prueba manual

Resultados verificables y capturas en [QA](QA_Y_EVIDENCIAS.md); inventario en [ARCHIVOS_MODIFICADOS](ARCHIVOS_MODIFICADOS.md). Los resultados de 38a0446 son históricos y no sustituyen las ejecuciones integradas.

El ERP local sigue en http://127.0.0.1:8092, router seguro y MariaDB ficticia 127.0.0.1:33324. Usar fecha 19/10/2026 y MÉDICO INTEGRACION FICTICIO. Las cuentas `agenda-integration-admin`, `agenda-integration-admission` y `agenda-integration-commercial` bajo @example.invalid usan la contraseña ficticia local de `storage/app/qa-v2/browser-private.json`; no se publica en Git. COMERCIAL de integración no tiene permisos generales de edición ni cobro.

Fixtures: FRESH2 para 1/2 sin autorización; MANUAL2 para parcial autorizado; DIRECT para marca directa y coexistencia; DUAL_AUTO para automático independiente; HISTORIC para evento v1 sin procedimientos; AUDIT para más de 40 movimientos históricos. El seed añade solo claves faltantes y preserva usuarios, horarios, decisiones y datos previos. No se reinicia la BD manual ni se cambia la fecha de los fixtures antiguos.

Para detener el entorno usar el script local guardado `storage/app/qa-v2/stop-manual-qa.ps1`; `-StopDatabase` detiene también la instancia MariaDB verificada. No detener otros servidores ni MySQL ajenos. La guía local ACCESO_MANUAL_QA.md contiene las credenciales originales y estas indicaciones.

## Esquema y rollback

Sin migración adicional por integrar los hotfixes. La liberación de v2 desde main aún necesita la migración aditiva de las tres tablas de procedimientos antes del código; no altera los ledgers v1. No se ejecutó ninguna adaptación en producción.

Rollback preferido de código a dcb9b2b, conservando tablas y eventos. Main puede leer marcas directas nuevas porque mantienen el contrato v1; ignorará temporalmente procedimientos/cola v2, cuyos datos y auditoría se conservan. No convertir decisiones al revertir, no eliminar tablas pobladas, no desconfirmar citas ni revertir pagos. La compatibilidad de datos se prueba en fixtures equivalentes; el commit instalado y el backup real de Hostinger siguen sin verificarse y requieren una operación autorizada antes de liberar.

## Continuación20/10: reportes recientes, solo integración local

Origin/main verificado en76e6ad21d69372d429698de35142689dfd6bd873. PR51 ya estaba en0ba1c23; PR53–55 se incorporaron mediante merge c598129 tras checkpoint9c78b3b y ensayo merge-tree, sin conflictos textuales. Su diff neto de nueve archivos se conserva íntegro: preloader/shell, CSV, XLSX con autofiltro, operaciones bancarias, pruebas y workflow. No se reintroducen los cambios revertidos delPR50. No cambian interpretación histórica ni independencia de los tres orígenes de reevaluación.

PR46 cerrado, Draft, sin fusionar; no se publica ni reabre. [Acta vigente y riesgos](CONTINUACION_20261020.md), [inventario](ARCHIVOS_MODIFICADOS.md).
