# QA de catálogo y Agenda — 16/10/2026

Código verificado: `fa3f5047949cdcf78c7dcd46d0bcf6b883ed469c`. Corrección independiente del manejo de 500: `5bab24cd150c18d6ed2f3c823a66062d354f6769`. Base exacta: `c86ffa6e9ed04bb80272db400a63b48604a9a7ef`; `origin/main` volvió a comprobarse antes de preparar commits y no avanzó. La revisión documental posterior no modifica el código probado.

## Ambiente y aislamiento

Windows local, PHP 8.3.35, Laravel 9.52.21, MariaDB 11.8.9 en `127.0.0.1:33323`, Chrome 155.0.8059.79. Las versiones instaladas coinciden con `composer.lock`. SQLite utiliza exclusivamente `:memory:`.

MariaDB tiene bases independientes para suites (`ceosalud_qa_catalog_suite`), navegador (`ceosalud_qa_catalog`) y restauración (`ceosalud_qa_catalog_restore`). El bootstrap verifica nombre, usuario, host, puerto, versión y directorio de datos; rechaza un checkout con `.env`. El navegador se sirve solo en 127.0.0.1:8088/8089 con CSRF real, cuentas/cookies separadas y bloqueo de llamadas externas. No utiliza pacientes, recibos, contraseñas, archivos ni conexiones productivos.

## Resultados ejecutados sobre el código candidato

| Ejecución | Resultado | Aserciones |
|---|---|---:|
| Suite PHP completa, SQLite | 1013 aprobadas; 0 fallos, 0 errores, 0 omitidas | 8230 |
| Misma suite completa, MariaDB 11.8.9 | 1013 aprobadas; 0 fallos, 0 errores, 0 omitidas | 8233 |
| Suite JavaScript completa | 302 aprobadas; 0 fallos/omitidas | — |
| Chrome, escritorio y viewport móvil 390×844 | 12 escenarios aprobados | Interacciones + HTTP + BD |
| Sintaxis PHP/JavaScript modificada | 66 archivos aprobados | `php -l`, `node --check` |
| Restauración sintética | 48 tablas y un archivo privado idénticos por SHA-256 | Comparación íntegra |

No se suman las dos bases como si fueran pruebas de negocio distintas. No hay PHPStan configurado; la comprobación estática ejecutada es sintáctica y se complementó con revisión de código, restricciones de BD y pruebas negativas.

## Recorrido funcional real

- PASS: ADMIN selecciona profesional, edita ambos importes, guarda y recarga; revisión persiste.
- PASS: ADMISION y COMERCIAL no administran precios ni alteran catálogo por HTTP.
- PASS: Agenda cotiza el precio editado junto al servicio sin abrir Registrar adelanto ni recargo.
- PASS: Cambiar servicio usa importe institucional compartido y cambiar profesional usa precio particular.
- PASS: ADMIN edita precio institucional desde el catálogo; Agenda obtiene nuevo precio y mantiene autorizaciones.
- PASS: Reevaluación visible exige indicación acreditada; seleccionar modalidad sola no crea ni cotiza precio reducido.
- PASS: MFR ofrece solo servicios habilitados y rechaza la asignación traumatológica heredada sin borrarla.
- PASS: ADMIN comparte Informe Médico con MFR y lo asigna desde UI sin duplicar servicio ni introducir un precio médico.
- PASS: Reserva con S/30 y confirmación explícita conservan un solo pago y saldo S/135.25.
- PASS: Móvil verificado 390px conserva edición de precios y Registro rápido legible.
- PASS: Dos administradores y procesos HTTP: una edición vence, la otra detecta versión obsoleta y conserva la cita vendida.
- PASS: Dos altas concurrentes crean una sola asignación profesional para el documento compartido.

Se editaron consulta/reconsulta a 165.25/125.50 desde el modal ADMIN, se guardaron, recargaron y volvieron a consultar por HTTP. Agenda mostró 165.25 con el panel de pago cerrado. El precio institucional editado pasó de 280.50 a 295.75 para los profesionales autorizados. Son importes **exclusivamente ficticios de QA**, no propuestas de precios productivos.

La reserva se guardó sin dinero. OCR ejecutado en WASM/worker local sobre una imagen Yape ficticia propuso `30.00` y `0007733001`, conservando ceros iniciales. “Usar sugerencias” trasladó los datos y la BD aún tenía cero pagos. El guardado explícito registró un solo pago de 30, un comprobante y un archivo privado vinculado. La confirmación separada conservó precio 165.25 y saldo 135.25. La cita histórica de 137 no cambió, incluso después de las ediciones y carreras.

Dos administradores, sesiones y procesos HTTP produjeron `[200,422]` al editar la misma versión y `[200,422]` al intentar asignar simultáneamente el mismo servicio. Persistieron una sola actualización válida y una sola asignación. No se usaron respuestas simuladas para esas carreras.

La UI móvil guardó y recargó realmente el precio; el botón de guardar quedó dentro del viewport. Se verificaron geometría y ausencia de desbordamiento del documento de Agenda, además de inspeccionar las capturas.

## Cobertura de backend y regresión

Incluye maestros editables, institucional/profesional, fallback permitido, tarifas opcionales y explícitas inválidas, precios ausentes/cero/vigencia, múltiples profesionales/servicios, duplicados, versiones obsoletas, protección de instantáneas y Ventas, 403 de precios, CSRF del navegador, reserva y confirmación con pago positivo, descuentos, exoneraciones y pruebas existentes de Agenda/cita extendida/OCR/pagos/documentos/privacidad comercial.

Las reevaluaciones comerciales se probaron en entrada operativa y heredada, ambos servicios, referidos/no referidos y días 0, 1, 15 y 16. Se verificaron indicación acreditada, cita canónica, referencia comercial separada, no reutilización y conservación del antecedente. La suite también incluye ausencia de indicación, pendientes, servicio equivocado, médico distinto y solicitudes manipuladas.

Doctor 9/MFR con asignación heredada a servicio 1 permanece en BD y no aparece como opción agendable. Un servicio MFR válido sí aparece. Compartir un documento no lo asigna a profesionales; la autorización individual y las excepciones entre especialidades se comprueban en servidor y quedan auditadas.

Las migraciones se ensayaron sobre un esquema anterior con filas ficticias: se conservaron IDs, atributos, importes y relaciones. Las FK rechazaron referencias inexistentes y los down protegieron información nueva. No se modificaron pacientes, usuarios, roles o permisos productivos.

## Fallos encontrados y corregidos durante QA

- Base intacta c86ffa6e: 943 pruebas PHP con 39 fallos; dos pruebas JS también conservaban expectativas anteriores. Predominaban aserciones del mínimo del 50 % ya sustituido por PR #43 y una prohibición de leer `window.location` que confundía lectura de URL con navegación. Se actualizaron los escenarios conservando controles de pago cero, permisos, dinero, saldo, duplicados, privacidad e idempotencia; los nuevos casos verifican confirmación con 30.
- La nueva interfaz exigió `catalog_version` para desactivar; se adaptó la regresión heredada al contrato real sin cambiar su comprobación de conservación del histórico.
- Primera ejecución MariaDB: las pruebas DDL confirmaban implícitamente sus transacciones y contaminaban los siguientes fixtures. Se aisló el esquema tras DDL, se corrigieron IDs de prueba que asumían AUTO_INCREMENT=1, se mantuvieron los fakes de URL coherentes y las inyecciones de deadlock se ejecutaron en la transacción exterior, como en HTTP. La suite final completa quedó verde sin omitir casos.
- Se corrigió un fixture de cambio de médico que creaba otra especialidad por accidente; ahora prueba otro médico compatible, conservando el rechazo al referido no autorizado.
- Navegador: se corrigieron selectores del ensayo y una captura de recibo en segundo plano. Los errores se reprodujeron y el recorrido completo volvió a ejecutarse; no se contaron intentos fallidos como aprobados.
- Código: cotización sin tarifa neutral, edición efectiva del maestro, validación de especialidad, fuente institucional, vigencia por fecha de atención, consultas de precio obsoletas, preparación del adelanto y guardado móvil. No se cambió la política financiera aprobada.

Se reprodujo una carrera con dos conexiones reales a MariaDB: una lectura consistente conservó la especialidad anterior mientras otra conexión la modificaba. La lectura bloqueante rechazó la autorización obsoleta (error nativo 1020 en este entorno). Se verificaron además ambas cotizaciones HTTP con respuesta 409 controlada, sin detalles SQL ni escrituras de dinero. El frontend debe actualizar los datos y conservar la misma solicitud idempotente al reintentar.

## Reproducción

Requisitos locales: checkout separado sin `.env`, dependencias de `composer.lock`, Node, Chrome/Playwright del runtime de QA, y MariaDB 11.8.9 aislada con directorio `.worktrees/.rc-suite-mariadb-1189/data` y puerto 33323. Crear previamente solo las bases/usuarios ficticios que verifica `IsolatedCatalogQa`; nunca usar credenciales ni nombres productivos. Las pruebas de migración reinicializan únicamente su base QA.

```powershell
$php = (Resolve-Path '../entrega-urgente-1/storage/app/qa-final/equivalent/php-8.3.35/php.exe').Path
& $php vendor/bin/phpunit
& $php vendor/bin/phpunit -c phpunit.catalog-mariadb.xml
node --test tests/JavaScript/*.test.js
& ./tools/qa/run-catalog-browser.ps1 -ResetSyntheticData
```

El runner rechaza puertos ocupados y solo detiene procesos que él inicia. Los datos, contraseñas sintéticas, cookies, capturas y volcados se guardan bajo `storage/app/qa-catalog`, ignorado por Git. El repositorio público recibe únicamente código, matriz sin identificadores profesionales sensibles y resúmenes sanitizados. No se adjunta el Excel ni se publican los logs productivos.

`catalog-restore-rehearsal.php` comprueba el bootstrap aislado, exige `CATALOG_QA_RESTORE=NEW_SYNTHETIC_DATABASE` y crea/verifica solo su destino local. El dump sintético se generó con `mariadb-dump --single-transaction --skip-add-drop-table`, sin `USE` de la fuente, se importó al destino nuevo y se comparó el contenido íntegro y la copia privada. No es un script de restauración productiva.

## Evidencia y límites

`evidencias/QA_RESUMEN.json` conserva versiones, resultados, casos, OCR y concurrencia; `CODIGO_VERIFICADO.json` identifica cada blob del commit probado; `EVIDENCIA_HASHES.json` permite comprobar los logs/JUnit/capturas conservados localmente. Los archivos de resultados locales se guardan sin exponerlos públicamente. Los cinco archivos pendientes de la rama anterior conservaron sus SHA-256.

Las pruebas corresponden al ambiente aislado. No se publicó ni ejecutó nada en Hostinger y no existe staging. La restauración sintética no acredita el backup productivo. Siguen pendientes aprobación de datos, precios MFR/TF y demás importes/asignaciones, revisión de referencias de 8/10, reconciliación de Keylinn y privacidad/admisibilidad de Clarity. Recomendación: **candidato funcional para revisión; NO-GO productivo hasta resolver esos requisitos y autorizar la liberación**.
