# Baseline técnico verificado — Fase 0

## Alcance y procedencia

- Repositorio: `devceosalud/INTERFAZ-INICIAL-CEO-SALUD`.
- Commit base confirmado: `6552e52521ac59d9c1bf8bc3efd880533a87d11d`.
- Branch local de trabajo: `stabilization/phase-0-safe-environment`.
- Esta fase solo prepara y caracteriza el entorno. No incluye refactor funcional, despliegue, `push`, `merge` ni conexión a producción.

## Arquitectura encontrada

- Laravel 9 / PHP `^8.0`, Blade, Livewire 2 y Eloquent.
- Autenticación web propia sobre el guard de sesión de Laravel.
- Roles y permisos mediante `spatie/laravel-permission`.
- Rutas separadas por admisión, recepción y administración, pero con protección desigual.
- Módulos persistentes para pacientes, responsables, médicos, servicios, horarios, citas, caja, ventas, vouchers, items y pagos.
- Integraciones HTTP para DNI/RUC y SMS; configuración de correo, colas, broadcasting y almacenamiento externo.

## Entorno de pruebas creado

- `.env.testing` sin credenciales reales, con SQLite `:memory:` y servicios externos dirigidos a un endpoint local descartable.
- `phpunit.xml` fuerza `APP_ENV=testing`, SQLite en memoria, cache/sesión en memoria, correo `array`, cola `sync`, broadcasting `null` y variables externas de prueba.
- `tests/CreatesApplication.php` aborta antes del bootstrap salvo que PHPUnit haya fijado exactamente `testing + sqlite + :memory:` y vuelve a comprobar la configuración resuelta después del bootstrap.
- `tests/TestCase.php` instala por defecto `Http::fake`, `Mail::fake`, `Queue::fake` y `Notification::fake` para toda prueba de aplicación.
- PHPUnit usa rutas `bootstrap/cache/testing-*.php`, separadas de cualquier cache local o de despliegue. El primer intento controlado confirmó que existía un `bootstrap/cache/config.php` heredado e ignorado por Git; la guardia abortó antes de migrations al detectar que resolvía otra conexión. No se eliminó ni reutilizó ese cache.
- SQLite PDO está disponible en el PHP local (`pdo_sqlite`). No se ha usado MySQL.

## Reconstrucción de base de datos

### Diferencia investigada antes de corregir

El baseline declara dos veces `hora_llamado` en `2026_06_11_164132_create_appointments_table.php`. `git blame` muestra que la primera definición fue introducida en `cc2941cd` (2026-08-07) y la segunda en `f79780da` (2026-09-08), al añadir el bloque “DATOS PARA EL LLAMADOR DE PACIENTES”. Ambas son `timestamp nullable`; no representan columnas distintas. En Fase 0 se conserva una sola definición dentro del bloque del llamador para recuperar la capacidad de construir el esquema sin alterar su tipo ni su uso esperado.

### Estado de ejecución

Las 27 migrations se ejecutaron desde cero, repetidamente, por `RefreshDatabase` sobre SQLite `:memory:`. La reconstrucción finalizó sin error después de conservar una sola definición de `hora_llamado`.

No se necesitó MySQL en Fase 0. Hay comportamientos que SQLite no puede validar de forma equivalente y que deberán verificarse más adelante en un MySQL aislado, nunca productivo:

- bloqueos y concurrencia real de `lockForUpdate()` al incrementar correlativos;
- semántica y restricciones nativas de columnas `enum`;
- cláusulas específicas como `after()` y diferencias de tipos/precision;
- consultas con funciones o reglas de comparación propias de MySQL.

## Pruebas

### Existentes en el commit base

- Solo los ejemplos predeterminados de Laravel (`tests/Feature/ExampleTest.php` y `tests/Unit/ExampleTest.php`).
- El `phpunit.xml` original dejaba comentada la selección de SQLite, por lo que podía heredar la conexión de `.env`.

### Suite de caracterización de Fase 0

- `ApplicationAndAuthenticationSmokeTest`: configuración aislada, arranque, login válido/inválido, acceso anónimo y páginas principales autenticadas.
- `AccessAndApiSmokeTest`: feed de calendario actualmente público, búsqueda pública de pacientes, interceptación de RENIEC con `Http::fake`, y capacidad actual de un usuario autenticado sin rol para autoasignarse `ADMINISTRADOR` y crear permisos.
- `PatientAndAppointmentSmokeTest`: creación de paciente y creación de cita sin adelanto mediante los contratos HTTP actuales.
- `CashierSalesAndPaymentsSmokeTest`: apertura de turno y venta Livewire, incluyendo voucher, `voucher_items`, pago, stock y avance de correlativo.
- Toda prueba de aplicación hereda fakes de HTTP, correo, colas y notificaciones.

Resultado final: **12 tests, 58 assertions, 0 fallos** con PHPUnit 9.6.34.

## Qué se pudo ejecutar

- Inspecciones estáticas de Git, configuración, rutas, migrations, modelos, controladores, componentes y servicios.
- Verificación de PHP 8.0.30 y `pdo_sqlite`.
- Validación de sintaxis de todos los PHP añadidos/modificados y parseo XML de `phpunit.xml`.
- Ejecución controlada de un smoke de arranque: 1 test, 7 assertions, correcto.
- Ejecución filtrada del flujo caja/venta: 1 test, 13 assertions, correcto.
- Ejecución completa final: 12 tests, 58 assertions, correcta.

## Fallos y pendientes

- Primer arranque controlado: la guardia bloqueó un cache de configuración local heredado antes de ejecutar migrations. Se resolvió aislando todas las rutas de cache de PHPUnit; no se borró ni utilizó el archivo heredado.
- Primera ejecución completa: el helper de Livewire 2 no observó los flash `ok` aunque las operaciones persistieron. Las aserciones se cambiaron a efectos de negocio persistidos y al evento de navegador.
- Ejecución financiera filtrada: SQLite devolvió ciertos enteros sin cast Eloquent como cadenas. Las aserciones normalizan el tipo sin modificar producción.
- Las rutas sin middleware y los controles de autorización faltantes se preservan intencionalmente. Los tests que prueban acceso público y escalamiento de privilegios son caracterización del comportamiento inseguro actual, no aprobación de ese diseño.
- El código contiene material sensible histórico dentro de comentarios de `ReniecService`; no se reproduce aquí y queda pendiente de saneamiento/rotación en una fase de seguridad explícita.
- No se comprobó el envío real a RENIEC/RUC/SMS/correo/SUNAT, por diseño. Esas integraciones requieren sandbox contractual o fixtures representativos antes de probarse.
- No se validó concurrencia de caja/correlativos. SQLite en memoria no sustituye una prueba concurrente sobre MySQL aislado.
- No se ejecutaron pruebas de navegador, impresión física, scheduler/cron ni workers persistentes.
- No se alteró `Sales.php`, controladores grandes, autorización, rutas públicas ni lógica funcional fuera del mínimo de migration necesario para reconstruir el esquema.

## Siguiente paso recomendado (sin ejecutar en Fase 0)

1. Revisar y priorizar los tests de caracterización que evidencian exposición pública y escalamiento de privilegios.
2. Preparar un MySQL local/desechable sin datos reales para pruebas de compatibilidad, bloqueos y correlativos.
3. Crear fixtures contractuales para integraciones externas y facturación electrónica sin tráfico real.
4. Definir las decisiones de negocio sobre autorización, estados de citas/pagos y reglas de caja antes de entrar en Fase 1.
