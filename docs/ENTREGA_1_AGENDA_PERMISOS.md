# Entrega 1: estabilización de Agenda y permisos

> Informe de la entrega inicial local `c9e4349`. La validación ampliada autorizada, la corrección de concurrencia y el estado para PR están en [VALIDACION_FINAL_ENTREGA_1.md](VALIDACION_FINAL_ENTREGA_1.md). Las limitaciones de MySQL y navegador descritas aquí fueron evaluadas en esa fase posterior.

Base: `0d37dee0a4209fccc6326894f46dccd4a2688d10` de `origin/main`.
Rama exclusiva: `codex/auditoria-agenda-adelantos-documentos`.
Checkout: `.worktrees/auditoria-agenda`. El checkout original y sus cinco modificaciones previas se conservan.

## Resultado y alcance

El alta antigua del MVP ahora reutiliza el registro operativo: una nueva cita regular sin pago se guarda como `PENDIENTE_CONFIRMACION`, sin ocupar intervalo. La confirmación requiere al menos 50% de pago económico efectivo, o exoneración autorizada. No se modifican citas históricas ni su ocupación. No hay migraciones nuevas.

La evidencia aportada por el usuario para ID 8 (`appointment.create=false`, `appointment.payment.submit=false`, vista Día) explica ambos bloqueos en la base. No se verificó la identidad de la sesión contra Hostinger. Esta entrega no concede permisos ni cambia su configuración financiera: después de una futura autorización nominativa, los requisitos financieros seguirán siendo obligatorios.

No se accedió a producción, pagos reales, pacientes reales ni historias clínicas. No se hizo despliegue, push, PR o merge. OCR, carga múltiple y cambios al almacenamiento de documentos quedan fuera de esta entrega.

## Reglas conservadas

| Acción | Requisitos y resultado |
|---|---|
| Guardar reserva | Acceso MVP, `appointment.view` y `appointment.create`. Puede guardarse sin dinero. Queda pendiente y no ocupa intervalo. Si se ingresa adelanto, se validan también permiso financiero, turno, serie y dinero real; guardar no confirma. |
| Registrar adelanto | Los anteriores más `appointment.payment.submit`, saldo válido y validaciones de medio/entidad/operación. Registra Payment/Voucher reales. En la UI de una reserva existente envía `confirm=false`. |
| Confirmar cita regular | `appointment.create`, validación de médico/horario y al menos 50% efectivo. Una reserva ya financiada puede confirmarse sin SUBMIT_PAYMENT si no se registra otro importe. No se vuelve a cobrar al confirmar desde la UI. |
| Exonerar | `appointment.zero_cost.approve` y `autorizado_por` explícito. Un rol ADMINISTRADOR, una casilla o texto de autorización solos no conceden la excepción. `appointment.down_payment.override` no reemplaza el pago ni exonera por sí solo. |
| Caja normal | Exactamente un turno manual propio ABIERTO y una serie TICKET activa de esa caja. Turnos ajenos, cerrados, múltiples o series ausentes/ambiguas se rechazan. |

El flag del contexto piloto conserva su comportamiento previo; no se activa para reparar permisos. Los tests lo fuerzan a false salvo casos existentes que lo habilitan explícitamente con fixtures. Adicionales y fuera de horario conservan sus reglas actuales y sus rutas específicas.

## Endpoint antiguo

`POST /scheduling-mvp/agenda/appointments` conserva la forma principal de su respuesta, agrega estado/tipo y `request_key`, y llama a `OperationalRegistrationService`.

- Si faltan `mode` y `booking_type`, asume `RESERVE` y `REGULAR`. Valores presentes inválidos no se corrigen silenciosamente.
- Solo acepta `booking_type=REGULAR`. Las excepciones no se solicitan por este endpoint.
- Un cliente antiguo que guarda sin dinero puede omitir `request_key`; recibe una UUID generada. Para un reintento idéntico debe reutilizar la UUID. La protección existente contra duplicado de paciente/día también permanece.
- Dinero positivo o `mode=CONFIRM` requieren una UUID de cliente antes de escribir. Repetir la misma petición con la misma UUID no duplica cita/pago; cambiar su contenido con la UUID ya usada devuelve 409.
- Campos no validados como `total_pagado` o estado de agenda suministrados por el cliente no constituyen pago ni fuerzan confirmación.
- Pagos, snapshot económico, historia y operación se mantienen dentro de la transacción del flujo existente. Fallos de permisos, 50%, turno o serie hacen rollback.

Los clientes que dependían de crear una cita LEGADO impaga deben adaptarse al contrato de reserva/confirmación. No se conserva ese comportamiento inseguro para altas nuevas.

## Archivos modificados y justificación

Rutas relativas al checkout aislado:

| Archivos | Justificación |
|---|---|
| `app/Http/Controllers/Scheduling/AgendaAppointmentController.php`, nuevo `app/Http/Requests/Scheduling/StoreRegularAgendaAppointmentRequest.php`, `routes/scheduling.php` | Cerrar la ocupación impaga en el endpoint antiguo reutilizando el registro operativo, validar UUID financiera y regular, exigir VIEW/CREATE. |
| `app/Http/Requests/Scheduling/OperationalRegistrationRequest.php`, `app/Http/Controllers/Scheduling/OperationalRegistrationController.php` | Mensaje específico de CREATE y rechazo de `proofs` con `prohibited`, compatible con Laravel 9. Se mantiene el comprobante singular JPG/PNG/PDF. No hay regla `prohibited_with` activa. |
| `app/Services/Scheduling/OperationalRegistrationService.php`, `app/Services/Scheduling/ReservationPaymentService.php` | Mensaje de permiso SUBMIT_PAYMENT específico. No se retira ninguna validación financiera. |
| Nuevos `app/Support/Scheduling/AgendaPermissionPlan.php`, `app/Console/Commands/PlanAgendaPermissions.php` | Preparar nominación de permisos de Spatie guard web, de solo lectura, preservando permisos directos y de roles. |
| `app/Console/Commands/DiagnoseCommercialPilot.php` | Enumerar requisitos de pago/configuración ausentes sin hacer escrituras ni conceder permisos. |
| `public/js/scheduling/agenda.js`, `agenda-operational-form.js`, `agenda-operational-workspace.js`, `agenda-guidance.js` | Distinguir reserva/adelanto/confirmación, explicar el bloqueo, validar centavos/50% en UI, bloquear envíos simultáneos y gestionar UUID de reintentos. Se preserva UUID ante pérdida de red/5xx/409; se regenera tras rechazo seguro 400/403/404/413/422/429. |
| `resources/views/scheduling/agenda/partials/quick-registration.blade.php`, `patient-modal.blade.php`, `operational-registration.blade.php` | Acciones y mensajes específicos visibles, incluyendo reserva deshabilitada por CREATE, financiación pendiente y explicación del guardado en cita nueva/existente. |
| `phpunit.xml` | Aislar el modo financiero normal de variables heredadas del equipo. SQLite en memoria y el guard de seguridad de tests ya existían. |
| Nuevos `tests/Feature/Scheduling/AgendaStabilizationTest.php`, `AgendaPermissionPlanTest.php`, `tests/JavaScript/agenda-permission-flow.test.js` | Casos de permisos, pagos/50%, caja, histórico, endpoint directo, UUID, plan sin escrituras y estado de acciones en JS real ejecutado en VM. |
| `tests/Feature/Scheduling/AgendaAppointmentWriteTest.php`, `AgendaBoardTest.php`, `AgendaRefinementTest.php`, `PilotAppointmentOperationsTest.php`, `PilotDiagnosisTest.php`, `tests/JavaScript/agenda-guidance.test.js`, `agenda-operational-form.test.js` | Actualizar contrato de alta y textos. Fixtures LEGADO se crean directamente para probar comportamiento histórico; no restauran el bypass HTTP. Añadir requisitos económicos/UI. |
| `docs/AUDITORIA_AGENDA_ADELANTOS_DOCUMENTOS.md`, este informe | Conservar diagnóstico previo e identificar el resultado autorizado y cómo revisarlo. |

## Permisos nominativos: preparación sin aplicar

En una instalación LOCAL con datos ficticios y configuración local verificada:

```powershell
php artisan agenda:permission-plan ID_FICTICIO --profile=reserve
php artisan agenda:permission-plan ID_FICTICIO --profile=advance
php artisan pilot:diagnose --user=ID_FICTICIO
```

Sustituir ID_FICTICIO por un entero de esa instalación. Los comandos son de solo lectura; no crean usuarios, permisos, roles, caja o turnos. El plan requiere un rol operativo existente COMERCIAL, ADMISION o ADMINISTRADOR y lista permisos efectivos, adiciones directas propuestas y definiciones web ausentes. No hay opción `--apply`.

Perfil `reserve`: `appointment.mvp.access`, `appointment.view`, `appointment.create`. Perfil `advance`: añade `appointment.payment.submit`. No propone exoneración, adicionales ni administración de Caja. La preparación no cambia los controladores de roles existentes.

Para una futura asignación aprobada: administrador autorizado confirma identidad, guard web, alcance nominativo y definiciones existentes; registra evidencia del antes/después y usa `givePermissionTo` para las adiciones concretas aprobadas. Conservar roles y otros permisos; no usar `syncPermissions` con una lista parcial. No crear automáticamente definiciones que falten. Esta tarea no ejecuta ese paso.

Comercial/Admisión puede reservar y adjuntar la evidencia existente. Si solo capta evidencia, Caja registra/conciliará el dinero; un adjunto no equivale a cobro. Para una operadora nominada a cobrar, Caja debe validar el contexto financiero propio. Administrador revisa la autorización; Caja mantiene conciliación, comprobantes fiscales, arqueo/cierre, ambigüedades y devoluciones. Confirmar no sustituye estos controles.

## Pruebas y validación local

Entorno utilizado: PHP 8.0.30, Laravel 9, PHPUnit 9.6.34, Node 26.3.0. Sin `.env` productivo en el worktree. PHPUnit fuerza SQLite `:memory:` y testing; el guard falla antes del bootstrap si esa configuración no se cumple. Datos ficticios, HTTP externo bloqueado y notificaciones/correo/cola simulados por la base de tests.

- PHPUnit completo: **582 tests, 3.234 aserciones, cero fallos/errores y 1 omitido** (581 pasan). La omisión preexistente es `AppointmentAgendaSchemaTest::test_native_rollback_preserves_all_legacy_columns_rows_indexes_and_foreign_keys`: el SQLite instalado no admite DROP COLUMN nativo (requiere >= 3.35); no se habilitó ni cambió ese test.
- Node completo: **191 tests, 191 pasan, cero fallos/omitidos**.
- Sintaxis de todos los PHP modificados/nuevos (`php -l`), JS (`node --check`) y `git diff --check`: **correctos**.
- Se ejecutó también una tanda inicial de 81 tests PHP/535 aserciones antes de ampliar la cobertura. El resultado anterior de una prueba nueva fallida por comparación de tipos de fixture fue corregido usando el snapshot fresco de BD; la suite completa anterior no se presenta como resultado final.

Logs locales excluidos de Git: `storage/app/qa-entrega1-php-full.txt`, `qa-entrega1-php-results.xml`, `qa-entrega1-js.txt`.

Desde el checkout aislado, con PHP, Node y dependencias locales disponibles:

```powershell
php vendor/phpunit/phpunit/phpunit
$agendaTestFiles = @(rg --files tests/JavaScript -g '*.test.js')
node --test @agendaTestFiles
git diff --check
```

No utilizar la base productiva para estas pruebas. Para validar visualmente, arrancar SOLO una instalación local ya configurada con datos ficticios y MVP activo, sin copiar credenciales productivas. No ejecutar migraciones/seeders contra Hostinger.

| Caso local (servicio S/ 100) | Resultado esperado |
|---|---|
| Operador sin CREATE | Reserva/confirmación bloqueadas con motivo de permiso; las peticiones directas dan 403. |
| CREATE, sin SUBMIT_PAYMENT | Reserva con importe cero permitida, pendiente y sin ocupación. Cobro positivo da 403. |
| SUBMIT_PAYMENT sin turno propio o sin serie válida | Error específico de configuración; no se persiste dinero, voucher ni alta parcial. |
| Registro/confirmación regular con S/ 49.99 | No se confirma. El usuario puede optar por guardar reserva. |
| Registro regular con S/ 50.00 y contexto de caja válido | Se registra pago efectivo y se confirma si el intervalo sigue válido. |
| Reserva existente: adelantos S/ 49.99 + S/ 0.01 | Continúa pendiente después del cobro; Confirmar cita luego valida nuevamente y no registra otro pago. |
| Exoneración sin APPROVE_ZERO_COST o solo texto autorizado | No confirma sin financiación. Con permiso y autorización explícitos, la excepción existente es válida. |
| Doble clic / reintento con misma UUID | Una operación/cobro. Cambio de payload con UUID ya usada: 409. |
| Petición antigua sin mode, booking_type ni dinero | Reserva regular pendiente, incluso si se intenta inyectar total_pagado. Históricas sin cambios. |

## Borradores preservados

`storage/app/delivery-drafts/pre-entrega1/` conserva `tracked.patch` de los borradores anteriores, el helper batch, la migración propuesta, log inicial y README. Está excluido de Git y no participa en autoload, rutas ni migraciones de esta entrega. Es una copia local, no está en el commit ni se trasladará mediante checkout/clonado. No aplicar el patch entero sobre esta corrección: contiene cambios superpuestos y la regla incompatible que requieren revisión futura.

`AppointmentDocumentController.php` y `AppointmentProofStorage.php` se mantienen iguales a la base. No se añade carga múltiple, OCR ni vínculo nuevo de comprobante/pago.

## Riesgos pendientes y reversión

- Los permisos efectivos y la configuración financiera productiva siguen sin cambios. Confirmar asociación de sesión con ID 8 y autorizar nominativamente cualquier futura asignación; la falta de permisos seguirá bloqueando producción hasta entonces.
- Las pruebas usan SQLite; falta validación de concurrencia/locks y DDL bajo MySQL en un entorno aislado. No se declara validación contra Hostinger ni prueba visual de navegador realizada.
- Históricas LEGADO y reglas de adicionales/fuera de horario se preservan. La auditoría de otras escrituras heredadas fuera del MVP, y el circuito de verificación de Caja, requieren trabajo separado.
- Cambia el contrato de alta antigua: revisar integraciones/clientes que esperan ocupación inmediata sin pago. La regla de médico/intervalo puede rechazar una confirmación aunque ya exista adelanto.
- La protección UUID del cliente abarca reintentos en la selección actual, no una sesión durable entre recargas del navegador. Un resultado incierto exige consultar estado antes de empezar otra operación; backend conserva UUID y controles financieros/bancarios.
- Al desplegar en una entrega futura, coordinar caché de assets para que UI y servidor tengan la misma versión. No se despliega ahora.

Reversión: aplicar `git revert SHA_DEL_COMMIT_DE_ENTREGA_1` en una rama de revisión. No requiere rollback de esquema, permisos ni datos. La reversión reabriría el bypass de alta regular impaga del código anterior; evaluar ese riesgo antes de publicar una reversión. Nunca borrar/revertir pagos o citas reales para revertir código.

El estado de Git y SHA definitivo se informan en la entrega al usuario; no se requiere push para revisar este checkout.
