# MVP-2B — Integración de disponibilidad en el calendario heredado

> **LOCAL. SIN COMMIT al momento de escribir este documento. NO APROBADO PARA DEPLOY HASTA VALIDAR MYSQL.**

Rama: `feature/mvp-2b-scheduling-schedule-ux`
Base: `b8281c500783e8196574d53003dafe5bbf1456bf` (MVP-2A)

MVP-2A dejó el motor de disponibilidad construido pero **sin consumidores**. MVP-2B lo
conecta al flujo real: los endpoints heredados dejan de calcular disponibilidad por su
cuenta, el JavaScript deja de decidir qué horas están libres, y los dos defectos visibles
del calendario (navegación de rango y duración de evento) quedan corregidos con test.

No se crearon migrations. No se modificó el enum de `appointments`. No se tocó producción.

---

## 1. Caracterización previa

Antes de modificar cualquier controlador se fijó el comportamiento heredado en
`tests/Feature/Scheduling/LegacyScheduleContractTest.php` (17 tests): estructura JSON,
nombres de campo, tipos, códigos HTTP, comportamiento sin datos y con bloques/citas.

Dos tests de caracterización **fallaron contra el código heredado**, y ese fallo es el
hallazgo: no eran defectos del test sino dos bugs reales del calendario de citas
(ver §4 y §5).

## 2. Endpoints migrados al motor

| Endpoint | Antes | Ahora |
| --- | --- | --- |
| `ScheduleController::generarEventosDisponibles` (privado) | Recalculaba bloques, ocupación y slots dentro del controlador | Delega en `DoctorAvailabilityService`; conserva la forma de evento de 12 claves |
| `Api\DoctorScheduleController@availableHours` | Devolvía bloques crudos + citas ocupadas crudas | Devuelve slots ya resueltos |
| `ScheduleController@doctor_schedules` | `fecha_cita LIKE '%Y-m%'` del mes del servidor | Acepta, valida y usa el rango del cliente |
| `ScheduleController@list` | `end` igual a `start`; rango comparado con bindings datetime | `end` derivado de la duración resuelta; rango comparado por fecha |

Al delegar, `generarEventosDisponibles` **hereda gratis** las correcciones de MVP-2A: una
cita guardada sin `duracion_cita` sigue bloqueando (antes `?? 0` la volvía inofensiva), un
slot nunca se pasa de `hora_fin`, el solapamiento se detecta por rango, el conjunto de
estados que consumen agenda está definido una sola vez y el filtro por sede tolera filas
heredadas sin sede.

### Contratos conservados

- `list`: mismos nombres de campo (`id`, `title`, `start`, `end`, `color`,
  `backgroundColor`, `borderColor`, `textColor`, `estado_cita`, …) y mismos eventos
  `disponible` de 12 claves.
- `doctor_schedules`: misma forma de evento, mismo filtro por `doctor_id`, misma
  autorización, mismo comportamiento cuando no se envía rango (cae al mes actual).
- Ningún endpoint cambió su método HTTP, su ruta ni su middleware.

### Contrato cambiado — ANTES vs DESPUÉS

Un único cambio de contenido, en `POST /api/appointment/schedule/available-hours`:

```
ANTES
{
  "horarios": [ { id, doctor_id, dia_semana, fecha_cita, hora_inicio, hora_fin,
                  duracion_cita, estado, ... } ],
  "ocupadas": [ { "hora_cita": "08:00:00", "duracion_cita": 30 } ]
}

DESPUÉS
{
  "slots": [ { "inicio": "08:00", "fin": "08:30", "minutos": 30,
               "estado": "DISPONIBLE", "site_id": null } ]
}
```

Motivo: `horarios` y `ocupadas` existían **solo** para que el navegador calculase los
slots. Mientras esos dos campos siguieran expuestos, seguiría siendo posible un segundo
motor en cliente, que es justamente lo que MVP-2A vino a eliminar. Se retiraron en el
mismo incremento que migró a sus tres consumidores, y no queda ningún otro consumidor en
el repositorio (verificado por búsqueda).

Efecto colateral positivo: la respuesta ya no contiene `hora_cita` de citas reales, es
decir dejó de revelar en qué momento exacto hay un paciente atendido. Solo dice qué está
libre. Cubierto por `test_the_available_hours_api_exposes_no_patient_data`.

Además el endpoint ahora **valida** su entrada (`doctor_id`, `fecha_cita`, `cita_doble`).
Antes, una petición sin parámetros respondía 200 con listas vacías; ahora responde 422.

## 3. JavaScript: cálculo duplicado retirado

Los tres consumidores pasaron a pintar los slots que llega resueltos:

| Archivo | Retirado | Resultado |
| --- | --- | --- |
| `available-schedule.js` (prioridad) | `generarHorariosCitaDisponibilidad` reescrita; `existeCruceCitaDisponibilidad`, `convertirMinutosCitaDisponibilidad`, `convertirHoraCitaDisponibilidad` eliminadas | Ya no decide disponibilidad |
| `appointment.js` | `existeCruceCita`, `convertirMinutosCita`, `convertirHoraCita` eliminadas | Solo renderiza `<option>` |
| `editar-cita.js` | `existeCruceEditarCita`, `convertirMinutosEditarCita`, `convertirHoraEditarCita` eliminadas | Solo renderiza `<option>` |

Los dos últimos se migraron ahora porque el cambio resultó pequeño y simétrico al primero:
las tres funciones eran copias literales del mismo algoritmo, y dejar dos vivas habría
mantenido dos motores activos. No se creó una cuarta utilidad JS genérica: no hay lógica
que compartir, solo un bucle de render de tres líneas por archivo.

Con esto desaparecen del cliente dos defectos que tenía el algoritmo copiado: el bucle
`while (actual < final)` podía emitir un último slot que se pasaba de `hora_fin`, y
`parseInt(cita.duracion_cita)` producía `NaN` con una cita heredada sin duración, de forma
que esa cita no bloqueaba nada.

`cita_doble` sigue funcionando igual desde el punto de vista del usuario, pero la regla
vive en el servidor: se envía como parámetro y el motor la aplica mediante el multiplicador
de duración de `AvailabilityQuery`, que duplica la duración **del bloque** en lugar de un
número fijo de minutos. Eso es exactamente lo que hacía el JS (`duracion * 2`).

## 4. Navegación de rango corregida

`ScheduleController@doctor_schedules` filtraba con `fecha_cita LIKE '%' . Date('Y-m') . '%'`.
El calendario de horarios médicos **siempre** mostraba el mes del servidor, así que
navegar a otro mes o a otro año devolvía cero bloques aunque existieran.

Ahora acepta `start` y `end`, los valida (`nullable|date`, `end` `after_or_equal:start`) y
compara por rango de fechas. No se usó `LIKE` porque la comparación por rango es correcta
además de legible: aprovecha el orden natural de la columna `date` y no depende del formato
textual. La autorización existente no se tocó.

FullCalendar ya enviaba `start` y `end` automáticamente
(`public/js/admissionist/calendario-medico/calendario-medico.js`, `events: { url, extraParams }`),
por lo que esta corrección **no necesitó cambio de frontend**.

Cubierto por: mes actual, mes siguiente, mes anterior, rango que cruza el año, rango de un
solo día, bloque fuera de rango, rango invertido (422), rango malformado (422) y ausencia
de rango (cae al mes actual).

## 5. Duración real del evento

`ScheduleController@list` emitía `'end' => $schedule->fecha_cita.'T'.$schedule->hora_cita`,
idéntico a `start`. Toda cita se dibujaba como un evento de duración cero.

`end` ahora deriva de `start + AppointmentOccupancy::minutesFor($cita->duracion_cita)`. No
se hardcodearon 15 minutos en el controlador: se reutiliza la misma política de duración de
MVP-2A, que se extendió a un método compartido precisamente para que el calendario y el
motor no puedan divergir. El orden es: valor almacenado → duración del bloque que lo
contiene → `FALLBACK_MINUTES` (15).

Cubierto con 15, 30, 45 minutos y con una cita heredada sin duración.

## 6. Mejoras adyacentes incluidas

1. **Rango de `list` excluía silenciosamente el primer día pedido.**
   `whereBetween('fecha_cita', [$inicio, $fin])` con objetos `Carbon` genera bindings
   `'2026-10-09 00:00:00'` contra una columna `date` que guarda `'2026-10-09'`. En
   comparación de cadenas `'2026-10-09' >= '2026-10-09 00:00:00'` es **falso**, así que
   toda cita del primer día del rango desaparecía del calendario sin error visible. Se
   corrigió comparando por fecha. Es el defecto que destapó la caracterización.
2. **Validación de entrada** en `availableHours` y en `doctor_schedules` (antes ninguno
   validaba nada y respondían 200 ante entrada basura).

Ambas pertenecen al alcance autorizado (rango, duración, disponibilidad, respuesta de
endpoint, consumidor JS) y ambas van acompañadas de test.

## 7. Solapamiento de bloques — auditado, NO activado

`tests/Feature/Scheduling/DoctorScheduleOverlapAuditTest.php` fija la regla y la consulta
de detección: mismo profesional, misma fecha, rangos que se solapan como intervalos
semiabiertos, expresado con `TimeRange` para que no pueda divergir del motor.

Estado medido hoy: **dos bloques solapados se aceptan sin ningún rechazo**. La validación
al guardar **no** se activó en este incremento, porque los datos heredados pueden contener
ya solapamientos y rechazarlos ahora podría detener trabajo real.

PENDIENTE registrado: verificación READ-ONLY en producción de cuántos solapamientos
existen realmente, antes de decidir si la validación se activa en modo bloqueante o solo
advierte. No se solicitó acceso productivo en esta tarea.

## 8. Enum drift — sigue siendo bloqueante

Producción tiene 10 estados en `appointments.estado_cita`. La migration versionada declara
8: no contiene `PACIENTE_LLEGO` ni `REEVALUACION`.

- No se modificó el enum.
- No se modificó la migration histórica.
- Clasificación: **BLOQUEO ANTES DE PR/DEPLOY DE CAMBIOS QUE DEPENDAN DE RECONSTRUIR BD.**
  Una base reconstruida desde las migrations rechaza dos estados que producción usa a
  diario.
- Propuesta: un incremento posterior de **reconciliación de esquema**, aditivo, probado en
  MySQL sano, que alinee el enum versionado con producción sin alterar registros.

La política de `REEVALUACION` de MVP-2A se mantiene sin cambios: sigue bloqueando de forma
**PROVISIONAL**. MVP-2B no rediseña las reevaluaciones.

## 9. Fuera de alcance en este incremento

Creación de cita nueva, validación atómica al guardar, paciente rápido / DNI / RENIEC,
pre-reserva, pagos, COSTO 0, adicionales, sobreagenda, llamador, UI final Día/Semana/Mes y
rediseño visual completo de horarios.

## 10. Pendiente antes de piloto

1. Validación en **MySQL local sano** (heredada de MVP-1A y MVP-2A, sigue abierta). La
   instancia MariaDB degradada no cuenta como evidencia.
2. Reconciliación del enum versionado con producción (§8).
3. Verificación READ-ONLY de solapamientos reales en producción (§7).
4. Integridad histórica de citas: `ON DELETE CASCADE` heredado en las cinco FK de
   `appointments` (MVP-1B, sin implementar).
5. Validación de choque **al guardar** dentro de la transacción: `AppointmentController@store`
   sigue sin comprobarlo. Consultar disponibilidad no es reservarla de forma atómica.
6. MVP-2C: UX visual de horarios y calendario.
