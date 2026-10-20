# QA y evidencias

## Resultado final local del20/10/2026

Código funcional: d93f4841d68bb864c2aa2545284f27d81df88d1c, integrado con main76e6ad21d69372d429698de35142689dfd6bd873. La confirmación de documentación/evidencias conserva estos mismos blobs de fuente; [manifiesto](evidence/FUENTES_SHA256.json). PR46 está cerrado, Draft y sin fusionar. No se hizo push ni se modificó GitHub.

| Verificación terminada | Resultado | Fallos / errores / omisiones |
|---|---|---|
| Sintaxis PHP |135 archivos |0 |
| Sintaxis JavaScript |36 archivos |0 |
| PHPUnit SQLite aislada |1130 pruebas /10028 aserciones |0 /0 /0 |
| PHPUnit MariaDB11.8.9 QA |1142 pruebas /10141 aserciones |0 /0 /0 |
| Concurrencia real MariaDB |12 carreras, incluidas en la suite |0 |
| JavaScript completa |324 pruebas |0 /0 /0 |
| Chrome real /Playwright |43 recorridos funcionales,14 nuevos/actualizados +29 conservados |0 errores inesperados |
| Consola y recursos con tres roles |10 superficies revisadas |0 errores inesperados;49 bloqueos CSP esperados |
| Salida física |6 citas ficticias distintas; un POST por salida |0 duplicaciones |
| GitHub Actions del SHA local |Pendiente de autorización de publicación |No se ejecutó; no se sustituye por CI histórica |

Los procesos finalizaron realmente con exit0; JUnit y JSON completos se verificaron antes de registrar estos resultados. [Acta de resultados](evidence/round4/RESULTADOS.json), [SQLite XML](evidence/round4/sqlite.xml), [MariaDB XML](evidence/round4/mariadb.xml), [JavaScript](evidence/round4/javascript.txt), [sintaxis](evidence/round4/SYNTAX.json), [Chrome14](evidence/round4/browser-results.json), [Chrome19](evidence/round4/regression-results.json), [documentos10](evidence/round4/documents-regression-results.json), [consola](evidence/round4/console-results.json), [CRC/estructura de XLSX descargados](evidence/round4/XLSX_ESTRUCTURA.json).

Chrome155 utiliza exclusivamente127.0.0.1, ADMINISTRADOR/ADMISION/COMERCIAL y datos ficticios. Comprueba gestor/doble clic/Alt+Enter/móvil; bloqueo y restauración tras cancelación/error; revisión409; reprogramación; modalidad con pago intacto; procedimientos personalizados y retirada/recuento; registro de teleconsulta y confirmación única con adelanto; documentos privados y versiones; favoritos por usuario; reportes CSV/XLSX, autofiltros, ceros iniciales, varios abonos, permisos y preloader; etiquetas y capacidad inferior50%. Las29 regresiones conservan MFR/Traumatología, saldos, OCR, precios, creación heredada bloqueada, pago, retroceso, orígenes independientes de reevaluación y auditoría histórica de hotfixes.

La repetición visual verifica también +N mediante teclado en ambos sentidos y que su doble clic no abra el gestor, nombres largos, postoperatorio/herida persistidos, grupos a la derecha, cuatro resoluciones y semana con citas simultáneas. No basta la comprobación geométrica: se inspeccionaron las capturas. La aceptación humana final sigue pendiente.

Los49 avisos de consola son la CSP local bloqueando siete URLs conocidas de recursos externos del tema heredado. Se registran separadamente; no se permiten conexiones externas ni se ocultan errores de aplicación/HTTP o URLs desconocidas. [Hallazgos y ejecuciones previas fallidas](evidence/round4/PRIMEROS_FALLOS_Y_CORRECCIONES.json). La fixture de series se corrigió manteniendo columna/validación, y los datos del19/10 se repitieron con reloj QA explícito, sin cambiar la regla de mismo día ni fechas de citas. El replay se desactivó después; las suites PHP no utilizan ese reloj.

Rendimiento nuevo: clic DOM→request, mediana2,5ms; clic DOM→resultado,277ms (265–308); aplicación105,03ms; SQL33,55ms;51 consultas y un POST en cada salida. [Muestras completas](evidence/round4/departure-results.json), [resumen/metodología](evidence/round4/PERFORMANCE.json). La referencia de tercera ronda era866ms desde el primer request antes de optimizar,3HTTP/85SQL; la serie actual desde el primer request es274,5ms,1HTTP/51SQL. No es una prueba de carga ni una afirmación sobre producción; no se atribuyen diferencias entre sesiones al código sin perfil adicional.

### Capturas comparativas

Antes de etiquetas: capturas conservadas del19/10 en la misma cohorte ficticia; después: replay final del20/10. No se reconstruyó artificialmente un antes con CSS nuevo. Hay más citas ficticias acumuladas; el caso TELE_ADDITIONAL conserva identidad y atributos.

| Vista |1366×768 |1440×900 |1920×1080 |390×844 |
|---|---|---|---|---|
| Antes Agenda operativa |[ver](evidence/round4/before-operative-1366.png) |[ver](evidence/round4/before-operative-1440.png) |[ver](evidence/round4/before-operative-1920.png) |[ver](evidence/round4/before-operative-390.png) |
| Después Agenda operativa |[ver](evidence/round4/operative-1366.png) |[ver](evidence/round4/operative-1440.png) |[ver](evidence/round4/operative-1920.png) |[cabecera](evidence/round4/operative-390.png), [fila](evidence/round4/labels-mobile-context.png) |
| Gestionar cita |[ver](evidence/round4/manage-1366.png) |[ver](evidence/round4/manage-1440.png) |[ver](evidence/round4/manage-1920.png) |[ver](evidence/round4/manage-390.png) |
| Admisión |[ver](evidence/round4/admission-1366.png) |[ver](evidence/round4/admission-1440.png) |[ver](evidence/round4/admission-1920.png) |[detalle](evidence/round4/admission-390.png), [listado](evidence/round4/admission-mobile-list.png) |

[Semana antes](evidence/round4/before-operative-semana.png) → [semana después](evidence/round4/operative-semana.png); [mes](evidence/round4/operative-mes.png); [nombre largo/postoperatorio](evidence/round4/labels-postop.png); [herida](evidence/round4/labels-wound.png); [documentos del modal](evidence/round4/documents-modal.png); [favoritos](evidence/round4/favorites-1366.png); [reportes](evidence/round4/reports.png). En semana, la concentración de varias citas se abrevia por el ancho disponible; nombre accesible/título y gestor conservan el detalle.

[Informe técnico y reproducción local](CONTINUACION_20261020.md). Ningún resultado autoriza publicación, merge o despliegue.

---

Estado vigente de la continuación: [entrega exclusivamente local del20/10/2026](CONTINUACION_20261020.md). PR46 cerrado y sin fusionar; no publicar ni reabrir sin autorización. Los resultados anteriores son antecedentes.

Estado vigente: [cuarta ronda, gestión de citas, procedimientos personalizados y teleconsulta](RONDA_4_DIAGNOSTICO_Y_GUIA.md). Los resultados y decisiones anteriores se conservan como antecedentes.

## Resultados finales de tercera ronda

| Suite finalizada | Pruebas | Aserciones | Fallos / errores / omisiones |
|---|---:|---:|---|
| SQLite aislada | 1083 | 9468 | 0 / 0 / 0 |
| MariaDB QA 11.8.9 | 1092 | 9556 | 0 / 0 / 0 |
| JavaScript | 318 | — | 0 / 0 / 0 |
| Chrome / Playwright | 29 recorridos (19 conservados +10 nuevos) | — | Cero errores JavaScript |

MariaDB incluye nueve carreras: las siete anteriores, salida simultánea sin motivo y carga simultánea de un documento aportado con el mismo UUID. Códigos de salida cero y JUnit completos verificados. [Reportes completos](evidence/round3/RESULTADOS.json), [SQLite](evidence/round3/sqlite.txt), [MariaDB](evidence/round3/mariadb.txt), [JavaScript](evidence/round3/javascript.txt), [Chrome nuevo](evidence/round3/functional-results.json), [regresión Chrome](evidence/round3/regression-results.json).

Chrome comprueba ADMIN/ADMISION/COMERCIAL, reserva ajena sin pago, indicadores persistidos, fila por teclado, filtros/borradores, PDF privado renderizado en canvas, carga en ambas agendas, descarga de bytes iguales, rechazo de MIME inválido, PDF de3 MB aceptado y9 MB rechazado, historial del paciente sin Clarity, reemplazo con motivo e historial restringido. Conserva MFR, matriz automática/manual, hotfixes, motivo obligatorio, precios/pagos, retroceso y capturas1366/1440/1920/móvil. Las imágenes fueron inspeccionadas; la aprobación humana final sigue pendiente.

[Regresiones que fallaron antes](evidence/round3/red.txt) y [pruebas finales dirigidas](evidence/round3/targeted-final.txt). Los cuatro contratos anteriores de lectura ADMIN se actualizan al requerimiento nuevo conservando negativos de escritura, pagos y privacidad de terceros; la etiqueta esperada de documentos cambia manteniendo íntegra la prueba de ubicación OCR. No se eliminan ni se deshabilitan pruebas.

Rendimiento: primer request a resultado visible, mediana866 →297,5 ms;85 →50 consultas y3 →1 HTTP inmediato. Repetición con clic DOM:293,5 ms. [Metodología y límites](RONDA_3_DIAGNOSTICO_Y_GUIA.md). CI se verificará sobre el SHA publicado; los reportes anteriores de840522e son antecedentes.


## Verificación final integrada con main dcb9b2b

Resultados nuevos, posteriores a la integración de #47–#49 y de la auditoría paginada:

| Suite terminada | Pruebas | Aserciones | Fallos / errores / omisiones |
|---|---:|---:|---|
| SQLite completa | 1074 | 9389 | 0 / 0 / 0 |
| MariaDB 11.8.9 completa | 1081 | 9458 | 0 / 0 / 0 |
| JavaScript completa | 315 | — | 0 / 0 / 0 |
| Chrome real / Playwright | 19 casos | — | Cero errores JavaScript |

MariaDB incluye siete carreras: las cinco previas, reintento simultáneo de marca directa con un solo evento y retirada directa concurrente con el último procedimiento realizado. El último caso conserva AUTOMATIC sin duplicar la cita ni el dinero.

Se conservan los 14 tests funcionales del hotfix; se añaden siete casos de unión/historia: coexistencia y retirada independiente, permisos COMERCIAL, contadores sin inferencia, evento v1 sin backfill, estados críticos, paginación estable con timestamps iguales y cambios concurrentes, visibilidad/acceso de auditoría. La única expectativa visual heredada ajustada es ATENDIDO: ahora se afirma su estado y colores aprobados, manteniendo las aserciones de ausencia de salida/reevaluación implícitas. Los tests económicos, privados y de auditoría no se eliminaron ni debilitaron.

Chrome ejecuta las once verificaciones anteriores y ocho integradas: historia v1; COMERCIAL sin UPDATE; negativos de atención/pagos/procedimientos/supervisión y CSRF; retirada de un origen con otro activo; autorización parcial y marca directa coexistentes; borrador ante decisión remota; auditoría paginada con acciones nuevas sin duplicados; capturas de retorno en cuatro resoluciones. Una revisión adicional comprueba que ADMINISTRADOR tenga visibles los realizados en laptop incluso en una cita ATENDIDO. Todas usan datos ficticios actuales del19/10, conservando los fixtures anteriores del17/10.

Primera ejecución completa integrada: un fallo de expectativa PRESENTE frente al nuevo ATENDIDO, corregido mediante aserciones más específicas de color/semántica. Se corrigieron fixtures de historia (recarga de columnas persistidas/estados críticos históricos) y una identidad fija del runner. Para repetir el automático se usa DUAL_AUTO, sin borrar una retirada manual anterior del fixture DIRECT. La aplicación mantuvo correctamente esa retirada; no se alteró su regla para conseguir un test verde.

[Resultados Chrome](evidence/integration/after-results.json), [inspección adicional](evidence/integration/unified-preview-results.json), [laptop administrador](evidence/integration/unified-admin-1366.png), [laptop comercial](evidence/integration/unified-1366.png), [móvil](evidence/integration/unified-390.png), [auditoría paginada](evidence/integration/unified-audit-paged.png). También se inspeccionaron1440×900 y1920×1080. No hubo desbordamiento horizontal innecesario ni errores de página. Aceptación humana final pendiente.

Los JUnit/logs completos están en storage/app/qa-v2/integration-main; las imágenes y JSON sanitizados se incluyen en este PR. El SHA exacto y los resultados de CI se registran en el PR y en un acta local ignorada, evitando una referencia circular en su commit. Un workflow iniciado no se declara aprobado. Las ejecuciones históricas siguientes no sustituyen estos resultados.

Reproducción Chrome en el mismo runtime manual, sin reset:

```powershell
$env:AGENDA_V2_INTEGRATION_FIXTURES = 'APPEND_SYNTHETIC_ONLY'
php tests/Support/agenda-v2-integration-seed.php
$env:AGENDA_V2_FIXTURE_SET = 'integration-main'
$env:AGENDA_V2_ROUND2_STAGE = 'after'
node tools/qa/browser-agenda-v2-round2.cjs
```

El seed solo añade claves ficticias faltantes. Las cuentas de COMERCIAL no reciben UPDATE ni permisos de cobro por probar esta operación. SQLite y MariaDB de suite se ejecutan con sus comandos previos y guardas; la BD manual usa otro esquema. La referencia inicial y las cinco huellas del checkout original están en INITIAL_STATE.json y se verifican al finalizar. Sin acceso a Hostinger ni datos reales.

## Resultados de la segunda ronda

Continuación de `58c5f927f1d5e333b68191b5396cca9671ca28bb` sobre el mismo PR Draft #46. PHP 8.3.35, Laravel 9.52.21, PHPUnit 9.6.34, Node 26.3.0, MariaDB aislada 11.8.9 y Chrome real headless 155.0.8059.79 con Playwright.

| Suite terminada | Pruebas | Aserciones | Fallos / errores / omisiones |
|---|---:|---:|---|
| PHP SQLite completa | 1053 | 8926 | 0 / 0 / 0 |
| PHP MariaDB completa | 1058 | 8974 | 0 / 0 / 0 |
| JavaScript completa | 312 | — | 0 / 0 / 0 |
| Chrome, recorrido ronda 2 | 10 escenarios + matriz | — | 0 errores de página |

Los JUnit y logs completos se conservan localmente en `storage/app/qa-v2/round2/`. [Resumen sanitizado](evidence/RESUMEN.json), [estados/HTTP Chrome antes](evidence/round2/before-results.json), [después](evidence/round2/after-results.json) y [manifiesto SHA-256](evidence/FUENTES_SHA256.json). El SHA exacto de ejecución se registra aparte en el PR y en el registro local ignorado para no crear una referencia circular en su commit. CI se informa por separado con su estado real.

## Investigación y regresión

La matriz se ejecutó primero en el código anterior: no hubo paso automático parcial. Auditoría original: SEND explícito anterior, origen MANUAL; véase [diagnóstico](RONDA_2_DIAGNOSTICO_Y_GUIA.md). Una prueba de integración nueva fuerza una cabecera READY/AUTOMATIC 2/1: [falla antes](evidence/round2/stale-ready-before.txt) y pasa con la defensa de lectura.

`AgendaV2Round2Test` añade 14 casos, incluida matriz parametrizada, ambos feeds y cola en otra sesión, desmarcar realización, origen/autor manual, retirada auditada, sin escrituras clínicas/económicas, motivo requerido en todos los tipos/entradas y modos de pago, normalización Unicode, acceso privado e historia sin backfill, observación de 1 carácter/espacios/1001 caracteres/máximo técnico, legado bloqueado para cuatro roles y filtros/resúmenes consistentes.

Las suites completas conservan precios y compatibilidad del PR #45, confirmación automática con pago, Caja, adelantos, OCR, adicionales, fuera de horario, reprogramación, duplicados/UUID, acreditaciones clínicas días 0/1/15/16, auditoría, documentos privados y roles. MariaDB incluye las cinco carreras originales con procesos separados y barrera de sincronización: UUID/pago único, dos pagos por intervalo, versión de procedimientos, reintento de realización y reversión concurrente.

Los tests válidos reciben motivos ficticios explícitos. Se adapta el contrato deliberadamente retirado: cada caso económico de creación heredada primero comprueba 403 y luego ejecuta la creación por Agenda operativa conservando sus aserciones financieras, de evidencias y duplicados. `agendaCreationResponse` es exclusivamente un helper de tests; no altera rutas productivas ni respuestas. No hay override global de HTTP para introducir motivos, casos omitidos ni pruebas eliminadas. Los tests negativos nuevos envían directamente el motivo vacío a los endpoints reales.

## Chrome y calidad visual

Tres contextos autenticados de Chrome (ADMINISTRADOR, ADMISION, COMERCIAL), CSRF real y únicamente datos ficticios. La matriz confirma 2/0, 2/1 sin autorización, 2/1 MANUAL explícito, 2/2 automático, corrección a 1/2, 3/2, 3/3 y 0/0. Se comprueban respuestas HTTP, origen y contadores; actualización entre las dos agendas y Nicol sin recarga.

Diez recorridos: sincronización; búsqueda y todos los filtros; borradores al filtrar/cambiar pestaña/recibir cambios remotos y 409; motivo histórico y observación 1 carácter; motivo obligatorio/foco/422 conserva texto/reserva+pago30 confirma una sola cita; roles/403/CSRF419; retroceso por UI; Bootstrap Select visual MFR habilitado y Traumatología bloqueado; único motivo al mover el modal de paciente nuevo; geometría/capturas.

Capturas antes/después en 1366×768 y móvil; después además 1440×900 y 1920×1080. Se verifica ausencia de desbordamiento horizontal de página y casillas de procedimientos dentro del área inicial de escritorio. La inspección visual detectó y corrigió hora partida en 1920 y «Más filtros» mal ubicado en móvil. Se revisan imágenes de los cuatro tamaños, listado móvil y cola integrada. Aceptación humana del personal pendiente; no se equipara con que un test pase.

El recorrido de pago respeta privacidad: la misma cuenta de ADMISION crea y cobra su reserva. En la segunda ronda se mantuvo el filtro de seguimiento por pago/presencia. La tercera ronda lo retira por el nuevo requerimiento de negocio, con negativos de privacidad y escritura conservados. Cada repetición crea un paciente ficticio único y escoge un horario libre sin borrar la BD manual.

## Aislamiento y reproducción

Worktree sin `.env`, sin caché productiva. `IsolatedAgendaV2Qa` verifica versión, host loopback, puerto33324, datadir exclusivo, nombres y usuarios ficticios antes de operar. SQLite usa memoria; MariaDB de suite usa `ceosalud_qa_agenda_v2_suite`; Chrome usa esquema separado `ceosalud_qa_agenda_v2_browser`. HTTP/correo/colas/notificaciones externos están sustituidos por fakes. Router localhost con CSP y CSRF real; navegador bloquea toda URL externa y resolución de hosts ajenos. No se accede a pacientes reales ni a Hostinger.

En el runtime local ya preparado:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never
$qaJsTests = @(Get-ChildItem tests/JavaScript -Filter '*.test.js' -File | ForEach-Object { $_.FullName })
node --test @qaJsTests
php vendor/phpunit/phpunit/phpunit -c phpunit.agenda-v2-mariadb.xml --colors=never
$env:AGENDA_V2_ROUND2_STAGE = 'after'
node tools/qa/browser-agenda-v2-round2.cjs
```

El runner ronda2 requiere los archivos locales ignorados de fixtures. Para una instalación QA nueva, reutilizar el bootstrap/seed original y luego el seed append-only de ronda2 con `AGENDA_V2_ROUND2_FIXTURES=APPEND_SYNTHETIC_ONLY`; se niega a sobrescribir su manifiesto. No usar el runner original con ResetSyntheticData mientras alguien navega: reinicializa su esquema y detiene el servidor que crea. Ronda2 conserva la sesión manual8092 y sus datos.

## Baselines históricos

Main `9c3b93a`: PHP1013/8230, JS302. Primera ronda58c5f92: SQLite1039/8513, MariaDB1044/8561, JS309, Chrome10. Esos resultados se conservan como baseline y no sustituyen la nueva ejecución.

Pendientes de liberación: revisión humana de estas capturas/fixtures, backup productivo comprobado, autorización independiente de despliegue y eventual asignación individual de supervisión. Esta entrega mantiene Draft y no ejecuta esos pasos.


## Revisión posterior del gestor y slots

La evidencia histórica anterior corresponde a sus SHAs. Resultados nuevos y capturas en evidence/management; logs completos en storage/app/qa-v2/management. SQLite1136/10079 y MariaDB1149/10201 verificadas sin fallos; MariaDB incluye13 carreras. JavaScript328 pruebas. Chrome contrasta gestor, reprogramación, pagos atribuidos, permisos y cuatro resoluciones; resultados finales desglosados en RESULTADOS.json. [Informe y guía](GESTION_RAPIDA_Y_REPROGRAMACION.md).

Los fixtures de navegador se conservan; cuando agotaron slots se añadió un horario ficticio, sin borrar citas. Las fuentes/CDN/Clarity heredadas bloqueadas por CSP se separan de errores de aplicación mediante URLs exactas. No hay conexiones externas habilitadas para hacer pasar QA.

Cierre:45 casos Chrome aprobados (13+3+19+10), sintaxis13 PHP/13 JS y328 pruebas JavaScript. Suites PHP finalizadas con salida0. [Galería](evidence/management/CAPTURAS.md). Las operaciones financieras no aprobadas permanecen bloqueadas; CI/publicación pendientes de autorización.


## Correcciones de gestor y ficha — 20/10/2026

La nueva ejecución y galería están en evidence/quick-edit. Incluye guardado inline, menores/responsables, validación, concurrencia, roles, ediciones seguras, bloqueos específicos y cuatro resoluciones. Los resultados anteriores no se usan como evidencia de esta revisión; consultar RESULTADOS.json y logs nuevos. [Informe y límites actuales](CORRECCIONES_GESTOR_Y_FICHA.md).
