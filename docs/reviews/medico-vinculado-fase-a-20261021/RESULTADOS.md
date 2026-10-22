# Resultados verificables de regresión local

Base ANTES: `a8bd76d8e10090decfde56c7402be585da723fcf`. DESPUÉS: rama local `codex/medico-vinculado-fase-a-20261021`. No se ejecutaron migraciones productivas ni se consultaron datos reales.

| Ejecución | Pruebas | Aserciones | Resultado |
| --- | ---: | ---: | --- |
| Base a8, suite completa SQLite |1200|10649|PASS |
| Base a8, caracterización adicional de CRUD médico sin vínculo |1|15|PASS |
| Incremento final, suite completa SQLite |1236|10983|PASS |
| Incremento final, suite completa MariaDB11.8.9 con integración |1257|11183|PASS |
| JavaScript, todos los archivos de tests/JavaScript |353|—|353 PASS,0 fallos,0 omitidas |
| Chrome155/Playwright, recorrido médico autenticado |11 escenarios agrupados|—|PASS,0 errores de página |
| Chrome155/Playwright, regresión visual original de reprogramación |6 escenarios agrupados|—|PASS |
| Lint PHP y sintaxis Node de archivos modificados/nuevos |40 archivos|—|PASS |
| git diff --check |—|—|PASS |

Duración de las últimas suites PHP: SQLite03:33.371; MariaDB05:03.520. Ninguna prueba fallida ni omitida en las suites finales. Los XML/textos exactos están en evidencias/. Se preservan los1200 identificadores de casos anteriores; no se removieron para obtener el resultado. La matriz por módulo y sus clases se detallan en MATRIZ_ANTES_DESPUES.md; los grupos se solapan.

## Cobertura nueva que complementa la regresión anterior

- UserDoctorLinkTest: vínculo explícito de cuenta existente; motivo/confirmaciones/versión; no duplicar usuarios ni roles; unicidad; reasignación y retirada; autoasignación403; inactivos; doctor_id prohibido en editor genérico; definición de rol sin asignar cuentas; guard ambiguo; migración up/down/unique/FK/restrict sobre datos heredados ficticios con usuarios, médicos y citas no vacíos.
- MedicalAccessCompatibilityTest: reserva con y sin cuenta médica, pago real y ocupación exclusiva; cambios de rol/epoch únicamente en acceso médico afectado; conservación de roles/permisos directos anteriores; límites del editor de MEDICO; bootstrap sin permisos generales para MEDICO; activación/desactivación; login/destinos; doctor_id no agrega campos a JSON genérico.
- DoctorCatalogIdentityCompatibilityTest: creación/edición/desactivación por endpoints vigentes, con y sin vínculo, preservando IDs, número de usuarios y todos los atributos de una cita histórica. El caso sin vínculo también corre antes sobre a8.
- VerifiedMedicalCareTest: Admisión llega pero no inicia/finaliza, médico propietario inicia/indica/retira/finaliza; color/estado/economía invariables al indicar; permiso faltante/otro médico/sin vínculo/inactivos403; rutas heredadas siguen sin bypass; listo manual no equivale a automático; procedimientos pendientes no eluden envío parcial; no retirar procedimientos completados; doble envío/revisión obsoleta; vínculo cambiado bloquea actor anterior.
- MedicalIdentityConcurrencyTest: seis carreras reales entre dos procesos MariaDB: revocación primero, reasignación primero, finalización primero seguida de revocación, UUID igual, UUID distintas y dos cuentas por un médico. Se comprueban actor, instante, conflictos, un solo evento/resultado y ausencia de salida ficticia.
- medical-actions.test.js: nueve pruebas nuevas ejecutan los scripts reales: confirmación cancelada, UUID estable tras resultado incierto, doble clic, botones deshabilitados durante recarga posterior, acciones del backend, selección obsoleta, errores internos no expuestos y confirmación/reintento de vínculo.

## Navegador y base de datos

Se usa Laravel real sobre SQLite exclusivamente local, con sesiones, login, CSRF, políticas y endpoints reales. El script crea cinco cuentas ficticias; la cuenta reutilizada comienza sin vínculo ni rol médico. Un administrador la selecciona, atestigua identidad y confirma, y luego asigna el rol en el editor actual. La prueba no infiere identidad por coincidencias.

Se comprueban: llegada de Admisión, inicio médico propietario, cancelación sin escritura, indicación/retirada con eventos, listo manual sin procedimientos en EN_ATENCION, selección médica propia, filtros pendiente/listo independientes, dos procedimientos realizados por Admisión, activación automática lista, finalización sin salida y revocación que invalida únicamente al médico. Escritorio1440px y móvil390px, sin desbordamiento horizontal. Las16 capturas médicas y10 de reprogramación son ficticias y se revisó visualmente el formulario de vínculo, pendiente en móvil y Admisión en escritorio. No basta con que el DOM contenga texto: se corrigió el selector duplicado del formulario nuevo y se esperó la transición real del shell para capturar imágenes legibles.

`medico/database-invariants.json` confirma5 usuarios iniciales/finales,1 cita,1 pago real S/60, precio S/120, saldo S/60, ATENDIDO y salida nula. Cinco eventos médicos conservan actor médico y dos procedimientos conservan solicitante médico/verificador de Admisión. El reloj fijo de QA produce iguales timestamps en algunas transiciones; la secuencia se verifica por IDs/eventos, no implica reescritura de horas.

Los seis escenarios de reprogramación recorren origen12:20, traslado, referencia de Admisión sin doble cola/KPI/ingresos, semana/mes separados de eventos ocupantes, nueva reserva en el mismo origen y segundo traslado con navegación al destino vigente.

## Entornos, identidad de fuentes y reproducción

PHP8.3.35, Laravel9.52.21, Spatie6.25, PHPUnit9.6.34; Node26.3.0; MariaDB11.8.9; Chrome155.0.8059.79. Dependencias existentes, sin cambios de Composer/npm.

SQLite: desde un checkout aislado sin .env, `php vendor/bin/phpunit --log-junit storage/app/medical-final-sqlite.xml` con el phpunit.xml existente que fuerza testing/memoria y servicios falsos. JavaScript: `node --test tests/JavaScript/*.test.js`. MariaDB: `php vendor/bin/phpunit -c phpunit.agenda-v2-mariadb.xml --log-junit storage/app/medical-final-mariadb.xml`, exclusivamente sobre el servicio descartable cuya identidad acepta IsolatedAgendaV2Qa. No ejecutar contra un .env ni una conexión arbitraria.

Para el servicio local propio33331 se usó una copia aislada fuera del checkout, sin .env, adaptando únicamente dos archivos privados de QA: IsolatedAgendaV2Qa.php (puerto/nombre de esquema/propietario esperado y verificación estricta del datadir) y ApplicationAndAuthenticationSmokeTest.php (expectativa del nombre/puerto de ese servicio). El código de estos dos archivos en la rama no se modificó ni se debilitó para aceptar conexiones arbitrarias. Los821 hashes de fuentes coinciden con la rama probada salvo esas dos adaptaciones identificadas; no hay archivos extra en el descubrimiento de tests. Fuente invariable entre suites finales. Los nombres/contraseñas ficticios del servicio y los adaptadores privados no se adjuntan.

Para navegador: checkout aislado sin .env, ejecutar medical-browser-seed.php (rechaza una base preexistente), servir el router QA reschedule-visual-browser-router.php en loopback8099 y ejecutar tools/qa/browser-verified-medical-care.cjs con MEDICAL_QA_PHP apuntando al PHP local compatible. Las credenciales aleatorias quedan únicamente en storage ignorado; no se exportan. El ensayo visual original usa browser-reschedule-visual.cjs y su seed existente. No reutilizar esta base como producción.

Las primeras pasadas detectaron expectativas antiguas de catálogo de roles en tres tests y el riesgo de conceder permisos ordinarios a MEDICO por el bootstrap general. Se ajustaron únicamente las expectativas al rol aprobado y se corrigió ese bootstrap para el rol nuevo, manteniendo los casos anteriores. También se corrigieron la carga del shell en las vistas nuevas, el doble clic durante recarga y el selector nuevo. Los intentos iniciales del harness con selectores erróneos y la expectativa privada del puerto/schema no constituyen resultados PASS; se conservan ignorados y los informes adjuntos corresponden a la repetición final completa.

## Límites y seguridad

Revisión de artefactos: sin .env, tokens, claves privadas, bases, sesiones, vendor/node_modules ni credenciales del navegador. Patrones de secretos de alta confianza y revisión de nombres ficticios; las claves literales de pruebas son únicamente valores de testing ya definidos en sus entornos. Ningún paciente real fue usado.

No se ejecutó CI para el nuevo SHA local ni pruebas en Hostinger. El PR57 continúa draft sobre a8; su CI anterior no se presenta como CI de este incremento. No se hicieron pruebas manuales con usuarios reales. La aceptación clínica en staging y el preflight del esquema/archivos productivos siguen pendientes antes de autorizar instalación. Puntos4/5 no desarrollados.
