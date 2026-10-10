# Correcciones solicitadas en PR #3 — 10/10/2026

Referencia: [comentario técnico más reciente](https://github.com/devceosalud/INTERFAZ-INICIAL-CEO-SALUD/pull/3#issuecomment-6099022144), sobre b83cf57. Rama exclusiva: codex/auditoria-agenda-adelantos-documentos. No se modifican main, Hostinger, permisos productivos ni datos reales. El PR continúa Draft, sin merge ni despliegue.

## Comportamiento corregido

- ReservationPaymentService propaga el conflicto de assertUnoccupied en vez de convertir silenciosamente REGULAR en ADICIONAL. El controlador devuelve HTTP 409. La transacción revierte todo cobro nuevo, ticket, correlativo, operación y cambios económicos de ese intento; conserva la reserva y cualquier pago anterior. Incluso disponer de appointment.additional.create no implica intención de conversión. El flujo explícito de creación ADICIONAL conserva sus permisos y validaciones actuales; este endpoint no introduce una operación de conversión.
- Omitir confirm o enviarlo falso registra únicamente el adelanto. Confirmar requiere confirm verdadero explícito después de validar el booleano en Laravel. El controlador normaliza únicamente las representaciones aceptadas por esa regla (true/1/"1" y false/0/"0"); valores ambiguos como "true" se rechazan con 422. El servicio comprueba === true y utiliza false como default. Se mantienen CREATE, SUBMIT_PAYMENT, caja válida, umbral del 50% y exoneración autorizada.
- No cambian schema, recorder financiero, UUID ni reglas de duplicados bancarios. El hash de reintentos sigue protegiendo el resultado original; confirmación y adelanto son operaciones distintas y deben llevar UUID independientes.

## Consumidores

Búsqueda en app, resources, public/js y tests con referencias a la ruta y /payments: el único cliente web activo es agenda-operational-workspace.js (submitPayment). Registrar adelanto envía confirm false y Confirmar cita envía confirm true con importe cero. Se revisaron las rutas, controlador y todas las pruebas que llaman el servicio/endpoint. No se encontró otro consumidor de aplicación ni llamada directa al servicio fuera del controlador. Los clientes externos al repositorio quedan fuera de esta comprobación: deben enviar intención explícita de confirmación.

La prueba anterior que esperaba dos reservas convertidas automáticamente a REGULAR/ADICIONAL fue corregida: ahora la segunda devuelve 409, mantiene sus datos y pagos anteriores, aunque el actor disponga del permiso ADICIONAL.

## Pruebas y evidencia nueva

| Comprobación | Resultado |
|---|---|
| PHP completa, SQLite en memoria | 593 tests, 3.464 aserciones; 592 aprobados, 1 omitido preexistente, cero fallos/errores. |
| MariaDB 10.4.32 / InnoDB, instancia QA propia | 89 tests, 715 aserciones; todos aprobados, sin omisiones. Incluye 8 pruebas de concurrencia, 30 de Agenda y regresiones de Pacientes/Horarios/Usuarios. |
| JavaScript completo | 192 tests aprobados, cero fallos/omitidos. |
| PHP específica de AgendaStabilization/OperationalRegistration | 47 tests, 509 aserciones, todos aprobados. |
| Sintaxis PHP/JS y git diff --check | Aprobados. |
| Navegador local, operadora ficticia con CREATE/SUBMIT_PAYMENT sin CREATE_ADDITIONAL | Reserva de S/100 financiada con S/50 sobre intervalo ocupado: mensaje de conflicto, permanece reserva REGULAR. Snapshot SQL de 8 tablas idéntico después del rechazo. Reserva libre: Registrar adelanto S/50 mantiene pendiente; confirmar explícitamente cambia a CONFIRMADA/REGULAR sin otro pago. |

Nuevas pruebas HTTP cubren confirm omitido, false, 0 y "0" con S/50 y reintento idéntico; true, 1 y "1" explícitos; rechazo de "true"; colisión con/sin permiso ADICIONAL y con pago previo S/0 o S/25. Comparan filas completas de appointments, payments, vouchers, voucher_items, voucher_series, appointment_operations, appointment_events, appointment_documents y cashier_shifts después de dos rechazos. Luego un adelanto deliberado sin confirm se cobra una sola vez, mantiene pendiente y no altera la ocupación.

Con procesos HTTP independientes sobre MariaDB, dos confirmaciones simultáneas con la misma UUID sobre una reserva ocupada devuelven 409/409 y conservan las tablas. Dos adelantos simultáneos sin confirm devuelven 200/200, crean un solo pago y mantienen pendiente. Se repiten los casos de claves/payloads distintos, identidad bancaria duplicada, competencia por intervalo y falta de permisos. JavaScript verifica confirm false en adelantos y que un conflicto no altera selección/tipo ni introduce otra petición automática; el reintento de confirmación conserva UUID y dinero cero.

El smoke visual usa únicamente fixtures del helper IsolatedAgendaQa en 127.0.0.1:33317, schema ceosalud_qa_entrega1 y datadir del worktree. La reserva en colisión (08:00) tenía S/50 y otra cita LEGADO ocupaba el intervalo; el rechazo no cambió ninguna de las 8 tablas comparadas. La segunda (08:30) recibió S/50 y se comprobó pendiente antes de confirmar. Verificación SQL final: dos pagos que suman S/100, dos tickets, cinco operaciones; la reserva en colisión sigue REGULAR/pendiente y la libre queda REGULAR/confirmada. El LEGADO solo es un fixture de ocupación, sin datos reales.

Evidencia local ignorada en storage/app/qa-final: php-review-tests.txt/php-review-results.xml, mariadb-review-tests.txt/mariadb-review-results.xml, javascript-review-tests.txt, review-browser-conflict-db.json, review-browser-pending-db.json, review-browser-confirmed-db.json, review-browser-*.jpg y scripts/fixtures de smoke. La configuración y casos HTTP/concurrencia están versionados.

## Repetición y límites

La preparación de la instancia aislada y los comandos completos permanecen en VALIDACION_FINAL_ENTREGA_1.md. Con esa instancia propia activa, sin .env y después de verificar el datadir:

```powershell
php vendor/phpunit/phpunit/phpunit
php vendor/phpunit/phpunit/phpunit -c phpunit.agenda-mariadb.xml
$qaJsTests = @(rg --files tests/JavaScript -g '*.test.js')
node --test @qaJsTests
```

Para smoke: preparar dos reservas ficticias REGULAR de S/100, una financiada S/50 con otra cita ocupando el mismo horario y una libre sin pago. Ingresar con una operadora ficticia CREATE/SUBMIT_PAYMENT y caja QA válida. Intentar confirmar la primera: mensaje de conflicto y ningún cambio SQL. Registrar S/50 en la segunda: debe seguir pendiente; confirmar después: ocupa horario sin otro pago. No usar datos o conexiones productivos.

Se mantiene la omisión de AppointmentAgendaSchemaTest por DROP COLUMN del SQLite instalado anterior a 3.35; no se declara aprobado. No se ejecutaron MySQL 8, Hostinger, carga sostenida ni matriz de navegadores. El smoke usa APP_ENV testing y no certifica CSRF productivo. Se observaron nuevamente los TypeError preexistentes de selectpicker en custom.min.js; no bloquearon los flujos y esos assets no cambian.

No se incorporó OCR, carga múltiple ni migraciones. Los servidores QA se detienen al finalizar. Reversión del nuevo commit: git revert en una rama revisable, sin eliminar pagos/citas. Esa reversión reintroduciría los dos bloqueos de revisión corregidos; evaluarlo antes de publicar. La publicación autorizada solo actualiza la rama del PR, no producción.
