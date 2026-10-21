# Resultados verificables — 21/10/2026

Datos ficticios, entornos locales aislados. No se accedió a producción. Base: `1f840e90912d2ce8c55ce7502a22a1d582492bc3`.

| Comprobación | Resultado final | Evidencia |
|---|---|---|
| PHP completa, SQLite en memoria | 1.160 pruebas, 10.327 aserciones, sin fallos | `evidence/php-sqlite.txt`, `.xml` |
| PHP seleccionada, MariaDB 11.8.9 | 52 pruebas, 593 aserciones, sin fallos | `evidence/php-mariadb.txt`, `.xml` |
| JavaScript completa | 339 pruebas, sin fallos ni omitidas | `evidence/javascript.txt` |
| Navegador real Chrome | 6 escenarios, cero errores JavaScript | `evidence/browser-results.json` y 10 PNG |
| Sintaxis | 13 archivos PHP/vistas/rutas y 6 JS, correcta | `evidence/lint.json` |
| Integridad BASE | 535/535 coincidencias | `evidence/preflight-base.json` |
| Integridad CANDIDATO | 535/535 coincidencias | `evidence/preflight-candidato.json` |
| Diferencias deliberadas frente a BASE | Detecta exactamente los 12 archivos de ejecución | `evidence/preflight-drift-detectado.json` |
| Integridad de aplicación en exportación QA MariaDB | 535/535 coincidencias con candidato | `evidence/preflight-maria-integrity.json` |
| Esquema/dependencias/permisos | Diff vacío; no tabla económica nueva | Diff y prueba específica |

La batería nueva tiene nueve pruebas HTTP de backend: traslado de 12:20 a otra fecha y liberación; nueva cita confirmada en ese intervalo conservando historial; dinero original intacto; múltiples reprogramaciones, reintento y revisión obsoleta; cita extendida y horario original modificado; filtros de origen/paciente y minimización de datos; reserva privada ajena/capacidad revocada/cuenta inactiva; paginación separada con más de 200 eventos; lectura sin escrituras ni cambios de esquema; comparación idéntica de ocupación/capacidad/colores e importes con y sin historia; límite de lectura y continuación para filtros calculados. Algunas comprobaciones comparten un caso de prueba.

La selección MariaDB incluye `RescheduleVisualHistoryTest`, `AgendaQuickManagementTest`, `AdmissionBoardTest`, `AgendaDoubleDurationTest` y `PilotAppointmentOperationsTest`. Se ejecutó sobre el mismo esquema anterior, con migraciones originales únicamente en la instancia ficticia. No se ejecutó la suite PHP completa contra MariaDB, ni las pruebas específicas de carreras de Caja de la rama económica, que no pertenece a esta candidata.

Los seis tests JavaScript nuevos verifican intervalos extendidos, eventos antiguos sin duración, referencia sin contexto activo y bloqueo de propagación, disponibilidad real del intervalo reutilizado, invariancia de la grilla y distintivos DOM reemplazables de semana/mes. El resto de los 339 cubre regresiones ya existentes.

Los seis escenarios de navegador verifican: cita inicial con pago ficticio de S/60; reprogramación mediante endpoint real autenticado/CSRF; marca de origen libre y vista móvil; Admisión sin filas/KPI/ingresos duplicados y navegación al destino; semana/mes sin eventos históricos de ocupación; nueva cita confirmada en el origen con pago legítimo propio; segunda reprogramación y enlace al destino vigente. Algunas comprobaciones comparten escenario. No se quitaron atributos `disabled` para habilitar acciones. Se inspeccionaron las capturas y se colocó el distintivo vigente en la columna de paciente para evitar partir su texto en la columna estrecha de hora.

## Reproducción local

Usar un checkout aislado sin `.env`, dependencias del lock y PHP compatible. La guardia de `phpunit.xml` exige SQLite `:memory:`, servicios externos ficticios y configuración de testing:

```text
php vendor/bin/phpunit --log-junit storage/app/qa-reschedule-full.xml
node --test tests/JavaScript/*.test.js
php vendor/bin/phpunit --filter RescheduleVisualHistoryTest
```

Para navegador, en un checkout de pruebas nuevo sin `.env`:

```text
php tests/Support/reschedule-visual-browser-seed.php
php -S 127.0.0.1:8099 -t public tests/Support/reschedule-visual-browser-router.php
node tools/qa/browser-reschedule-visual.cjs
```

El seeder rechaza una base previa, crea exclusivamente `storage/app/qa-reschedule-visual/browser.sqlite` y genera una credencial aleatoria local, que no se entrega. El navegador debe usar Playwright y Chrome; el script resuelve los binarios disponibles en esta máquina, que deben adaptarse al entorno independiente. Las solicitudes externas están bloqueadas, la fecha ficticia es 21/10/2026 y se mantienen autenticación y CSRF reales.

La instancia MariaDB se creó fuera del repositorio, en una exportación QA, usando binarios locales 11.8.9, loopback `127.0.0.1:33331`, base exclusiva `ceosalud_qa_reschedule_suite` y datadir exclusivo. El puerto original del harness estaba ocupado por otra instancia y se conservó intacta. Se adaptó únicamente el bootstrap de pruebas existente `IsolatedAgendaV2Qa` en esa exportación: puerto, nombre de base/usuario y credenciales ficticias; se mantuvieron las verificaciones estrictas de versión, datadir, ausencia de `.env` y servicios ficticios. No se cambió ese bootstrap en la rama de release. La aplicación de la exportación coincide con los 535 fingerprints del candidato.

Comando de selección usado allí:

```text
php vendor/bin/phpunit -c phpunit.agenda-v2-mariadb.xml --filter "RescheduleVisualHistoryTest|AgendaQuickManagementTest|AdmissionBoardTest|AgendaDoubleDurationTest|PilotAppointmentOperationsTest" tests/Feature/Scheduling
```

Versiones ejecutadas: PHP 8.3.35, Laravel 9.52.21 (el lock declara `9.x-dev`), PHPUnit 9.6.34, Spatie Permission 6.25.0, Node 26.3.0, MariaDB 11.8.9 y Chrome 155.0.8059.79. No se cambiaron Composer ni NPM. La primera exportación QA omitió dependencias públicas `vendor`; se completó antes de obtener el resultado MariaDB final. Los registros entregados son las ejecuciones finales aprobadas.

No ejecutado: comprobación de archivos/configuración/schema/vendor/cachés de Hostinger, despliegue, prueba de carga productiva, Safari/iOS ni pruebas con datos reales. Los resultados locales no acreditan funcionamiento productivo.

Los logs entregados reemplazan solo las rutas absolutas de máquina por `<QA_RELEASE_ROOT>` / `<QA_EXPORT_ROOT>`; no se modifican resultados ni aserciones. No se incluyen `.env`, bases, credenciales, sesiones, logs del servidor de pruebas, archivos privados ni dependencias.
