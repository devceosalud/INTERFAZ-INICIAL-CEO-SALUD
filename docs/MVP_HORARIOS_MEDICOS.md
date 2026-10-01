# MVP Horarios Médicos — diseño operativo e integración con Agenda

## Estado

Implementación local sin commit. No se modificó producción ni se introdujeron migrations.

## AS-IS confirmado

`doctor_schedules` representa un bloque de operación mediante:

- `doctor_id`: profesional;
- `site_id`: sede opcional, incorporada por la fundación de Scheduling;
- `dia_semana`: día ISO de 1 (lunes) a 7 (domingo);
- `fecha_cita`: fecha concreta o `NULL`;
- `hora_inicio` / `hora_fin`: extensión del bloque;
- `duracion_cita`: duración programada por cita y cadencia de slots;
- `estado`: activación lógica del bloque.

La semántica efectiva ya implementada por `DoctorAvailabilityService` es:

- `fecha_cita` concreta: bloque válido solo para esa fecha;
- `fecha_cita = NULL` + `dia_semana`: bloque recurrente semanal;
- varias filas para la misma fecha/día: varios bloques del mismo médico;
- cada bloque genera slots desde `hora_inicio`, avanzando por `duracion_cita`, sin exceder `hora_fin`;
- las citas existentes se superponen después y convierten los slots correspondientes en ocupados.

La UI heredada solo creaba fechas concretas y guardaba siempre `dia_semana = 1`. Por ello no
exponía correctamente la recurrencia que el motor sí conoce.

## Arquitectura aplicada

La persistencia continúa siendo exclusivamente `doctor_schedules`. No existe una segunda tabla
ni un segundo motor de disponibilidad.

- `DoctorScheduleWorkspaceController`: renderiza el workspace y materializa bloques recurrentes
  para el rango visual Día/Semana/Mes.
- Los endpoints heredados de alta, edición e inactivación conservan sus rutas y escriben en
  `doctor_schedules`.
- `DoctorAvailabilityService` sigue siendo la única fuente de disponibilidad efectiva para
  Agenda.
- `DoctorScheduleImpactService` es exclusivamente de lectura: identifica citas que quedarían
  fuera del bloque propuesto y nunca las modifica.

## Superficies

### Semana

Vista inicial y principal para editar. Usa grilla temporal, colores estables por médico, nombre
visible y cadencia. Permite selección, drag y resize, pero todo cambio requiere confirmación.

### Día

Detalle temporal. Con un médico filtrado muestra únicamente sus bloques; con “Todos” permite
compararlos mediante color estable, nombre visible y leyenda.

### Mes

Resumen macro. Muestra rango horario y médico; no presenta slots individuales. Al seleccionar
un día se abre la vista Día.

## Programación masiva soportada

1. **Solo este día:** una fila con fecha concreta.
2. **Días seleccionados de la semana:** una fila concreta e independiente por día seleccionado.
3. **Patrón semanal:** una fila por weekday con `fecha_cita = NULL`; recurre sin fecha final.

Los bloques concretos creados masivamente pueden editarse individualmente porque son filas
independientes. Los bloques semanales se editan como patrón de ese weekday.

## Confirmaciones e impacto

Crear varios bloques, editar, mover, redimensionar e inactivar requieren confirmación explícita.
Antes de editar o inactivar se consultan citas activas cubiertas por el bloque. La advertencia
expone solo número de cita, fecha, hora y estado. No mueve, cancela ni elimina citas.

## Limitaciones reales — Horarios MVP-B

El esquema actual no puede representar correctamente:

- ausencia por fecha que suprima una ocurrencia semanal;
- horario excepcional que reemplace, en vez de sumar, al patrón base;
- vigencia desde/hasta de un patrón;
- agrupación de las filas creadas como una sola programación masiva;
- edición “solo esta fecha” de una fila recurrente;
- auditoría persistida de autor, antes/después, fecha o patrón afectado.

Por ello la fórmula futura sigue siendo:

`programación base + ausencias + horarios especiales = disponibilidad efectiva`.

En MVP-A los controles de ausencia y horario excepcional son visibles pero están deshabilitados;
no se simula persistencia.

## Duración

`duracion_cita` significa **duración programada por cita**. Define la cadencia ofrecida por
Agenda. No representa la duración clínica real, que en el futuro deberá calcularse entre inicio
y cierre efectivo de la atención/HCE.

## Autorización actual

- `ADMISION`: lectura y escritura de horarios.
- `RECEPCION`, `ADMINISTRADOR`, `COMERCIAL`: lectura únicamente según la matriz provisional.
- No existe rol `DOCTOR` confirmado ni relación persistida `users ↔ doctors`; no se otorgaron
  permisos nuevos ni se inventó esa asociación.

## Dependencia de esquema local

El workspace requiere las migrations ya versionadas que crean `sites` y agregan `site_id` a
`doctor_schedules`. La MariaDB heredada auditada todavía no las tiene aplicadas. Esta tarea no
las ejecuta ni modifica.
