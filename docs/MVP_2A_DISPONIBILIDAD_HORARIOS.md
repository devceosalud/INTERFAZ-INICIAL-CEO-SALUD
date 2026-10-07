# MVP-2A — Motor de disponibilidad y horarios médicos

## Estado de validación

> **LOCAL CHECKPOINT. SIN COMMIT.**
> Hereda el pendiente de MVP-1A: las migrations de `sites` y `site_id` siguen sin validarse en MySQL sano. MVP-2A **no añade ninguna migration**.

## Objetivo

Crear la única fuente de verdad de aplicación para decidir qué horas de un profesional están libres u ocupadas, de modo que el calendario futuro la consuma en lugar de recalcular disponibilidad en cada controlador y en cada archivo JavaScript.

## 1. AS-IS encontrado

### A. Cómo se representa hoy un horario

Una fila de `doctor_schedules` es un bloque: `doctor_id`, `dia_semana`, `fecha_cita` (nullable), `hora_inicio`, `hora_fin`, `duracion_cita`, `estado`. MVP-1A añadió `site_id` nullable. No existe ninguna otra tabla de horarios.

### B. Recurrencia vs fecha específica

La estructura soporta ambas y la consulta de disponibilidad de `ScheduleController` las contempla: una fila con `fecha_cita` concreta aplica a esa fecha, y una fila con `fecha_cita` nula recurre semanalmente según `dia_semana`.

**Pero la recurrencia está muerta en la práctica.** `ScheduleController@store` exige `fecha_cita` y escribe `dia_semana = '1'` fijo, con el comentario "Lunes por defecto"; `updateDoctorSchedule` repite el literal. Por tanto todos los horarios reales son de fecha específica y `dia_semana` contiene un valor sin significado.

### C. Hora inicio y fin

Columnas `time`. La validación exige `hora_fin` posterior a `hora_inicio`. No hay validación de que el bloque caiga dentro de un horario operativo de la clínica.

### D. Turnos

No existe el concepto. Mañana y tarde se expresan como dos filas del mismo día. `appointments.turno_cita` no es un turno de jornada: se escribe siempre a `0` y pertenece al futuro llamador.

### E. Duración utilizada

Ver sección 3. La fuente real es `doctor_schedules.duracion_cita`.

### F. Cómo se calcula la disponibilidad hoy

En **tres** lugares distintos que no coinciden:

1. `ScheduleController::generarEventosDisponibles`, privado, genera slots en PHP para FullCalendar. Es el único que resuelve la recurrencia y el único cuyo recorrido no se sale del bloque.
2. `Api\doctorSchedule\DoctorScheduleController@availableHours` devuelve `horarios` y `ocupadas` en crudo y delega el cálculo al navegador. Solo busca por `fecha_cita` exacta, así que **ignora la recurrencia**.
3. Tres copias del mismo algoritmo en JavaScript: `appointment.js`, `editar-cita.js` y `available-schedule.js`.

Además `DashboardController` calcula su propia colección `$ocupadas`, con un cuarto criterio de estados.

### G. Solapamientos

- Entre bloques de horario: **no hay validación**, se pueden crear bloques que se solapen.
- Entre citas: `AppointmentController@store` **no comprueba conflictos**. Solo verifica que el mismo paciente no tenga otra cita `PROGRAMADO` con el mismo médico y fecha. La única protección contra doble reserva es la lista de horas que ofrece la UI.

### H. Considera citas existentes

Sí en las tres rutas de disponibilidad. No en el guardado.

### I. Considera sede

No, en ningún punto.

### J. Diferencias entre roles

Las lecturas de agenda y horarios están bajo `auth` más el conjunto operativo de roles; los escritos de horario bajo `role:ADMISION`; los maestros bajo `role:ADMINISTRADOR`. La API interna de horas disponibles está protegida por `internal-api` más rol. No hay diferencias de cálculo por rol, solo de acceso.

## 2. Problemas reales detectados

1. **Una cita sin `duracion_cita` no bloquea nada.** La columna es nullable. El PHP heredado hace `?? 0` y el JavaScript `parseInt(null)`, que es `NaN`; en ambos casos la comparación de solapamiento falla y la hora vuelve a ofrecerse aunque ya esté tomada. Es la vía más directa a una doble reserva.
2. **El JavaScript puede ofrecer un slot que se sale del bloque.** Avanza mientras el *inicio* del slot está dentro del bloque (`while (actual < final)`), así que un bloque cuya duración no es múltiplo del slot ofrece una hora que termina después de `hora_fin`. El PHP sí valida el fin.
3. **Cuatro definiciones distintas de "ocupado".** Tres rutas excluyen `NO_ASISTIO`, `CANCELADO`, `ATENDIDO` y `REEVALUACION`; el listado del dashboard excluye solo tres e incluye `ATENDIDO`.
4. **La recurrencia está soportada a medias**: el motor heredado de calendario la resuelve, la API de horas no.
5. **`AppointmentController@store` no valida conflicto** ni comprueba que exista bloque: `$horario->duracion_cita` se invoca sobre un posible `null`.

## 3. Arquitectura implementada

Nada del código heredado se ha modificado. Se añadió una capa nueva que el resto podrá adoptar.

| Pieza | Responsabilidad |
|---|---|
| `App\Support\Scheduling\TimeRange` | intervalo semiabierto y **única** definición de solapamiento |
| `App\Support\Scheduling\AppointmentOccupancy` | única definición de qué estados consumen tiempo, y duración de respaldo |
| `App\Support\Scheduling\AvailabilityQuery` | entrada explícita: profesional, fecha, sede, duración requerida |
| `App\Support\Scheduling\AvailabilitySlot` | un intervalo ya resuelto como libre u ocupado |
| `App\Support\Scheduling\DayAvailability` | resultado del día, con consultas de conveniencia |
| `App\Services\Scheduling\DoctorAvailabilityService` | el motor |
| `App\Http\Controllers\Scheduling\DoctorAvailabilityController` | contrato HTTP de solo lectura |

Decisiones de acoplamiento:

- el motor **no lee `Request`**, sesión ni configuración: todo llega en `AvailabilityQuery`;
- no conoce Blade ni FullCalendar; `DayAvailability::toArray()` es una conveniencia, no el modelo;
- es **de solo lectura**: no escribe nada;
- los incrementos siguientes añaden campos a `AvailabilityQuery` en lugar de cambiar la firma del motor, que es la extensión prevista para pre-reserva, sobreagenda autorizada y adicionales.

## 4. Reglas de disponibilidad

1. Se consideran solo bloques `ACTIVO` del profesional que apliquen a la fecha, por `fecha_cita` exacta o por recurrencia `dia_semana` cuando `fecha_cita` es nula. Se reutiliza la semántica heredada.
2. Un bloque con `duracion_cita` nula o cero se descarta como dato defectuoso en lugar de provocar un bucle infinito.
3. El paso de la rejilla es la `duracion_cita` del bloque. La longitud del slot es la duración requerida si se pidió, y si no la del bloque.
4. **Un slot nunca sobrepasa `hora_fin`**: debe caber completo dentro del bloque.
5. Un slot está ocupado si su intervalo se solapa con el de alguna cita que consuma tiempo.
6. Solapamiento por rango, no por hora exacta: `nueva_inicio < existente_fin AND nueva_fin > existente_inicio`. Al ser intervalos semiabiertos, una cita de 10:00 a 10:30 bloquea 10:00 y 10:15 y **deja libre 10:30**.

## 5. Fuente de duración

No se inventó ninguna fuente nueva. `doctor_services` y `services` **no tienen** columna de duración, así que no hay fuentes contradictorias.

| Uso | Fuente | Naturaleza |
|---|---|---|
| tamaño del slot | `doctor_schedules.duracion_cita` | configuración del bloque, obligatoria, valores 10/15/20/30/45/60 |
| longitud de una cita existente | `appointments.duracion_cita` | fotografía tomada al crear la cita, nullable |
| duración requerida puntual | `AvailabilityQuery::requiredMinutes()` | explícita del llamante |

Cuando `appointments.duracion_cita` es nula, el motor toma la duración del bloque que contiene esa hora y, si no hay bloque, `AppointmentOccupancy::FALLBACK_MINUTES` (15). **Nunca cero.** Esto corrige el problema 1.

Nada está fijado globalmente en 15 minutos: el 15 es solo el respaldo de un dato ausente. Se mantienen separados los tres conceptos que el requerimiento distingue: duración de consulta, futura pre-reserva de 15 minutos e intervalo visual del calendario.

## 6. Tratamiento de estados

Se parte del enum productivo confirmado de diez valores y **no se modifica**.

**Liberan el slot:** `CANCELADO` y `NO_ASISTIO`.

**Consumen tiempo:** `PROGRAMADO`, `CONFIRMADO`, `PACIENTE_LLEGO`, `EN_ESPERA`, `LLAMANDO`, `EN_ATENCION`, `ATENDIDO`, `REEVALUACION`.

El criterio es asimétrico a propósito: bloquear de más una hora pasada es recuperable, ofrecer una hora ya tomada produce una doble reserva.

### `ATENDIDO` — decisión firme

El código heredado lo excluye de la ocupación. **El motor lo bloquea**, porque ese intervalo fue efectivamente consumido: la consulta ocurrió y el profesional estuvo ocupado. Excluirla hace que una hora del día en curso aparezca libre cuando ya se usó. Para fechas futuras la diferencia es irrelevante, ya que una cita futura no puede estar atendida.

### `REEVALUACION` — política PROVISIONAL

**El motor lo bloquea.** Justificación de negocio: una reevaluación en CEO Salud representa una atención posterior vinculada a una atención original, y puede ocurrir en una ventana operativa distinta. Por tanto **no debe interpretarse como "la cita original queda libre"**.

Esta política es **provisional y queda registrada como tal**. El TO-BE deberá modelar después:

1. la atención original;
2. su posible reevaluación vinculada;
3. el horario o evento propio de la reevaluación cuando corresponda.

Ese modelo **no se resuelve en MVP-2A**. Cuando exista, hay que revisar la lista de estados de `AppointmentOccupancy`, y la deuda queda anotada tanto en esa clase como en la sección 14.

Ninguna de las dos decisiones altera el comportamiento actual de la aplicación, porque el motor todavía no está conectado a las rutas heredadas. Ambas tienen prueba individual explícita.

### Deriva del enum — consecuencia verificada

La migration versionada declara solo ocho valores: faltan `PACIENTE_LLEGO` y `REEVALUACION`, que producción sí tiene y que el código consulta. Está registrado en `MATRIZ_DRIFT_BD.md`.

**Consecuencia comprobada al escribir las pruebas:** Laravel compila `enum` en SQLite como una `CHECK constraint`, así que el esquema local **sí la aplica** y esos dos valores **no pueden insertarse**. Intentarlo lanza `QueryException`. Por tanto cualquier base construida desde estas migrations rechazaría dos estados que producción usa a diario.

Implicación para las pruebas: la política de ocupación de esos dos estados se verifica sobre la regla (`AppointmentOccupancy::blocks()` y `BLOCKING_STATES`) y no de extremo a extremo, porque la fila no se puede crear. Existe además una prueba que caracteriza el rechazo, de modo que el día en que el enum se alinee esa prueba falle y obligue a sustituir la verificación de regla por una de extremo a extremo.

No se modifica el enum en este incremento. Alinearlo es trabajo previo al piloto y exige comparación con producción.

## 7. Tratamiento de sede

`AvailabilityQuery` acepta `siteId` opcional.

- Sin sede: no se filtra, que es el comportamiento equivalente al actual.
- Con sede: se incluyen las filas de esa sede **y también las que tienen `site_id` nulo**, tanto en bloques como en citas.

Incluir las nulas es deliberado. Excluirlas ocultaría la ocupación heredada y el motor ofrecería horas ya tomadas, que es precisamente el fallo que este incremento corrige. No se esconde ninguna cita heredada.

Estrategia provisional para la sede única actual: mientras exista una sola sede, filtrar o no filtrar da el mismo resultado. Cuando se abra una segunda, habrá que asignar sede a los bloques y a las citas nuevas y endurecer esta regla para que las nulas dejen de considerarse universales. **No se hace backfill productivo.**

## 8. Horarios configurables y fechas especiales

Lo que la estructura heredada **sí** puede expresar:

- varios bloques por día, como filas independientes;
- mañana y tarde, como dos bloques;
- distintos días de la semana, mediante `dia_semana` con `fecha_cita` nula;
- horario específico por fecha, mediante `fecha_cita`;
- cambios puntuales, editando o desactivando la fila de esa fecha;
- ausencia de un día: simplemente no existe bloque para esa fecha.

**No hace falta ninguna estructura nueva y no se creó ninguna.** La razón es que el uso real es siempre de fecha específica, y con fechas específicas la excepción y la ausencia se expresan por ausencia o edición de la fila correspondiente. Una tabla de excepciones o de reglas recurrentes sería complejidad sin necesidad demostrada.

El delta mínimo quedaría **solo** si en el futuro se activa la recurrencia semanal de verdad: entonces sí haría falta distinguir horario base de excepción, porque no se puede cancelar una regla recurrente para una fecha concreta. La propuesta mínima en ese escenario es una marca de tipo en `doctor_schedules` que permita filas de bloqueo, no un sistema de calendarios recurrentes. **No se propone ejecutarlo ahora.**

## 9. Contrato de disponibilidad

`GET /scheduling-mvp/availability`, con `doctor_id`, `fecha`, y opcionalmente `site_id` y `duracion`.

Protección: `scheduling-mvp` (feature flag), `auth`, `permission:appointment.mvp.access` del grupo, más `permission:appointment.view` propio. No se confía en la UI.

La respuesta contiene fecha, profesional, sede e intervalos con su estado. **No contiene ningún dato personal de paciente**: para un slot ocupado basta saber que está ocupado, y hay una prueba que lo verifica sobre el cuerpo de la respuesta.

## 10. Concurrencia

Hay que mantener separadas dos cosas distintas:

- **consultar disponibilidad** es una lectura, y es lo que hace MVP-2A. Detecta conflictos con lo que ya existe en el momento de la consulta;
- **reservar atómicamente** exige transacción y bloqueo sobre la fila o el rango, y pertenece al incremento que cree citas o pre-reservas.

Un GET de disponibilidad no puede evitar que dos usuarias que lo consultan a la vez elijan la misma hora. Esa garantía se resuelve al escribir, no al leer. `AppointmentController@store` hoy no valida conflicto en absoluto, así que el problema existe con independencia de este motor.

## 11. Compatibilidad histórica

- No se modificó ninguna ruta, controlador, vista ni archivo JavaScript heredado.
- No se añadió ninguna migration.
- El motor solo se alcanza tras el feature flag, apagado por defecto.
- Las citas y bloques heredados sin sede siguen siendo visibles y siguen ocupando.
- Las citas heredadas sin duración ahora ocupan, en lugar de no ocupar nada.

## 12. Mejoras adyacentes

**Corregidas ahora**, dentro del motor nuevo y sin tocar el heredado: la duración nula que no bloqueaba, el slot que se salía del bloque, y la unificación de la definición de ocupación y de solapamiento.

**Antes del piloto:**

- `AppointmentController@store` no valida conflicto de horario ni la existencia del bloque, y desreferencia un posible `null` en `$horario->duracion_cita`;
- las cuatro definiciones de "ocupado" siguen vivas en el código heredado;
- `ScheduleController@doctor_schedules` filtra los horarios con `fecha_cita LIKE '%Y-m%'` del mes en curso e ignora el rango que pide el calendario, así que navegar a otro mes no carga horarios;
- `ScheduleController@list` emite `end` igual a `start`, de modo que los eventos de cita no muestran duración;
- el enum versionado de `estado_cita` rechaza `PACIENTE_LLEGO` y `REEVALUACION`, que producción usa. Cualquier base reconstruida desde las migrations rompería esos dos flujos.

**Backlog TO-BE:**

- migrar las tres rutas heredadas y las tres copias de JavaScript al motor;
- eliminar `dia_semana` fijado a `'1'`, o activar la recurrencia de verdad;
- validar solapamiento al crear bloques de horario.

**Críticas:** ninguna nueva. Sigue vigente el `ON DELETE CASCADE` heredado documentado en `AUDITORIA_BORRADOS_HEREDADOS.md`.

## 13. Límites del incremento

No se implementó, por estar fuera de alcance: pre-reserva, adelanto del 50 %, costo 0, adicionales, sobreagenda autorizada, paciente rápido con RENIEC, creación rápida de cita, responsable editable en UI, calendario visual final con arrastrar y soltar, e integración con el llamador.

## 14. Deuda pendiente para MVP-2B

1. Modelar atención original, reevaluación vinculada y horario propio de la reevaluación, y revisar entonces la política provisional de `REEVALUACION`.
2. Conectar el calendario y la API heredada al motor, retirando las tres copias de JavaScript.
3. Validar conflicto al guardar y al reprogramar citas, con la protección transaccional.
4. Decidir la vista Día/Semana/Mes sobre la salida del motor, para que la usuaria vea de un golpe profesional, horario, ocupado y disponible.
5. Validar en MySQL sano lo pendiente de MVP-1A.
