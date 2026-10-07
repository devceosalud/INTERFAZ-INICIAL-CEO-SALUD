# MVP-2C — Agenda operativa y horarios

> **LOCAL, SIN COMMIT Y NO APROBADO PARA DEPLOY.**

- Rama: `feature/mvp-2c-scheduling-visual-ux`
- Base: `cc30f4e6e6027f1d2194396405f8e3574ac10390` (MVP-2B)
- Base de datos productiva: no utilizada
- Migraciones nuevas: ninguna

MVP-2A construyó el motor de disponibilidad y MVP-2B lo conectó a los endpoints heredados.
MVP-2C consume esas reglas y presenta una estación de trabajo compacta para operar la agenda.
El retrabajo no sustituye ni duplica las reglas de duración, ocupación o disponibilidad.

## 1. Referencia funcional y criterio visual

La captura del sistema anterior se tomó como referencia de flujo, densidad y lectura rápida:
selección de médico, calendario pequeño, preparación de datos y horario visible en una misma
pantalla. No se copió su identidad visual, sus colores intensos ni su distribución exacta.

La reinterpretación usa una estética clínica sobria:

- jerarquía basada en bordes, tipografía y alineación, sin tarjetas decorativas;
- tipografía operativa de 7 a 11 px y controles de 27 px;
- sin emojis;
- semaforización redundante mediante color, borde y texto;
- Día como vista inicial y superficie principal de trabajo;
- Semana como revisión operativa compacta con identificación rápida de citas;
- Mes como resumen y mecanismo de navegación.

Las tres vistas tienen responsabilidades deliberadamente distintas:

- **Día = operación detallada.** Expone la fila clínica completa y concentra la selección
  precisa de intervalos.
- **Semana = revisión operativa con identificación rápida de citas.** Cada cita muestra su
  hora real y el nombre del paciente; las citas con altura suficiente pueden añadir el servicio.
  El resto del contexto continúa en Registro rápido. En citas de 15 o 20 minutos se priorizan
  inicio y paciente; las de 30 minutos mantienen esa lectura compacta; desde 45 minutos puede
  aparecer el servicio. La hora final se revela solo cuando el ancho real del evento lo permite,
  de modo que una cita solapada nunca sacrifica el nombre para mostrar el rango completo.
- **Mes = panorama macro de disponibilidad/carga.** Conserva profesional, libres y ocupadas,
  sin identidad de pacientes.

## 2. Estructura en dos zonas

### Panel operativo izquierdo

El panel ocupa una fracción menor del ancho y concentra tres bloques visibles:

1. **Médicos.** Lista densa, búsqueda, carga `L / O` y selección primaria de un solo médico.
2. **Mini calendario.** Mes navegable siempre visible; seleccionar un día sincroniza fecha,
   feed, agenda y contexto de registro.
3. **Registro rápido.** Prepara médico, fecha, hora, sede, servicio y operador. DNI y estado del
   paciente aparecen como estructura explícitamente deshabilitada. Una cita seleccionada muestra
   su paciente; un intervalo libre declara que todavía no hay paciente. La acción `Completar
   registro` aparece únicamente al seleccionar una cita existente y permanece deshabilitada
   hasta MVP-3; no existe guardado.

El orden operativo resultante es:

`médico → fecha → agenda → intervalo → contexto preparado`

El bloque de registro declara en pantalla que MVP-2C no crea ni modifica citas. De este modo
se representa el flujo futuro sin simular una persistencia inexistente.

### Agenda derecha

La agenda domina el ancho restante. Mantiene:

- eje vertical de horas;
- referencias visuales cada 20 minutos, sin cambiar la duración real de los intervalos;
- filas bajas y separación precisa de intervalos;
- fila clínica compacta `Hora | Citado | Pago | H.C. | Apellidos y nombres` para una cita
  existente, alineada con una cabecera que solo aparece en Día;
- resumen de libres, ocupadas y minutos libres;
- franja compacta del intervalo seleccionado;
- leyenda horizontal, sin inspector derecho permanente.

### Estrategia final de Semana y fondos horarios

Semana continúa usando `timeGridWeek` de FullCalendar. No fue necesario sustituirla por una
grilla propia: el motor conserva correctamente las posiciones `start`/`end`, incluidos inicios
heredados no alineados con la referencia visual de 20 minutos. La corrección se realizó en la
fuente de los fondos, no ocultando globalmente nodos internos de FullCalendar.

La causa de los rectángulos blancos era esta cadena:

1. `AgendaBoardPresenter` entrega cada intervalo disponible como `tipo_contexto = slot_libre`;
2. `renderCalendar()` convertía cada uno en un evento `display: background` con fondo blanco;
3. FullCalendar materializaba cada intervalo como un `div.fc-timegrid-bg-harness` separado.

La implementación final conserva esos intervalos en memoria para selección y Registro rápido,
pero no los agrega al calendario como eventos de fondo. El blanco disponible es el fondo natural
de la grilla. El bundle de FullCalendar no incluye el locale `es`, así que su `firstDay` por
defecto es domingo; Semana fija `firstDay: 1` al renderizar y Mes lo devuelve a 0. El rango
superior ya salía de `AgendaRange` en lunes–domingo, y ahora las columnas usan esos mismos
siete días.

A partir de los `slots` ya resueltos por el motor se fusionan los bloques contiguos y se pinta
únicamente su complemento como `agenda-outside-hours-background`. Ese fondo ocupa el ancho de
la columna, sin margen ni borde de tarjeta, en gris suave semitransparente para que las líneas
horarias sigan viéndose. Puede existir además un fondo temporal para el intervalo seleccionado.

El feed operativo autenticado expone únicamente el identificador interno del paciente, nombre
visible, número heredado de H.C., estado de pago, servicio, estado y responsable cuando existe.
No selecciona ni entrega documento de identidad, teléfono, correo, dirección, contenido de
historia clínica, motivo de consulta, importes ni número externo de cita. El endpoint genérico
de disponibilidad continúa completamente libre de PII.

## 3. Selección y comparación de médicos

Un clic sobre un médico abre una sola agenda. La selección múltiple solo se habilita al activar
`Comparar` y está limitada a 12 profesionales.

Cuando hay dos o más médicos seleccionados, la grilla no crea columnas estrechas ni superpone
intervalos. Se sustituye temporalmente por una tabla de comparación con:

- médico y especialidad;
- intervalos libres y ocupados;
- minutos libres;
- cobertura horaria del periodo.

Desde esa tabla se elige una fila y se abre su agenda individual. Mes conserva su carácter de
resumen; la comparación específica se aplica a Día y Semana.

## 4. Interacción del intervalo

Seleccionar una cita por clic, `Enter` o barra espaciadora, o hacer clic en cualquier punto de
un intervalo disponible, sincroniza:

- estado textual y semáforo;
- médico y especialidad;
- fecha;
- inicio, fin y duración;
- sede;
- paciente e identificador interno cuando ya existe cita;
- servicio, estado de cita, estado de pago y número de H.C. cuando ya existe cita;
- panel de registro rápido;
- comprobación no bloqueante de cruce.

Un intervalo disponible se marca con un contorno lateral discreto como `Slot libre`, limpia
cualquier paciente previamente seleccionado y conserva el contexto para el futuro agendamiento
flash, pero no registra una cita. Una cita existente se marca sin reemplazar su color de estado,
muestra paciente, servicio, estado, pago y H.C., y prepara la acción deshabilitada `Completar registro` para que
MVP-3 continúe con el mismo `patient_id` sin duplicar al paciente.

La rejilla usa `slotDuration` y `slotLabelInterval` de 20 minutos exclusivamente como cadencia
visual. Cada evento conserva el `start`/`end` programado que entrega la Agenda; por ello una cita
reservada por 15, 30 o 45 minutos ocupa una altura proporcional y no modifica
`doctor_schedules.duracion_cita`, `appointments.duracion_cita` ni las reglas de MVP-2A.

Esta duración es **programada o reservada**, no la duración clínica real de la consulta. El
futuro flujo clínico/HCE deberá registrar inicio y cierre efectivos del episodio y calcular por
separado la duración real como `cierre - inicio`. MVP-2C no inventa ese dato, no reinterpreta
históricos y no abre todavía trabajo de Historia Clínica Electrónica.

Pendiente: separar formalmente la duración programada de Agenda y la duración clínica real
cuando se implemente el flujo HCE/llamado. No se añaden ahora columnas de duración real,
inicio de atención ni fin de atención.

## 5. Semaforización

La semántica continúa centralizada en `AgendaLegend`:

| Estado | Tratamiento operativo |
| --- | --- |
| Disponible | fondo blanco + referencia `Disponible` en la leyenda |
| Programada | ámbar + texto `Programada` |
| Confirmada | azul + texto `Confirmada` |
| Ocupada | pizarra + texto `Ocupada` |
| Fuera de horario | gris + texto `Fuera de horario` |

El color nunca es el único mecanismo de interpretación. Las citas son rectángulos operativos,
sin píldoras. La disponibilidad se percibe como fondo blanco y no repite texto en cada intervalo;
fuera del horario configurado se conserva un fondo gris neutro.

## 6. Compatibilidad técnica preservada

Se mantienen:

- `DoctorAvailabilityService` como fuente única de disponibilidad;
- `TimeRange` como regla de solapamiento temporal;
- `AppointmentOccupancy` como política de ocupación y duración;
- contratos JSON del feed y advertencia de cruces;
- permisos, feature flag y rutas de MVP-2A/MVP-2B;
- formularios y persistencia heredados de horarios;
- FullCalendar 5.5.1 desde activos ya versionados, sin CDN ni licencia premium.
- nuevo shell global de navegación superior, documentado por separado, que elimina la columna
  lateral sin duplicar layouts ni cambiar visibilidad por rol.

No se añadió ninguna migración ni se modificó producción.

### Corrección de scroll en Validar cruce

La causa no era el selector nativo de hora por sí solo, sino **dos contenedores de
scroll anidados** más el `scrollIntoView` que dispara el picker de Windows/Chrome.

1. `.agenda-operations` desplazaba toda la columna izquierda.
2. `.agenda-doctor-list` también era un overflow independiente.
3. Al abrir `input[type=time]`, el navegador desplazaba el ancestro equivocado y, al
   cerrar el picker, el panel quedaba atrapado al fondo: no se podía volver a
   «Registro rápido».

Corrección:

- un único scroller vertical (`.agenda-operations`);
- la lista de médicos ya no tiene overflow propio;
- `scroll-margin` en los campos de hora;
- al perder el foco del picker se fuerza un reflow que restaura `overflow-y`.

No se sustituyó el input nativo. Abrir/cerrar el selector, comprobar el cruce y
desplazarse de inmediato hacia Registro rápido debe funcionar en el mismo panel.

## 7. Rendimiento y privacidad

`DoctorAvailabilityService::forRange()` carga bloques y citas una vez por rango y compone los
resultados en memoria. `OperationalAgendaAppointmentService` realiza la consulta separada y
acotada que aporta identidad mínima solo al feed operativo. Ambas respetan el rango solicitado.
El test de rendimiento compara el costo de un mes contra un día con el mismo conjunto de
profesionales.

El feed limita la comparación explícita a 12 médicos y la carga inicial a 8. La PII mínima se
entrega solo en Día/Semana y solo por la ruta operativa protegida
`/scheduling-mvp/agenda/feed`; Mes continúa siendo resumen. El endpoint genérico
`/scheduling-mvp/availability` no contiene paciente, H.C. ni pago.

Las rutas conservan, en este orden:

1. feature flag `scheduling-mvp`;
2. autenticación;
3. permiso `appointment.mvp.access`;
4. permiso `appointment.view`.

## 8. Validación local

La validación visual se ejecuta contra SQLite desechable, datos ficticios y servicios externos
anulados. Las resoluciones objetivo son 1920 × 1080, 1600 × 900, 1366 × 768, 1024 × 768 y
390 × 844 al 100 %.

Escenarios mínimos:

- Día con un médico;
- mini calendario visible y cambio de fecha;
- panel de registro rápido;
- agenda completa del día;
- cita con paciente y servicio visibles;
- varias citas consecutivas;
- intervalo seleccionado y contexto prellenado;
- cita seleccionada y contexto del mismo paciente;
- herramienta de cruce abierta, selector de hora y retorno del scroll;
- rejilla 07:00, 07:20, 07:40… con eventos de duración programada y geometría exacta;
- comparación de varios médicos;
- Semana;
- Mes.
- shell desktop cerrado y con Citas abierto;
- shell tablet/móvil cerrado y con drawer abierto;
- agenda móvil con intervalo seleccionado.

Las capturas se conservan como evidencia de revisión fuera del repositorio. No se versionan
datos ni imágenes de pacientes.

### Aprovechamiento del viewport

En escritorio, Agenda usa prácticamente toda la superficie disponible bajo la navegación
global. El contenedor exterior conserva solo una separación mínima, mientras médicos,
calendario, registro rápido y agenda horaria administran su desplazamiento dentro de la
superficie operativa. Se evita presentar el módulo como una tarjeta grande y no se fija una
altura para una resolución concreta: la altura se deriva del viewport y del encabezado global.

El refinamiento visual final amplía la columna operativa de 302 a 350 px en escritorio amplio
y de 285 a 330 px hasta 1450 px. Los tres bloques de la izquierda permanecen visibles mediante
scroll independiente en médicos y en el cuerpo de registro rápido. La separación entre bloques
es de 4 px con divisores discretos, sin convertirlos en tarjetas decorativas.

En Día, la lectura horizontal real es `Hora | Citado | Pago | H.C. | Apellidos y nombres`.
`Citado` muestra `Sí` únicamente en una cita real. `Pago` proviene sin inferencias de
`appointments.estado_pagado`. H.C. proviene de `patients.historia_clinica`, fuente utilizada por
el alta y las vistas heredadas; si ese campo está vacío se presenta `—`. No se usa `patient_id`
como H.C. ni se eligió `historia_clinica_nueva`, cuyo uso operativo no está demostrado. Los
apellidos se presentan antes de los nombres. La rejilla mantiene una altura compacta que permite
leer una cita real de 15 minutos; el scroll interno absorbe el rango restante sin modificar
`hora_cita`, `duracion_cita` ni los límites reales de los eventos.

### Flujo heredado de completado investigado

Existe el listado `GET /admissionist/patient` y la actualización heredada
`PUT /admissionist/patient/udpate` (`admissionit.patient.update`), limitada actualmente al rol
`ADMISION`. El método `PatientController::update()` recibe un `id` en el formulario y actualiza
el mismo paciente, por lo que conceptualmente podría conservar el `patient_id`. No se conecta
desde Agenda en MVP-2C: no es una ruta de recurso dedicada, conserva el error tipográfico
`udpate`, no valida la existencia del modelo antes de actualizarlo y requiere revisar el contrato
completo y la autorización del futuro flujo. El botón queda preparado y deshabilitado para MVP-3.

### Transición de rutas

La ruta scheduling-mvp es temporal de desarrollo/piloto y no representa la URL final del módulo.

Estado actual:

- `/admissionist/appointment` continúa siendo el módulo heredado funcional;
- `/scheduling-mvp/agenda` contiene la nueva Agenda todavía en desarrollo y piloto.

Transición propuesta, únicamente después de la aprobación y de completar los flujos
funcionales:

1. la nueva Agenda pasa a ser la entrada principal de Citas;
2. el módulo heredado permanece temporalmente como fallback durante el piloto;
3. el módulo heredado se retira después de la validación operativa.

MVP-2C no introduce redirects ni sustituye ninguna de las rutas actuales.

## 9. Fuera de alcance

No se implementaron creación definitiva de citas, pre-reservas, pagos, COSTO 0, citas
adicionales, sobreagendamiento, integración con llamador, DNI/RENIEC ni cambios del enum
productivo.

### Continuidad del MVP empresarial

El diseño mantiene espacio para los requisitos confirmados, sin implementarlos antes de tiempo:

1. horarios médicos personalizados;
2. vista macro Día/Semana/Mes;
3. DNI con resultado paciente existente/no existente;
4. registro de paciente nuevo dentro del flujo de agenda;
5. apertura/agendamiento autorizado aunque la fecha no estuviera disponible originalmente;
6. aprobadores para COSTO 0;
7. función ADICIONALES;
8. registro de quién agenda y responsable comercial;
9. experiencia rápida y usable.

MVP-2C resuelve la interfaz operativa de agenda. La verificación por DNI, paciente rápido y
`Completar registro` funcional pertenecen a **MVP-3 — DNI + paciente rápido/completar registro**.
La evolución del menú lateral hacia una cabecera compacta transversal se registra por separado
como **ERP SHELL / NAVEGACIÓN GLOBAL**.

## 10. Pendientes antes de piloto

1. Validación en MySQL local sano, además de SQLite.
2. Prueba operativa con usuarias de ADMISION y COMERCIAL.
3. Reconciliación del enum versionado de `appointments` antes de reconstruir una base.
4. Validación de choque dentro de la transacción que eventualmente reserve un horario.
5. Decidir si la advertencia de cruces de horarios pasa a ser bloqueante.
6. Resolver o aislar el error heredado de `selectpicker` del shell global.
7. Implementar en MVP-3 la continuidad segura del paciente y la acción `Completar registro`.
