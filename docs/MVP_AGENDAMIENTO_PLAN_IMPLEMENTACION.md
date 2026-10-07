# MVP de Agendamiento — plan de implementación

## 1. Estado y propósito

Este documento convierte el Blueprint, los requisitos funcionales y la evidencia productiva de Fases 0–5 en un plan técnico incremental. Es planificación: no crea migrations, endpoints ni comportamiento productivo.

Clasificación usada:

- **CONFIRMADO EN PRODUCCIÓN:** demostrado mediante evidencia productiva sanitizada.
- **CONFIRMADO EN CÓDIGO:** observable en los repositorios auditados.
- **CONFIRMADO POR NEGOCIO:** decisión comunicada por Rodrigo/jefatura.
- **PROPUESTA TÉCNICA:** decisión reversible recomendada para implementar el MVP.
- **PENDIENTE:** requiere decisión empresarial o evidencia antes de activar el flujo afectado.
- **RIESGO:** condición que exige mitigación, prueba o rollback.

## 2. Restricciones de partida

1. Laravel 9 continúa como monolito modular evolutivo; no se propone separar microservicios para este MVP.
2. `appointments` productivo se conserva y evoluciona solo mediante cambios aditivos.
3. No se eliminan columnas ni se modifica impulsivamente el enum productivo.
4. El ERP será la fuente canónica futura de cita y reglas administrativas.
5. El llamador temporal seguirá siendo una aplicación separada hasta implementar un contrato explícito; nunca compartirá tablas con el ERP como solución futura.
6. Las integraciones RENIEC, correo, SMS y llamador se abstraen y se falsean en tests.
7. Las autorizaciones se validan en backend por capacidad; el menú solo refleja capacidades.
8. La activación inicia apagada y se habilita gradualmente mediante configuración y permisos, sin nombres de usuarios hardcodeados.

## 3. Arquitectura concreta propuesta

```text
Blade/JS + FullCalendar
        |
        v
Form Requests + Policies/capacidades
        |
        v
Casos de uso de Agenda
  |-- ConsultarDisponibilidad
  |-- IdentificarOCrearPaciente
  |-- CrearPreReserva
  |-- ConfirmarCita
  |-- ReprogramarCita
  |-- ReasignarResponsable
  |-- AutorizarExcepcion
  `-- RegistrarAdicional/Sobreagenda
        |
        +--> motor de disponibilidad y bloqueo por profesional/día
        +--> repositorios Eloquent + transacciones MySQL
        +--> auditoría de negocio
        +--> adaptador RENIEC
        `--> puerto de integración con llamador (implementación posterior)
```

### 3.1 Límites internos

| Módulo | Responsabilidad del MVP | No debe asumir |
|---|---|---|
| Agenda | horarios, disponibilidad, bloqueos, holds, citas y reprogramación | pagos como verdad canónica ni estados clínicos |
| Pacientes | búsqueda local, identidad mínima, completitud y deduplicación | que RENIEC siempre responde o que todo dato externo está verificado |
| Autorización | capacidades por acción y alcance de piloto | equivalencia entre nombre de rol y función empresarial futura |
| Comercial/Pagos | evidencia, verificación y excepción necesaria para confirmar | rediseño completo de ventas, voucher, caja o SUNAT |
| Auditoría | eventos críticos inmutables del MVP | reconstrucción histórica no demostrable |
| Integraciones | contratos y adaptadores | acceso directo a tablas de otra aplicación |

### 3.2 Componentes probables

Se reutilizan, sin refactor masivo inicial:

- `routes/web.php` y `routes/api.php` con middleware/capacidades explícitas;
- `app/Models/Appointment.php`, `Patient.php`, `DoctorSchedule.php`, `Payment.php`;
- controladores actuales de `admissionist/appointment`, `schedule`, `patient` y API interna;
- `app/Services/ReniecService.php`, detrás de un contrato nuevo y respuesta normalizada;
- vistas `resources/views/admissionist/appointment` y `schedule`;
- JavaScript actual de calendario/agenda como referencia de comportamiento;
- FullCalendar actual como motor visual, evitando duplicar reglas en JavaScript;
- Spatie Permission ya instalado para capacidades.

Se agregan posteriormente componentes pequeños orientados a casos de uso, por ejemplo:

```text
app/Domain/Scheduling/
app/Application/Scheduling/
app/Policies/AppointmentPolicy.php
app/Contracts/IdentityLookup.php
app/Integrations/Reniec/
app/Integrations/PatientCaller/
```

Los nombres son provisionales. El objetivo es sacar decisiones transaccionales de controladores sin refactorizar de una vez todo el ERP.

## 4. Dependencias y orden recomendado

El orden sugerido se ajusta respecto de la lista inicial: primero se introducen guardas, datos aditivos y concurrencia; después se construyen flujos y UI. Cada incremento debe ser desplegable con la funcionalidad nueva apagada.

```text
MVP-0 Seguridad/configuración
  -> MVP-1 Delta de datos compatible
      -> MVP-2 Disponibilidad + concurrencia
          +-> MVP-3 Paciente rápido/RENIEC
          +-> MVP-4 Cita rápida/responsable
                  -> MVP-5 Hold/adelanto/COSTO 0
                      -> MVP-6 Adicional/sobreagenda
                          -> MVP-7 UX Día/Semana/Mes
                              -> MVP-8 Contrato llamador
```

MVP-3 puede desarrollarse en paralelo con MVP-2 después de MVP-0/1. MVP-8 no bloquea los primeros incrementos, pero el modelo no debe impedirlo.

## 5. Incrementos

### MVP-0 — Contención, capacidades y activación

- **Objetivo:** preparar un perímetro seguro para desarrollar sin exponer rutas nuevas.
- **Comportamiento:** flag global apagado; acceso adicional mediante capacidades; errores y auditoría técnica sin PII; contratos fake para integraciones.
- **Archivos probables:** `config/features.php`, middleware/policies, permisos Spatie, rutas, factories y pruebas de seguridad. No hardcodear `COMERCIAL` porque no existe como rol productivo confirmado.
- **Datos conceptuales:** catálogo de permisos; sin tablas nuevas obligatorias. Asignación a roles/usuarios existentes durante piloto.
- **Compatibilidad:** rutas y menús actuales continúan; el flag nuevo no cambia el flujo heredado.
- **Tests:** flag apagado/encendido, permisos positivos y negativos para roles productivos, usuario sin rol, integraciones fake.
- **Riesgo:** conceder capacidades por rol de forma demasiado amplia.
- **Rollback:** apagar el flag y retirar asignaciones de permisos; sin pérdida de datos.
- **Dependencias:** ninguna.

### MVP-1 — Fundación de datos aditiva

- **Objetivo:** introducir el delta mínimo que separa sede, responsabilidad, hold, tipo de agendamiento, autorizaciones, pago y auditoría.
- **Comportamiento:** estructuras nuevas permanecen en sombra; los registros heredados siguen funcionando aun sin datos nuevos.
- **Archivos probables:** migrations nuevas aditivas, modelos/relaciones, factories, comandos de diagnóstico read-only y pruebas de migration. No editar la migration histórica de `appointments`.
- **Datos conceptuales:** estructuras detalladas en `MVP_AGENDAMIENTO_MODELO_DATOS.md`.
- **Compatibilidad:** columnas nuevas nullable inicialmente; backfill por lotes e idempotente; no cambiar enums ni FKs legacy.
- **Tests:** instalación limpia, upgrade con fixtures heredadas, rollback de cada migration, constraints e índices.
- **Riesgo:** locks prolongados al alterar `appointments` y certeza falsa en backfills.
- **Rollback:** eliminar solo estructuras nuevas mientras el flag está apagado; no tocar datos legacy.
- **Dependencias:** MVP-0.

### MVP-2 — Motor de disponibilidad y concurrencia

- **Objetivo:** obtener disponibilidad determinista y reservar intervalos sin doble asignación.
- **Comportamiento:** combina horario recurrente, excepción/bloqueo, citas vigentes y holds no vencidos; serializa mutaciones por sede/profesional/fecha.
- **Archivos probables:** casos de uso de disponibilidad, repositorios Eloquent, servicio de locking, API interna de agenda, adaptación progresiva de `ScheduleController` y `DoctorScheduleController`.
- **Datos conceptuales:** `doctor_schedules.site_id`, excepciones de horario y fila de lock por profesional/día.
- **Compatibilidad:** lectura paralela con feed actual; comparación de resultados antes de cambiar la UI.
- **Tests:** cruces, duración variable, borde de jornada, bloqueos, zona horaria Lima, dos transacciones competidoras en MySQL.
- **Riesgo:** reglas actuales implícitas y diferencias entre agenda generada y productiva.
- **Rollback:** volver el feed al motor legacy con flag; conservar estructuras nuevas sin escritores.
- **Dependencias:** MVP-1.

### MVP-3 — Paciente rápido y RENIEC resiliente

- **Objetivo:** identificar o crear una sola identidad sin abandonar la agenda.
- **Comportamiento:** DNI → búsqueda local; si no existe, consulta RENIEC; si falla, fallback manual; registro mínimo marcado como pendiente; posterior completado sobre la misma fila.
- **Archivos probables:** controlador/caso de uso de identidad, contrato `IdentityLookup`, adaptador de `ReniecService`, Form Requests, panel rápido y tests HTTP fake.
- **Datos conceptuales:** reutilizar `patients`; añadir únicamente metadatos de completitud/origen si no pueden representarse con seguridad en campos existentes.
- **Compatibilidad:** conservar rutas actuales mientras consumidores JS migran a una respuesta normalizada versionada.
- **Tests:** existente, nuevo, carrera de DNI duplicado, RENIEC exitosa/error/timeout, fallback, no PII en logs.
- **Riesgo:** duplicidad por solicitudes simultáneas y tratar RENIEC como verdad verificada.
- **Rollback:** desactivar búsqueda integrada y volver al alta actual; las identidades creadas permanecen válidas.
- **Dependencias:** MVP-0/1; puede avanzar en paralelo con MVP-2.

### MVP-4 — Agendamiento rápido, responsabilidad y reprogramación

- **Objetivo:** crear/reprogramar una cita común en segundos con contexto visual y trazabilidad.
- **Comportamiento:** slot u hora manual → paciente → servicio/precio → responsable inicial → guardado; creador, responsable y modificador se mantienen separados; reasignación auditada.
- **Archivos probables:** casos de uso de cita, policy, Form Requests, controladores finos, panel rápido Blade/JS, adaptador de compatibilidad de `Appointment`.
- **Datos conceptuales:** sede, responsable, último modificador, detalle de agendamiento e historial de responsabilidad.
- **Compatibilidad:** sigue escribiendo los campos legacy requeridos; no cambia enum; `user_id` conserva semántica de creador legado.
- **Tests:** permisos, doble clic idempotente, reprogramación, responsable inicial, reasignación, cita heredada sin detalle nuevo.
- **Riesgo:** duplicar la lógica actual del controlador grande o cambiar precios históricos.
- **Rollback:** flag al flujo heredado; datos nuevos permanecen como metadatos sin bloquear lectura legacy.
- **Dependencias:** MVP-2 y MVP-3.

### MVP-5 — Pre-reserva, confirmación, pago y COSTO 0

- **Objetivo:** retener cupos temporalmente y confirmar solo con regla económica o excepción auditable.
- **Comportamiento:** hold de 15 minutos configurable; expiración idempotente; extensión autorizada; evidencia de pago; verificación; umbral general 50 %; excepción al adelanto separada de COSTO 0; autorización de precio cero mediante maestro.
- **Archivos probables:** casos de uso de holds/confirmación, job/comando de expiración, policies, almacenamiento privado de evidencia, panel financiero acotado y adaptadores a pagos legacy.
- **Datos conceptuales:** holds, evidencias, autorizaciones y aprobadores COSTO 0.
- **Compatibilidad:** mantener y proyectar temporalmente `precio_programado`, `total_pagado`, `saldo_pendiente`, `metodo_pago`, `es_exonerado`, `autorizado_por`, `estado_pagado`, `numero_operacion`.
- **Tests:** expiración, extensión, pago 49/50/100 %, evidencia rechazada, excepción, COSTO 0 autorizado/no autorizado, carrera entre pago y expiración.
- **Riesgo:** doble cobro, divergencia con `payments`/voucher/caja y archivos con PII.
- **Rollback:** apagar confirmación nueva; no borrar holds, evidencias ni autorizaciones; conciliación antes de volver al flujo legacy.
- **Dependencias:** MVP-4 y decisiones específicas de COSTO 0/pago para activar esos subflujos.

### MVP-6 — Sobreagendamiento y cita adicional

- **Objetivo:** soportar excepciones sin confundirlas con cupos regulares.
- **Comportamiento:** horario no disponible abre evaluación; backend exige autorización para sobreagenda; adicional solo con agenda llena, información al paciente, espera configurable y dentro del bloque real; registra resultado atendido/no atendido cuando se defina.
- **Archivos probables:** policies/casos de uso de excepciones, reglas de capacidad, panel de detalle, auditoría.
- **Datos conceptuales:** tipo en detalle de agendamiento y autorización genérica; no usar `additional_rate_id` ni `cita_doble` como equivalentes.
- **Compatibilidad:** la cita conserva fila legacy y enum productivo; el tipo vive fuera del enum de estado.
- **Tests:** adicional dentro/fuera de horario, agenda no llena, límite, consentimiento informado, sobreagenda aprobada/rechazada, permiso negativo.
- **Riesgo:** activar sin límites empresariales o extender jornada implícitamente.
- **Rollback:** deshabilitar capacidades de excepción; mantener citas ya registradas y su trazabilidad.
- **Dependencias:** MVP-5 y decisiones empresariales enumeradas en el backlog.

### MVP-7 — Agenda Día/Semana/Mes y UX consolidada

- **Objetivo:** unificar filtros, comparación, calendario, lista horaria y panel rápido con alta densidad desktop.
- **Comportamiento:** vistas Día/Semana/Mes; filtros médico/especialidad; comparación; hora manual; semaforización accesible; detalle expandido; atajos seguros.
- **Archivos probables:** vistas Blade, componentes, módulos JS, feed de calendario versionado y CSS. FullCalendar se conserva si satisface rendimiento/accesibilidad.
- **Datos conceptuales:** ninguno obligatorio; usa APIs de incrementos previos.
- **Compatibilidad:** enlace reversible entre interfaz nueva y antigua durante piloto.
- **Tests:** feature/API, contratos JSON, navegador/UAT para interacciones críticas, permisos y privacidad.
- **Riesgo:** mover reglas al frontend, exceso de densidad o regresión en equipos productivos.
- **Rollback:** desactivar flag de UI; backend nuevo queda disponible sin exposición.
- **Dependencias:** MVP-2 a MVP-6.

### MVP-8 — Contrato controlado con llamador

- **Objetivo:** reemplazar la desconexión actual sin compartir tablas.
- **Comportamiento:** publicación idempotente de citas admitidas; correlación ERP/temporal; recepción controlada de llegada/llamado/atención/cierre; reconciliación y observabilidad.
- **Archivos probables:** puerto/adaptador de llamador, outbox o cola, endpoints autenticados/versionados, mapeador de estados y dashboards técnicos en ambos repositorios.
- **Datos conceptuales:** correlación externa, idempotency key, estado de entrega e intentos; diseño detallado posterior.
- **Compatibilidad:** inicialmente shadow/read-only; luego dual-run de eventos, nunca doble escritura directa a tablas.
- **Tests:** contrato, reintentos, orden fuera de secuencia, duplicados, llamador caído, privacidad y reconciliación.
- **Riesgo:** pérdida/duplicación de eventos y exposición de PII.
- **Rollback:** detener publicación/consumo por flag, conservar outbox y operar manualmente; no borrar correlaciones.
- **Dependencias:** MVP-4 estable; no bloquea el primer commit funcional.

## 6. Capacidades provisionales por acción

Nombres conceptuales, sujetos a convención final:

| Capacidad | Uso |
|---|---|
| `appointment.view` | consultar agenda/cita |
| `appointment.create` | crear cita regular |
| `appointment.update` | editar datos no sensibles permitidos |
| `appointment.reschedule` | cambiar fecha/hora/profesional |
| `appointment.responsible.assign` | reasignar responsable |
| `appointment.hold.create` | crear pre-reserva |
| `appointment.hold.extend` | extenderla con motivo |
| `appointment.payment.submit` | adjuntar evidencia |
| `appointment.payment.verify` | verificar/rechazar evidencia |
| `appointment.down_payment.override` | exceptuar adelanto |
| `appointment.zero_cost.request` | solicitar COSTO 0 |
| `appointment.zero_cost.approve` | aprobar/rechazar COSTO 0 si figura en maestro |
| `appointment.overbook` | registrar sobreagenda autorizada |
| `appointment.additional.create` | registrar adicional informada |
| `appointment.audit.view` | consultar historial autorizado |

Asignación inicial propuesta, pendiente de matriz final:

- ADMISION: lectura, paciente rápido, cita regular, hold, reprogramación y responsabilidad según alcance.
- RECEPCION: conservar funciones actuales; cualquier escritura de agenda se habilita solo por capacidad explícita.
- ADMINISTRADOR: administrar permisos/catálogos; no recibe escritura operativa automáticamente.
- CAJA: evidencia/verificación/cobro según definición empresarial, no edición clínica/agenda por defecto.
- FACTURACION: lectura financiera/fiscal necesaria, sin escritura de agenda por defecto.
- COMERCIAL: no existe productivamente; crear el rol/asignación será una decisión de evolución y despliegue, no una suposición del código.

## 7. Feature flag y piloto

Propuesta mínima:

1. `SCHEDULING_MVP_ENABLED=false` como corte global por entorno.
2. capacidad `appointment.mvp.access` para usuarios/roles participantes.
3. filtros de profesionales/sede configurables solo cuando se confirme el grupo del piloto.
4. no crear un framework genérico de flags; si el piloto exige limitar profesionales, añadir una estructura específica y auditable en MVP-1.
5. la UI nueva y sus escrituras deben consultar flag y capacidad en backend.

## 8. Definition of Done por incremento

Un incremento se considera listo para revisión cuando:

- migrations aditivas y rollback fueron probados en copia aislada cuando existan;
- tests SQLite aplicables y tests MySQL necesarios están verdes;
- permisos positivos/negativos están cubiertos;
- integraciones externas están fakeadas;
- logs no contienen DNI completo, pacientes, tokens ni evidencias;
- compatibilidad con registros heredados está demostrada;
- flag apagado deja el comportamiento previo intacto;
- documentación y runbook de rollback están actualizados;
- no existe dependencia silenciosa del llamador legacy.

## 9. Decisiones empresariales que bloquean activaciones específicas

1. Campos mínimos exactos para agendar, campos para completar/verificar y nombres de estados de completitud del paciente.
2. Quién autoriza sobreagendamiento y cuándo una selección no disponible puede regularizarse como cita regular excepcional.
3. Máximo de adicionales por profesional/bloque/día y resultado/cierre exacto de una no atendida.
4. Autoaprobación y límites económicos del maestro COSTO 0.
5. Roles/personas que pueden verificar adelantos y evidencias de pago.
6. Grupo del piloto: usuarios, profesionales y alcance de sede.

Estas decisiones no bloquean MVP-0/1 ni la construcción genérica detrás de flags. Sí bloquean la activación de los subflujos afectados y la UAT dirigida.
