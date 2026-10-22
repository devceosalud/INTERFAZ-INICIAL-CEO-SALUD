# Resultados de la candidata integrada — 21/10/2026

Datos exclusivamente ficticios. No se usaron `.env`, pacientes, pagos, archivos privados ni conexiones de producción. Las capturas corresponden al servidor local de pruebas con autenticación y CSRF reales; no son maquetas.

| Ejecución final | Resultado | Evidencia |
|---|---|---|
| Suite PHP completa, SQLite en memoria, PHP 8.3.35 / PHPUnit 9.6.34 | 1188 pruebas, 10522 aserciones; sin fallos | `evidence/php-sqlite.txt`, `.xml` |
| Selección PHP, MariaDB 11.8.9 | 102 pruebas, 921 aserciones; sin fallos | `evidence/php-mariadb.txt`, `.xml` |
| Concurrencia, sesiones independientes MariaDB | 6 pruebas, 52 aserciones; sin fallos | `evidence/mariadb-concurrency.txt`, `.xml` |
| Suite JavaScript, Node 26.3.0 | 344 pruebas; todas pasan, ninguna omitida | `evidence/javascript.txt` |
| Navegador económico, Chrome 155.0.8059.79 / Playwright | 7 escenarios, cero errores JS, 11 PNG | `evidence/economy/economic-browser-results.json` |
| Regresión visual original, mismo Chrome | 6 escenarios, cero errores JS, 10 PNG | `evidence/reschedule/browser-results.json` |

Los 28 casos económicos PHP cubren mismo pago50→60, referencia/duplicado, descuento fijo/porcentual/remoción, recargo fijo20 y porcentual10 sin asumir un límite20, tarifa y catálogo intacto, cancelación sin escritura, permisos separados y roles, UUID/reintento/colisión, usuario y firma de revisión, cambios concurrentes/vencimiento/revocación, auditoría mínima y hash final, saldo y exceso, turno cerrado, fiscal/derivados, anulaciones, créditos/devoluciones, varias líneas y conciliación, múltiples pagos individuales, nuevos pagos posteriores y reserva impaga sin confirmación. Se prueba también la migración idempotente y la asignación desde Roles. No se añade circuito de autoaprobación: el MVP permite edición directa por el usuario habilitado.

Las seis carreras MariaDB emplean procesos PHP y conexiones independientes con barreras controladas: mismo UUID no duplica; propuestas distintas solo aplican una; cierre gana y bloquea edición; edición gana y cierre congela el total correcto; carrito lee antes de una corrección de pago; carrito lee antes de un descuento. Las últimas dos ejecutan la guardia real `Sales::lockAppointmentSale` bajo una transacción y comprueban rechazo422 del carrito obsoleto, incluido el error de instantánea1020 de MariaDB. No simulan una emisión SUNAT completa ni realizan emisión real. Las pruebas habituales de ventas cubren el comportamiento ordinario de Caja.

La selección MariaDB utiliza este filtro exacto sobre las suites existentes más la nueva:

```text
php vendor/bin/phpunit -c phpunit.agenda-v2-mariadb.xml --filter "AppointmentEconomicEditTest|RescheduleVisualHistoryTest|AdmissionBoardTest|AppointmentQuickManagementTest|SchedulingMvpFoundationTest|MvpGuardNormalizationTest|DoctorServicePricingTest|BankPaymentSchemaTest|BankPaymentIntegrityTest|AppointmentSalesPrivacyTest|CashierSalesAndPaymentsSmokeTest" --log-junit storage/app/qa-v2/direct-regression.xml
php vendor/bin/phpunit -c phpunit.agenda-v2-mariadb.xml tests/Integration/MvpEconomyDirect --log-junit storage/app/qa-v2/direct-concurrency.xml
```

El término `AppointmentQuickManagementTest` del filtro no corresponde a una clase existente; no se cuenta como ejecutada. `AgendaQuickManagementTest` sí está en la suite completa SQLite. Los XML permiten comprobar exactamente las clases ejecutadas. No se ejecutó la suite completa de 1188 contra MariaDB.

La instancia MariaDB es desechable, fuera del repositorio, puerto loopback33331, DB `ceosalud_qa_reschedule_suite`, datadir exclusivo `storage/app/qa-v2/mariadb` de la exportación QA. Se adaptó solo en esa exportación el bootstrap existente `IsolatedAgendaV2Qa` (puerto/nombres/credenciales ficticias) porque el puerto original33324 pertenece a otro entorno que se preservó. Se mantuvieron sus guardias de versión, identidad/datadir y rechazo de `.env`. La aplicación de la exportación se contrasta con los fingerprints de esta candidata. Las migraciones, incluido el permiso nuevo, solo se ejecutaron allí y en SQLite.

## Navegador y capturas

La ejecución económica parte de una cohorte nueva sin permisos económicos. El administrador los asigna a Comercial desde Roles. El propio Comercial revisa/confirma la corrección, cancela primero sin persistir, realiza A/B/C, retira el descuento operando desde móvil390px, corrige referencia y tarifa, y consulta seis eventos de auditoría sin crear nuevos pagos. Después reprograma y verifica economía y marca histórica intactas. Se inspeccionaron visualmente comparación y controles móviles.

La regresión original no se modificó: 12:20→24/10 12:00, marca histórica y horario liberado; Admisión sin cola ni importes duplicados; semana/mes con DOM histórico separado; nueva cita confirmada en el origen con su pago legítimo; segunda reprogramación y navegación al destino vigente. Usa otra cohorte limpia. Las 21 capturas identifican profesionales/usuarios/dinero ficticios.

Reproducir en checkout de pruebas sin `.env`, dependencias del lock y PHP compatible:

```text
php vendor/bin/phpunit --log-junit storage/app/direct-full.xml
node --test tests/JavaScript/*.test.js
php tests/Support/mvp-economic-browser-seed.php
php -S 127.0.0.1:8099 -t public tests/Support/reschedule-visual-browser-router.php
node tools/qa/browser-mvp-economic.cjs
```

Para la regresión visual, usar otro checkout limpio y el seeder/script originales `reschedule-visual-browser-seed.php` y `browser-reschedule-visual.cjs`. Ambos seeders rechazan una DB previa. La contraseña sintética generada queda en storage ignorado y NO se entrega. Los scripts referencian la instalación local de Playwright/Chrome; adaptar sus rutas en el entorno revisor. No ejecutar herramientas QA en el ERP instalado.

## Comprobaciones adicionales y límites

`lint.json`, `preservacion-visual.json` y `preflight-*.json` registran sintaxis, comparación de la release visual y fingerprints. Se comprobaron 23 archivos PHP/JS y 541 fingerprints tanto en la candidata como en su exportación MariaDB y en las dos bases de referencia. Los 47 archivos de la release visual se mantienen (el archivo compartido de rutas solo añade seis líneas económicas). `git diff --check` sin errores. No cambian Composer, cotizador, formulario de nueva cita, registro de pagos, reglas clínicas, disponibilidad o reportes. Las pruebas completas verifican sus regresiones; los diffs permiten comprobar el alcance.

No se probaron Safari/Firefox, dispositivos físicos, carga sostenida productiva, datos históricos reales ni archivos/esquema de Hostinger. Los escenarios financieros no conciliables permanecen bloqueados. Las evidencias de la release visual anterior siguen en su directorio original; para esta integración son autoritativas las ejecuciones y documentos de este directorio.
