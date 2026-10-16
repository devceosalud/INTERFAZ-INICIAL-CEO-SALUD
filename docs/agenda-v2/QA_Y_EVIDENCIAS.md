# QA y evidencias

## Ambiente y baseline

PHP 8.3.35 del runtime local existente, Laravel 9.52.21, PHPUnit 9.6.34, Node 26.3.0, MariaDB aislada 11.8.9 y Chrome headless 155.0.8059.79. Todos los pacientes, usuarios, pagos y comprobantes utilizados son ficticios. Servicios externos de la aplicación bloqueados con fakes; navegador limitado a localhost con CSP y bloqueo de conexiones externas. CSRF real habilitado en el router del navegador.

Se extrajo una copia intacta de 9c3b93a y se ejecutó antes de adaptar los tests: **1.013 pruebas PHP, 8.230 aserciones, cero errores/fallos/omisiones**. JavaScript baseline: **302/302**. La primera ejecución dentro del sandbox encontró una limitación de is_writable de PHP en Windows; se corrigió ejecutando las suites autorizadas fuera del sandbox, manteniendo las conexiones ficticias verificadas.

## Resultados de verificación

La suite SQLite completa de v2 registra **1.039 pruebas / 8.513 aserciones**, sin fallos ni omisiones. JavaScript ampliado: **309/309**, sin fallos ni omisiones. La suite MariaDB completa registra **1.044 pruebas / 8.561 aserciones**, sin errores, fallos ni omisiones; incluye cinco carreras de procesos HTTP reales, Unit y Feature. Chrome registra **10/10 escenarios y cero errores JavaScript**. El SHA exacto probado se registra junto al resumen de ejecución y en el PR. No se presenta una compilación como prueba funcional.

Los 26 escenarios PHP nuevos cubren pagos, permisos negativos, cambios simultáneos/versiones, idempotencia, cola, colores críticos, Lima al cruzar medianoche UTC, migración sobre datos ficticios heredados y reversión. Las cinco carreras MariaDB comprueban: misma UUID con un pago/ticket/cita; dos cobros por el mismo intervalo con un único ganador; actualizaciones de procedimientos con una versión perdedora; reintento simultáneo de realización con una única auditoría; dos reversiones con una única corrección.

Tres pruebas nuevas de `doctor-service-compatibility.test.js` verifican el refresco de Bootstrap Select después del cálculo, la conservación de selecciones válidas, apertura del modal, cambios de excepción y funcionamiento sin plugin. El escenario Chrome del maestro utiliza el desplegable visual real para MFR y Traumatología; comprueba además HTTP 422 sin autorización. No modifica registros reales de la profesional reportada.

Chrome usa tres sesiones de usuario (ADMINISTRADOR, ADMISION y COMERCIAL), escritorio 1440×1000 y móvil 390×844. El runner comprueba selección no guardada hasta confirmar, realizados, decisión manual, HTTP 403, actualización compartida sin recarga, cola ordenada, conservación de texto y casillas después de perder foco, reversión con motivo, confirmación automática desde panel de pago, ausencia del botón adicional tras pagar, CSRF 419 y exclusión de Clarity. Guarda capturas y errores JavaScript; cualquier error de página hace fallar la ejecución.

## Contratos de tests existentes

La primera suite v2 completa identificó expectativas incompatibles con el cambio solicitado: pagos positivos que debían permanecer pendientes y operadores ADMISION/COMERCIAL creando por el adaptador heredado. No se desactivaron casos ni se relajaron las comprobaciones de importes, duplicados, permisos o evidencias. Se actualizaron esas expectativas a CONFIRMADA, se ejercitó el adaptador conservado con autorización ADMINISTRADOR y se añadieron 403 para los roles bloqueados. Los perfiles bloqueados continúan el flujo completo por Agenda operativa.

Los tests de privacidad/conciliación de reservas pagadas pendientes usan ahora fixtures históricos explícitos: no se eliminó esa cobertura. Los tests económicos de días 0/1/15/16 conservan sus precios, acreditación y evidencias. Las pruebas de MariaDB reutilizan el aislamiento DDL existente y preservan únicamente URLs heredadas bajo 127.0.0.1:9, para que los fakes HTTP sigan verificándose sin tráfico externo.

## Matriz de aceptación

| Criterios | Evidencia automatizada |
|---|---|
| CA-01 a CA-07 | AgendaV2PaymentsTest; suites de comprobantes/registro/exclusividad; carreras de UUID/intervalo |
| CA-08 y CA-09 | HTTP y HTML en AgendaV2PaymentsTest; LegacyRegistrationParityTest y matrices de todos los roles |
| CA-10 y CA-11 | agenda-v2-refresh.test.js; polling de Admisión existente; Chrome entre sesiones y borrador desenfocado |
| CA-12 a CA-16 | AgendaV2ProceduresTest y carrera de expected_version; Chrome administrador/admisión/comercial |
| CA-17 a CA-25 | Procedimientos + CompatibilityTest; dos feeds, cola cronológica, decisiones manuales, ausencia de dinero/citas nuevas |
| CA-26 | ReevaluationIndicationTest y CommercialReevaluationTest, conservando días 0, 1, 15 y 16 |
| CA-27 a CA-30 | AgendaV2ReversalTest; carrera de reversiones; historial y snapshots; Chrome |
| CA-31 y CA-32 | Suite completa de catálogo PR #45, especialidad/asignación y precios por profesional; doctor-service-compatibility.test.js y Chrome con Bootstrap Select real MFR/Traumatología |
| CA-33 | Suites completas PHP/JS de Caja, vouchers, reservas, OCR, documentos y autorizaciones |
| CA-34 | Capturas e inspección en Chrome escritorio/móvil y comprobación de ancho |

## Reproducción

En checkout aislado sin .env, dependencias de composer.lock, PHP 8.3 y Node:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never
node --test tests/JavaScript/*.test.js
php vendor/phpunit/phpunit/phpunit -c phpunit.agenda-v2-mariadb.xml --colors=never
```

La tercera orden solo funciona con el runtime exacto verificado; no reutiliza otra BD ni usa credenciales del desarrollador. Crear el datadir exclusivo con MariaDB 11.8.9, puerto 33324 y bind local; ejecutar tests/Support/agenda-v2-init.php únicamente con AGENDA_V2_QA_INIT=NEW_SYNTHETIC_RUNTIME. La inicialización rechaza esquemas preexistentes. Para navegador, usar tools/qa/run-agenda-v2-browser.ps1 -ResetSyntheticData; solo reinicia el esquema ficticio de navegador y detiene el servidor que crea.

Los logs/JUnit, contraseñas efímeras y capturas se conservan localmente en docs/agenda-v2/evidence y storage/app/qa-v2; no se suben credenciales ni fixtures de ejecución. El resumen sanitizado y el manifiesto SHA-256 identifican resultados y fuentes; los hashes de texto normalizan CRLF a LF como el almacenamiento de Git. El SHA del commit probado se registra aparte para no crear una referencia circular en el propio commit.

Pendientes de liberación: aceptación del personal sobre los fixtures, backup productivo real, ensayo de despliegue autorizado, eventual concesión individual de supervisión y comprobación del entorno instalado. CI se informa con sus estados reales en el PR; una ejecución pendiente no se declara aprobada.
