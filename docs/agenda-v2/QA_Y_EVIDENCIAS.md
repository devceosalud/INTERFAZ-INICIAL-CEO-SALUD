# QA y evidencias

Estado vigente: [tercera ronda, permisos documentales, rendimiento y guía local](RONDA_3_DIAGNOSTICO_Y_GUIA.md). Los resultados y decisiones anteriores se conservan como antecedentes.

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
