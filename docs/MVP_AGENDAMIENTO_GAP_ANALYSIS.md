# MVP de agendamiento — GAP analysis AS-IS / objetivo

## 1. Alcance y escala de evaluación

El análisis se limita al código relacionado con agendamiento, pacientes, horarios, precios, adelantos y dependencia con el llamador. No valida el despliegue ni los datos de producción.

Escala solicitada:

- **IMPLEMENTADO Y UTILIZADO:** existe y está conectado a un flujo/ruta del código actual. No equivale a “comprobado en producción”.
- **IMPLEMENTADO PARCIALMENTE:** cubre una parte, pero incumple elementos necesarios del requisito.
- **EXISTE CÓDIGO PERO NO ESTÁ INTEGRADO:** hay piezas aprovechables sin formar el flujo requerido.
- **NO IMPLEMENTADO:** no se encontró representación funcional suficiente.
- **NO SE PUEDE DETERMINAR SIN PRODUCCIÓN:** depende de despliegue, DDL, configuración o uso real no accesible desde el repositorio.

## 2. Resultado ejecutivo

El ERP contiene una base reutilizable: sesión y roles provisionales, pacientes, integración RENIEC, profesionales/servicios/tarifas, horarios fechados, calendario FullCalendar, creación y reprogramación de citas, adelanto con ticket/pago y atribución del usuario creador.

No existe todavía un MVP seguro completo. Las brechas estructurales son:

- ausencia de pre-reserva y confirmación gobernada por adelanto;
- disponibilidad calculada en interfaz, pero no protegida atómicamente al guardar;
- ausencia de sobreagenda autorizada y cita adicional;
- COSTO 0 controlado por checkbox/texto libre y valores financieros confiados al cliente;
- ausencia de responsable actual de la cita separado del creador y de los modificadores;
- agenda sin comparación multi-profesional ni vista Día real;
- RENIEC fragmentado entre dos pantallas y consultado por cada tecla;
- reprogramación sin auditoría de actor/motivo ni validación de conflicto;
- dependencia productiva del llamador todavía no reconciliada.

## 3. Matriz de requisitos

| Capacidad | Estado actual | Evidencia principal | Brecha concreta |
|---|---|---|---|
| Horarios médicos personalizables | **IMPLEMENTADO PARCIALMENTE** | `DoctorSchedule`; `ScheduleController@store/updateDoctorSchedule`; rutas `/admissionist/doctor-schedule/*` | Permite rangos fechados y duración limitada a una lista fija. El alta exige fecha y hardcodea `dia_semana = 1`; no modela claramente plantilla recurrente, bloqueo/excepción, sede, consultorio, vigencia ni conflictos entre horarios. |
| Agenda Mes | **IMPLEMENTADO Y UTILIZADO** | `public/js/admissionist/calendario/calendario.js`, `initialView: dayGridMonth`; `ScheduleController@list` | Debe comprobarse en producción y mejorar densidad/estados, pero existe en flujo enrutado. |
| Agenda Semana | **IMPLEMENTADO Y UTILIZADO** | FullCalendar `timeGridWeek` | Existe selector de semana. No se comprobó uso productivo. |
| Agenda Día | **IMPLEMENTADO PARCIALMENTE** | FullCalendar ofrece `listWeek`, no `timeGridDay` | La tabla de “citas de hoy” no sustituye una agenda Día temporal y comparable. |
| Modo de Agendamiento Rápido | **NO IMPLEMENTADO** | `resources/views/admissionist/appointment/crud/create.blade.php`; modales de paciente y cita | El alta actual usa un modal extenso con datos clínicos/económicos, pago, OCR y observaciones. No existe un recorrido mínimo explícito para COMERCIAL/ADMISION ni dos niveles sobre un único flujo. |
| Alta con paciente mínimo/incompleto | **NO IMPLEMENTADO** | `patient/crud/create.blade.php`; `PatientController@store`; modelo/migration `patients` | La ficha de alta contiene numerosos datos demográficos; no existe estado de completitud/validación ni flujo comprobado para completar después la misma identidad sin duplicarla. |
| Densidad operativa desktop | **IMPLEMENTADO PARCIALMENTE** | `appointment/index.blade.php` reúne filtros, calendario y tabla; altas/ediciones abren modales | Existe contexto compartido, pero el registro rápido no está integrado y la navegación por modales separa información relevante. No hay evaluación de densidad, jerarquía ni productividad repetitiva. |
| Filtros especialidad/profesional | **IMPLEMENTADO Y UTILIZADO** | `resources/views/components/utils/calendar.blade.php`; `filtro-calendario.js`; `ScheduleController@list` | Un filtro por vez; no conserva conjuntos de profesionales ni vistas comparativas. |
| Comparación multi-profesional | **NO IMPLEMENTADO** | No se encontró selector múltiple ni columnas/recursos por profesional | La maqueta sí propone comparación y disponibilidad común, pero su lógica no forma parte del ERP. |
| Tarjetas, estados y semaforización | **IMPLEMENTADO PARCIALMENTE** | `ScheduleController@list` genera eventos; colores fijos por id de especialidad | El color representa especialidades concretas, no un sistema reconciliado de estados; no hay combinación accesible color + texto/etiqueta/icono, leyenda estable, tipo adicional/sobrecupo ni señal inequívoca de pago/pre-reserva. |
| Abrir flujo en fecha no disponible | **IMPLEMENTADO PARCIALMENTE** | `calendario.js@dateClick` abre el modal sobre cualquier fecha futura; `eventClick` abre desde evento disponible | Abrir funciona, pero guardar fuera de horario puede desreferenciar `$horario` nulo. No existe camino explícito de excepción ni permiso específico. |
| Control de disponibilidad/choques | **IMPLEMENTADO PARCIALMENTE** | `DoctorScheduleController@availableHours`; JS `existeCruceCita`; `ScheduleController@generarEventosDisponibles` | La exclusión ocurre antes de guardar y principalmente en cliente. `AppointmentController@store` no vuelve a comprobar choque/intervalo dentro de la transacción, no bloquea filas y no hay restricción que impida doble confirmación concurrente. |
| Cita regular | **IMPLEMENTADO PARCIALMENTE** | `AppointmentController@store`; modelo/migration `appointments` | Se crea directamente como `PROGRAMADO`; no existe pre-reserva ni confirmación por 50 %. El servidor confía en precio/saldo enviados. |
| Sobreagendamiento autorizado | **NO IMPLEMENTADO** | No se encontró tipo, autorización, motivo ni capacidad | La reprogramación incluso puede mover a una hora arbitraria sin control, lo cual es una brecha, no una implementación válida. |
| Cita adicional | **NO IMPLEMENTADO** | No existen campo/tipo/flujo/resultado de adicional | La definición empresarial está confirmada: agenda regular llena, no cupo regular, información expresa, atención no garantizada, espera configurable y gestión por ADMISION o COMERCIAL sin aprobación médica manual obligatoria. Debe respetar horario activo, fin de bloque, carga, duración estimada y capacidad. `cita_doble` y `additional_rates` no la implementan. |
| Pre-reserva temporal | **NO IMPLEMENTADO** | No hay entidad/estado/vencimiento/scheduler de pre-reserva | El código crea de inmediato una cita `PROGRAMADO`, incluso con pago cero. |
| Cita confirmada por 50 % | **NO IMPLEMENTADO** | `AppointmentController@store` acepta `total_pagado = 0`; estado inicial siempre `PROGRAMADO` | No aplica umbral del 50 %, verificación de evidencia ni transición atómica pre-reserva→confirmada. |
| Excepción al adelanto | **IMPLEMENTADO PARCIALMENTE** | `es_exonerado`, `autorizado_por` en `appointments` | Mezcla costo cero con excepción, usa texto libre, no guarda solicitante/aprobador identificable, motivo, fecha ni decisión. |
| DNI: paciente existente | **IMPLEMENTADO Y UTILIZADO** | POST `/api/patient/show`; `Api\patient\PatientController@show`; JS de paciente/cita | La consulta no valida longitud/formato y se dispara por cada tecla. |
| DNI: RENIEC | **IMPLEMENTADO PARCIALMENTE** | `ReniecService@consultar`; `PatientController@show`; `patient.js` procesa `encontrado_reniec` | Sin timeout/reintento/mensaje de error específico observable; el controlador no valida que sean 8 dígitos antes de invocar proveedor. |
| Paciente nuevo dentro del agendamiento | **EXISTE CÓDIGO PERO NO ESTÁ INTEGRADO** | En pantalla de pacientes, guardar abre modal de cita y carga el paciente; `patient.js` líneas del alta→modal | Desde el modal de cita, la respuesta RENIEC no se consume: `appointment.js` solo maneja `message === encontrado`; no abre/embebe el alta al no existir. |
| Fallback manual RENIEC | **IMPLEMENTADO PARCIALMENTE** | Modal de alta permite ingresar datos manuales | La respuesta 404 se convierte en excepción genérica del JS; falta estado UX explícito que continúe el mismo flujo sin perder DNI/datos. |
| Creación de cita | **IMPLEMENTADO Y UTILIZADO** | POST `/admissionist/appointment/store`, solo rol `ADMISION` | Requiere endurecimiento de reglas, validación referencial y concurrencia. |
| Edición/reprogramación | **IMPLEMENTADO PARCIALMENTE** | POST `/admissionist/schedule/update`; `ScheduleController@update`; modal de edición | No valida disponibilidad, choque, transición de estado, motivo obligatorio ni registra modificador/historial. |
| Slot y hora manual | **IMPLEMENTADO PARCIALMENTE** | `calendario.js@eventClick` precarga `hora_cita`; formulario alterna input/select de hora | Puede precargar o editar hora, pero no existe un flujo UX validado para cambios rápidos como 10:00→10:15, navegación por teclado o reprogramación corta; el servidor no vuelve a garantizar disponibilidad. |
| Drag & drop | **NO IMPLEMENTADO** | Calendario actual usa `editable: false` | La maqueta lo muestra, pero debe condicionarse a permiso, disponibilidad, confirmación y auditoría. |
| Usuario que agenda | **IMPLEMENTADO PARCIALMENTE** | `appointments.user_id = auth()->user()->id`; relación `Appointment@user`; `created_at` | Conserva creador en alta, pero el nombre genérico `user_id` no expresa rol histórico y no existe `updated_by` ni historial de modificaciones. |
| Responsable de la cita | **NO IMPLEMENTADO** | `appointments.user_id`; `patients.channel_id`, `interaction_medium_id`; `patients.user_id` | `appointments.user_id` conserva al creador, pero no existe un responsable actual editable ni historial de reasignación. Canal/medio no identifica responsable y `patients.user_id` no puede reutilizarse con esa semántica. |
| COSTO 0 con aprobación | **IMPLEMENTADO PARCIALMENTE** | `/api/appointment/calculated`; checkbox `es_exonerado`; `autorizado_por`; `precio_programado` | Cualquier ADMISION con acceso al alta puede marcar exoneración. No existe el maestro configurable confirmado de aprobadores activos/inactivos ni una aprobación durable. Precio y saldo ocultos pueden alterarse desde cliente y el store los acepta. |
| Adelanto/pago al agendar | **IMPLEMENTADO PARCIALMENTE** | `AppointmentController@store` crea ticket, item y payment en transacción si `total_pagado > 0`; exige caja abierta | No valida el 50 %, no verifica la operación y duplica cifras financieras en cita/voucher/payment. |
| Evidencia de pago | **EXISTE CÓDIGO PERO NO ESTÁ INTEGRADO** | Dropzone + Tesseract en navegador; `numero_operacion` | El `<input type=file>` no tiene `name`; la imagen no se envía ni persiste. OCR solo rellena el número. Texto reconocido y datos se exponen en DOM/consola. |
| Venta/cobro posterior de cita | **IMPLEMENTADO PARCIALMENTE** | `App\Http\Livewire\Sales@updatedBuscarCita/agregarCitaAlCarrito/guardarVenta` | Flujo separado del asistente de agenda; recalcula desde tarifa vigente en lugar de depender inequívocamente del snapshot de cita; no resuelve la responsabilidad actual de la cita ni una futura atribución comercial diferenciada. |
| Sede | **NO IMPLEMENTADO** | `appointments`, `doctor_schedules`, `doctors` no tienen sede en migrations versionadas | El TO-BE multisede y la adicional requieren contexto de sede; el piloto puede operar con una sede verificada, pero debe conservar el concepto. |
| Compatibilidad con llamador | **NO SE PUEDE DETERMINAR SIN PRODUCCIÓN** | `docs/LLAMADOR_AS_IS.md` | Código del llamador lee/escribe directamente `appointments`; falta confirmar flujo desplegado, conexión y esquema real. |

## 4. Evidencia detallada por área

### 4.1 Rutas y autorización

`routes/admision.php` separa lectura de escritura:

- lectura de pacientes, citas, calendario, horarios y disponibilidad: `auth` + roles `ADMISION|RECEPCION|ADMINISTRADOR|COMERCIAL`;
- creación/modificación de pacientes/citas/horarios: `auth` + rol `ADMISION`.

`routes/recepcion.php` mantiene lectura de agenda/pacientes/horarios y sus funciones legítimas de caja/ventas. `routes/api.php` protege búsquedas internas con `internal-api` y roles operativos. Esto contiene el acceso según la matriz provisional de Fase 1, pero no ofrece capacidades granulares para `agenda.crear`, `agenda.sobreagendar`, `agenda.adicional`, `precio.costo_cero.aprobar` o `pago.verificar`.

Las vistas de ADMINISTRADOR y RECEPCIÓN incluyen los modales y JavaScript de alta/edición de ADMISION. El backend bloquea sus escrituras mediante la ruta, pero la interfaz puede mostrar acciones fallidas o confusas. La visibilidad deberá alinearse con capacidades futuras sin sustituir la autorización de servidor.

### 4.2 Horarios y disponibilidad

`doctor_schedules` conserva médico, día semanal, fecha opcional, inicio, fin, duración y estado. Hay dos interpretaciones inconsistentes:

- `ScheduleController@generarEventosDisponibles` admite horario fechado o semanal (`fecha_cita` exacta o `fecha_cita NULL + dia_semana`);
- `DoctorScheduleController@availableHours`, consumido por el modal, solo busca `fecha_cita` exacta;
- el CRUD obliga una fecha y fija `dia_semana` a lunes.

El algoritmo de disponibilidad calcula intersecciones por duración tanto en calendario como en JavaScript. Es reutilizable como regla inicial, pero debe centralizarse en servidor. Hoy dos peticiones simultáneas pueden observar libre el mismo cupo y guardarlo ambas.

`AppointmentController@store` localiza un horario por médico/fecha/rango y usa su duración, pero no trata el caso no encontrado antes de acceder a `duracion_cita`. También evita que el mismo paciente tenga otra cita `PROGRAMADO` con el mismo médico y fecha, sin verificar hora, servicio u otros estados; esto no es protección contra doble reserva del médico.

La actualización de cita no recalcula duración, no comprueba horario ni choque y acepta el estado recibido. La vista contiene opciones cuyo texto y valor no coinciden (`PACIENTE_LLEGO` envía `CONFIRMADO`; `REEVALUACION` envía `ATENDIDO`) y la migration versionada no contiene ambos estados. Debe conciliarse con producción y llamador.

### 4.3 Calendario y referencia UX

El ERP actual ofrece FullCalendar con Mes, Semana y Lista semanal; filtros por especialidad/profesional; eventos ocupados y, al seleccionar un médico, slots disponibles. No ofrece:

- Día como grilla temporal;
- comparación simultánea por columnas de varios médicos;
- disponibilidad común;
- leyenda de estados coherente;
- drag & drop;
- tarjetas que distingan regular, sobreagenda, adicional, pre-reserva y confirmada.

La maqueta interna cubre visualmente Día/Semana/Mes, filtros, comparación, tarjetas, asistente en tres pasos y arrastre. La referencia operativa anterior aporta un principio distinto y complementario: alta densidad de información más rapidez para agendamiento repetitivo. Ninguna debe copiarse literalmente. El MVP debe conservar las intenciones útiles y someter toda interacción a autorización, disponibilidad y control de choque.

**CONFIRMADO POR NEGOCIO:** la experiencia principal desktop debe permitir a un usuario entrenado de COMERCIAL/ADMISION registrar una cita común en pocos segundos, sin fijar aún un SLA numérico. Debe combinar claridad, densidad útil y velocidad, admitir slot/hora manual y semaforizar con color más texto/etiqueta/icono.

**PROPUESTA UX:** una pantalla de trabajo puede integrar filtros/profesionales, calendario, agenda horaria/citas y registro rápido, abriendo detalle completo solo para operaciones avanzadas. Agendamiento rápido y detalle deben usar el mismo caso de uso y no duplicar reglas.

### 4.4 Pacientes y RENIEC

El flujo servidor es: buscar `patients.numero_identidad`; si no existe, invocar `ReniecService`; si hay respuesta, devolver `encontrado_reniec`. `patient.js` entiende ambos resultados y autocompleta el modal de alta. Después del alta, abre el modal de cita y carga el paciente: es una pieza reutilizable.

Problemas actuales:

1. ambos inputs llaman a la API en cada evento `input`, sin debounce ni longitud mínima;
2. una búsqueda parcial puede provocar múltiples llamadas reales a RENIEC;
3. no hay validación previa de DNI ni manejo diferenciado de timeout/proveedor/“no encontrado”;
4. `appointment.js` solo procesa paciente local; ignora `encontrado_reniec`;
5. la pantalla de cita no integra el alta del paciente;
6. las respuestas completas y el DNI se imprimen en consola;
7. el API devuelve el modelo paciente completo, con más PII de la necesaria.

La nueva decisión empresarial añade una brecha funcional: COMERCIAL no debe completar toda la ficha para agendar. El sistema futuro debe distinguir información mínima de agendamiento e información obligatoria de validación presencial. El paciente debe evolucionar sobre la misma identidad desde incompleto/pendiente a completo/validado; los campos y nombres definitivos siguen pendientes. El esquema/código actual no representa explícitamente esa evolución.

### 4.5 Costo, costo cero y adelanto

El precio base proviene de `doctor_services.precio_primera_consulta` o `precio_reconsulta`; luego `additional_rates` suma monto o porcentaje. `calculatedPrice` puede devolver cero si el cliente envía `es_exonerado`.

Hay una inconsistencia de identificadores: el cliente envía el id de `doctor_services`; `calculatedPrice` busca la última cita comparando `appointments.service_id` con ese id, aunque al crear la cita se guarda el id real de `services`. La detección de reconsulta puede ser incorrecta.

El alta confía en `precio_programado`, `total_pagado`, `saldo_pendiente`, `es_exonerado` y `autorizado_por` enviados por navegador. No comprueba que saldo = precio − pagado ni que un pago sea <=/coherente con precio. Si hay adelanto, sí exige caja abierta y crea cita, ticket, línea, correlativo y pago dentro de una transacción con bloqueo de serie; esa infraestructura es reutilizable con conciliación.

COSTO 0 actual es riesgoso:

- checkbox disponible al rol que crea;
- texto libre “Autorizado por”;
- sin solicitante/aprobador identificable, motivo o timestamp específico;
- sin capacidad separada ni doble control;
- sin conservar precio original calculado de forma confiable en servidor.

La captura de pago se procesa con Tesseract en el navegador, pero no se carga al servidor. Solo se conserva `numero_operacion`; por tanto no hay evidencia durable ni verificación.

### 4.6 Creador, responsable de la cita y modificadores

**CONFIRMADO EN CÓDIGO:** `appointments.user_id` se establece desde `auth()` al crear, por lo que el código versionado registra al usuario creador. `created_at` y `updated_at` indican tiempos generales. No se conserva:

- usuario modificador/reprogramador;
- historial de cambios;
- actor que cambia estado;
- aprobadores identificables;
- responsable actual de la cita separado del creador;
- historial de reasignación con responsable anterior/nuevo, actor y fecha/hora.

**CONFIRMADO POR NEGOCIO:** al crear, el responsable de la cita debe iniciar como el usuario autenticado; después puede reasignarse a otro usuario. Creador, responsable y modificador son datos distintos. La reasignación debe auditar responsable anterior/nuevo, usuario que cambia y fecha/hora.

`patients.channel_id` e `interaction_medium_id` pueden aportar origen de captación, pero no responsabilidad de una cita. `patients.user_id` se asigna al operador que ejecuta `store`, incluso al recuperar un paciente existente, de modo que no debe reinterpretarse como responsable de cita ni como owner comercial histórico.

### 4.7 Livewire de ventas

`Sales` permite buscar una cita, agregarla al carrito, emitir/liquidar comprobantes y registrar pagos con el usuario y turno de caja. Es reutilizable como integración posterior, no como implementación del asistente completo de la maqueta. El cálculo de búsqueda usa `doctor_services` y tarifa adicional actuales, mientras la cita ya tiene `precio_programado`; esta duplicación debe reconciliarse antes de afirmar qué importe es canónico.

### 4.8 Definición confirmada de cita adicional

**CONFIRMADO POR NEGOCIO:** la adicional se utiliza con la agenda regular del profesional completa; no constituye cupo regular; exige informar expresamente al paciente; la atención no está garantizada y puede terminar atendido o no atendido. La espera operativa de referencia puede llegar aproximadamente hasta dos horas, pero debe almacenarse/aplicarse como política configurable y nunca como constante dispersa.

**CONFIRMADO POR NEGOCIO:** ADMISION o COMERCIAL pueden gestionar la adicional conforme a autorización. No requiere necesariamente una aprobación manual del profesional. Debe respetar horario activo, fin de bloque, citas regulares, adicionales existentes, duración estimada cuando exista y capacidad operativa; no puede extender artificialmente la jornada.

**PENDIENTE DE NEGOCIO:** máximo por profesional/bloque/día y reglas exactas de cierre/no atención.

El código actual no contiene una marca equivalente, constancia de información, política de espera, capacidad específica de gestión ni resultado propio. `cita_doble` solo extiende duración y una tarifa adicional solo modifica precio.

## 5. Riesgos prioritarios

| Prioridad | Riesgo | Consecuencia |
|---|---|---|
| CRÍTICO potencial | Llamador con rutas no autenticadas que podrían escribir `appointments` productivo | Alteración externa de estados y conflicto con el MVP. Explotabilidad pendiente de producción. |
| ALTO | Confirmación sin control atómico de disponibilidad | Doble reserva o sobreagenda accidental. |
| ALTO | COSTO 0 y precios confiados al navegador | Exoneración o manipulación no autorizada. |
| ALTO | Estado de cita mutable sin transición/auditoría | Pérdida de trazabilidad y estados incompatibles con llamador. |
| ALTO | Nueva lógica financiera sobre cifras duplicadas | Saldo/pago/comprobante inconsistente o cobro duplicado. |
| ALTO | `numero_cita` basado en segundos | Colisión concurrente contra índice único. |
| ALTO | No existe responsable actual de la cita separado del creador | Reasignaciones opacas, seguimiento operativo incorrecto y atribución al usuario equivocado. |
| MEDIO-ALTO | RENIEC por tecla y logging de PII | Consumo externo excesivo, errores de UX y exposición de datos. |
| MEDIO-ALTO | Horarios semanales/fechados interpretados de modo distinto | Slots falsos o ausentes. |
| MEDIO | Vistas muestran acciones que backend no autoriza | Confusión operativa, aunque la ruta esté protegida. |

## 6. Qué puede reutilizarse

- middleware de sesión y matriz provisional como contención inicial;
- catálogos `Doctor`, `Specialty`, `Service`, `DoctorService` y `AdditionalRate`, previa integridad y conciliación;
- `DoctorSchedule` y algoritmos de intersección como fuente de aprendizaje/migración;
- FullCalendar y filtros actuales como base visual;
- búsqueda local de paciente e integración `ReniecService` detrás de controles y fakes;
- alta de paciente que continúa al modal de cita;
- relaciones y snapshot económico de `Appointment` como compatibilidad legada;
- transacción de adelanto, bloqueo de correlativo, voucher, item y payment, tras definir fuente financiera canónica;
- `Sales` para cobro posterior, sin refactorizarlo prematuramente;
- `appointments.user_id` como creador legado;
- `channel_id`/`interaction_medium_id` como datos de origen, no como responsable de cita.

## 7. Cambios requeridos por categoría

### Código/aplicación

- caso de uso único para calcular disponibilidad y confirmar cupo en servidor;
- capacidades separadas para crear, reprogramar, sobreagendar, adicional, aprobar costo cero y verificar pago;
- flujo DNI local→RENIEC→manual→alta→agenda, con debounce, longitud y errores seguros;
- vistas Día/Semana/Mes y comparación multi-profesional;
- operaciones frecuentes de agenda con pocos pasos y conservación de contexto, incluida hora manual, cambios rápidos, paciente inline, responsable, adicional y reprogramación;
- modo rápido y detalle completo como dos presentaciones del mismo caso de uso;
- semaforización accesible con estado textual/iconográfico además del color;
- feedback inmediato PACIENTE REGISTRADO/PACIENTE NUEVO y completitud pendiente;
- validación servidor de precio, saldo, adelanto y transiciones;
- auditoría de modificaciones y aprobaciones;
- integración controlada con cobro/llamador.

### Base de datos — diseño posterior, no implementado aquí

- sede/consultorio en contexto operativo;
- pre-reserva, vencimiento, extensión y estado;
- tipo de agendamiento y datos propios de adicional;
- autorización de sobreagenda/costo cero/excepción de adelanto;
- creador, modificador e historial/eventos;
- responsable actual de la cita e historial de reasignación, separados del creador/modificador;
- maestro configurable de aprobadores de COSTO 0, con estado activo/inactivo y vigencia futura opcional;
- representación futura de completitud/validación de identidad sin duplicar pacientes, después de definir los campos de negocio;
- evidencia y verificación de pago;
- restricción o estrategia de concurrencia para cupos;
- compatibilidad/mapeo de estados del llamador.

### Configuración/operación

- política de duración/extensión de pre-reserva;
- política de espera/capacidad de adicionales;
- límites económicos y regla de autoaprobación de COSTO 0;
- regla exacta de autorización/guardado del sobreagendamiento;
- sede/especialidades/usuarios del piloto;
- timeouts y modo seguro de RENIEC;
- scheduler para expiraciones cuando se implemente.

## 8. Dependencias de producción

No se puede determinar sin evidencia productiva:

- DDL real de `appointments`, enums, índices y duplicación de `hora_llamado`;
- volumen y distribución de citas futuras/estados/importes;
- horarios realmente utilizados (fechados vs semanales);
- usuarios/roles que agendan y exoneran en la práctica;
- valores reales de canales/medios y significado de `patients.user_id` en datos históricos;
- porcentaje de citas con cero, adelanto, número de operación o ticket;
- flujo y versión del llamador desplegados;
- existencia de controles externos sobre sus rutas;
- configuración real de RENIEC y comportamiento observado, sin exponer secretos.

## 9. Dependencia del llamador

Hasta confirmar producción, deben considerarse contrato legado:

- `appointments.id`, `patient_id`, `doctor_id`, `service_id`;
- `fecha_cita`, `duracion_cita`, `turno_cita`;
- `estado_cita` y `updated_at`;
- relaciones con pacientes, médicos y servicios;
- potencialmente timestamps operativos, según el flujo realmente usado.

No se deben renombrar, retirar ni cambiar semántica/enum de estos elementos durante el MVP sin proyección compatible y prueba conjunta. La cita adicional deberá proyectarse al llamador solo cuando exista una decisión explícita sobre si ingresa a espera y en qué momento.

## 10. Orden recomendado de implementación futura

1. Confirmar contrato productivo del llamador, DDL/estados de agenda y roles productivos básicos.
2. Implementar después un maestro configurable de aprobadores y proteger precio/costo cero y acciones excepcionales; no hardcodear profesiones o roles.
3. Establecer una regla servidor única de horarios, bloqueos, disponibilidad y concurrencia.
4. Implementar trazabilidad de creador, responsable de la cita, modificadores y reasignaciones antes de generar métricas.
5. Integrar DNI/paciente nuevo con RENIEC resiliente y fallback manual.
6. Implementar pre-reserva, expiración y confirmación por adelanto/excepción.
7. Implementar agenda Día/Semana/Mes, filtros y comparación multi-profesional.
8. Implementar sobreagendamiento autorizado y cita adicional como flujos distintos.
9. Integrar evidencia/verificación de pago y cobro sin doble contabilización.
10. Adaptar/probar llamador con compatibilidad temporal y ejecutar piloto controlado.

## 11. Decisiones de negocio todavía pendientes

1. ¿Un aprobador de COSTO 0 puede aprobar su propia solicitud y qué límites económicos aplican por aprobador?
2. ¿Cuál es la regla exacta para guardar una selección no disponible como cita regular excepcional o sobreagendamiento y quién la autoriza?
3. ¿Cuál será el límite de adicionales por profesional/bloque/día y cuáles serán sus reglas exactas de cierre/no atención?
4. ¿Cuál es el grupo exacto del piloto en la sede actual: profesionales, usuarios y roles participantes?

**CONFIRMADO POR NEGOCIO y ya no bloqueante:** el tipo de perfiles elegibles para COSTO 0 se resuelve mediante un maestro configurable; ADMISION/COMERCIAL pueden gestionar adicionales sin aprobación médica manual obligatoria; el responsable de la cita inicia como creador y puede reasignarse con auditoría; la calidad operativa UX forma parte del MVP.

**PENDIENTE DE NEGOCIO para diseño detallado del paciente:** lista exacta de datos mínimos para agendar, datos obligatorios para completar/validar y nombres finales de estados de completitud. Esto no invalida el requisito confirmado de permitir agendamiento comercial con identidad mínima.

## 12. Conclusión

El MVP no requiere desechar el ERP actual, pero sí impedir que piezas parciales se interpreten como controles completos. La prioridad técnica futura debe combinar consistencia/autorización de servidor con fluidez operativa verificable. La agenda visual, RENIEC y pagos pueden aprovecharse; el modo rápido, identidad incompleta evolutiva, semaforización accesible, adicional, sobreagenda, pre-reserva, costo cero trazable y responsabilidad de cita requieren diseño o implementación nuevos.
