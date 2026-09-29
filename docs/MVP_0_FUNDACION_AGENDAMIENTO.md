# MVP-0 — Fundación segura del módulo de agendamiento

## Objetivo

Preparar un perímetro mínimo para desarrollar el futuro módulo sin alterar el flujo heredado. Este incremento no implementa agenda, disponibilidad, holds, pacientes, pagos, RENIEC, adicionales, llamador ni UI final.

## Feature flag

- variable: `SCHEDULING_MVP_ENABLED`;
- configuración: `config/scheduling.php`;
- default: `false`;
- testing/local puede activarlo en memoria con `config()->set('scheduling.enabled', true)` o mediante entorno local explícito;
- `phpunit.xml` y `.env.testing` lo fuerzan apagado por defecto;
- el middleware `scheduling-mvp` devuelve 404 cuando está apagado, también para visitantes anónimos.

`App\Http\Kernel` antepone `EnsureSchedulingMvpEnabled` a la lista de prioridad usando la API oficial `prependToMiddlewarePriority()` en su constructor. Sin eso Laravel eleva `auth` por encima de `SubstituteBindings` del grupo `web`, lo ejecuta antes de cualquier middleware de ruta y un anónimo recibiría una redirección al login que delata la existencia de la ruta con el módulo apagado.

La lista de prioridad no se copia: se hereda del framework y solo se le antepone una entrada, de modo que una futura actualización de Laravel no exige reconciliarla. El middleware queda en el índice 0, antes de `EncryptCookies` y `StartSession`; es inocuo porque solo lee configuración y aborta 404, y el proyecto no tiene vistas propias en `resources/views/errors/`, así que ese 404 se renderiza con la vista del framework, que no usa sesión ni autenticación.

No se creó un framework genérico de feature flags.

## Capacidades

`App\Support\Scheduling\SchedulingCapability` centraliza los nombres del catálogo declarado en `MVP_AGENDAMIENTO_PLAN_IMPLEMENTACION.md` §6 y §7:

- `appointment.mvp.access`;
- `appointment.view`;
- `appointment.create`;
- `appointment.update`;
- `appointment.reschedule`;
- `appointment.responsible.assign`;
- `appointment.hold.create`;
- `appointment.hold.extend`;
- `appointment.payment.submit`;
- `appointment.payment.verify`;
- `appointment.down_payment.override`;
- `appointment.zero_cost.request`;
- `appointment.zero_cost.approve`;
- `appointment.additional.create`;
- `appointment.overbook`;
- `appointment.audit.view`.

Son identificadores para Spatie Permission; no crean permisos, roles ni asignaciones automáticamente. COMERCIAL no se crea.

`appointment.mvp.access` corresponde al ítem P0 `E0-02` del backlog y expresa participación en el piloto. Se mantiene separada de `appointment.view`, que expresa lectura de agenda: tener una no implica la otra.

## Aislamiento de rutas

`routes/scheduling.php` define un único endpoint de infraestructura sin UI ni lógica funcional:

```text
GET /scheduling-mvp
```

Requiere, en este orden:

1. feature flag activo;
2. autenticación;
3. capacidad `appointment.mvp.access`.

Con acceso válido responde 204. Sirve para caracterizar el gate y podrá convertirse en la entrada real del módulo; no expone datos.

El flag va primero para que el módulo apagado sea indistinguible de una ruta inexistente ante cualquier visitante.

## Convivencia con Fase 1

- `RequiresRole` y middleware `role:` permanecen intactos para módulos heredados.
- El MVP utiliza capacidades granulares mediante Spatie, no un sistema paralelo.
- `RequiresCapability` centraliza la comprobación Spatie para acciones backend.
- `RequiresSchedulingMvpAccess` combina feature flag, autenticación y capacidad para futuros componentes Livewire invocados directamente.
- Tener el rol ADMINISTRADOR no concede automáticamente acciones sensibles.
- La transición es incremental: no se refactorizó la seguridad completa del ERP.

## Archivos

Nuevos:

- `config/scheduling.php`;
- `app/Support/Scheduling/SchedulingCapability.php`;
- `app/Http/Middleware/EnsureSchedulingMvpEnabled.php`;
- `app/Http/Livewire/Concerns/RequiresCapability.php`;
- `app/Http/Livewire/Concerns/RequiresSchedulingMvpAccess.php`;
- `app/Http/Controllers/Scheduling/MvpAccessController.php`;
- `routes/scheduling.php`;
- `tests/Feature/Scheduling/SchedulingMvpFoundationTest.php`;
- este documento.

Modificados:

- `app/Http/Kernel.php`;
- `routes/web.php`;
- `.env.example`;
- `.env.testing`;
- `phpunit.xml`.

## Tests

La suite específica verifica:

- el default versionado del flag está apagado, sin forzar configuración en el test;
- flag apagado y flujo heredado intacto;
- flag apagado devuelve 404 a un visitante anónimo, sin delatar la ruta;
- visitante rechazado con flag activo;
- autenticado sin capacidad rechazado;
- capacidad explícita permitida;
- `appointment.view` no concede acceso al piloto;
- la capacidad de lectura no concede ninguna de las 13 capacidades de escritura;
- ADMINISTRADOR sin capacidad no hereda acciones sensibles;
- CAJA y FACTURACION no modifican agenda sin capacidad expresa;
- guard de backend para invocación directa;
- guard directo rechazado también cuando el flag está apagado;
- el catálogo coincide exactamente con el plan, sin duplicados, sin crear COMERCIAL y sin crear permisos.

Resultado: 15 tests del MVP-0 y 76 en la suite completa, sin regresiones de Fases 0/1.

Los guards de Fase 0 permanecen: SQLite en memoria, HTTP sin llamadas imprevistas, Mail/Queue/Notification fake y configuración de testing forzada.

## Riesgos y pendientes

- la matriz definitiva de asignación de capacidades continúa pendiente;
- las capacidades existen como constantes, pero no se crean/asignan en producción;
- futuros endpoints y Livewire deben aplicar el middleware/trait, no solo ocultar botones;
- la ruta 204 es infraestructura, no una pantalla de agenda;
- `E0-04` (policies y Form Requests) y `E0-06` (logging sanitizado con correlation id) son P0 de la épica E0 y quedan diferidos: no hay todavía escrituras ni logs propios del módulo que protegerlos. Deben resolverse antes de la primera escritura del piloto.

## Activación local/testing

Preferido en tests:

```php
config()->set('scheduling.enabled', true);
```

En entorno local aislado puede configurarse `SCHEDULING_MVP_ENABLED=true`. No debe cambiarse el default versionado ni activarse en producción durante MVP-0.

## Rollback

1. mantener `SCHEDULING_MVP_ENABLED=false` para desactivar inmediatamente;
2. retirar el require de rutas, el alias de middleware y el constructor del Kernel si se revierte el código;
3. eliminar archivos nuevos/configuración;
4. no existe rollback de datos porque MVP-0 no agrega migrations, tablas ni asignaciones.

## Confirmación de alcance

No se implementaron schemas, migrations, motor de disponibilidad, pre-reservas, precios, pagos, COSTO 0, adicionales, sobreagenda, RENIEC, llamador, calendario ni UI funcional.
