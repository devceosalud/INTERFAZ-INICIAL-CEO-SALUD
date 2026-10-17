# QA y evidencias

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

El recorrido de pago respeta privacidad: la misma cuenta de ADMISION crea y cobra su reserva. ADMINISTRADOR mantiene el filtro previo de seguimiento por pago/presencia; no se cambia para facilitar un test. Cada repetición crea un paciente ficticio único y escoge un horario libre sin borrar la BD manual.

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
