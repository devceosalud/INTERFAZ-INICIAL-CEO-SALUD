# MVP de agendamiento — requisitos empresariales

## 1. Propósito y límites

Este documento define el alcance funcional prioritario del piloto/MVP de agendamiento de citas de CEO Salud. Es una especificación documental: no define tablas, migrations, endpoints, clases ni contrato técnico con el llamador.

Fuentes utilizadas:

- decisiones funcionales ya registradas en Fase 3;
- arquitectura TO-BE aprobada provisionalmente en Fase 4;
- código versionado del ERP en la rama de verificación;
- auditoría AS-IS del llamador;
- maqueta `ERP_Mockup_V8.OK.v2_Wilson-Cl.html`, tratada únicamente como **REFERENCIA UX INTERNA**;
- capturas descritas por Rodrigo de un sistema anterior de clínica, tratadas únicamente como **REFERENCIA OPERATIVA EXTERNA/ANTERIOR**, sin copiar diseño ni datos identificables.

Clasificación de cada enunciado:

- **CONFIRMADO POR NEGOCIO:** obligación expresamente indicada por CEO Salud/Rodrigo.
- **CONFIRMADO EN CÓDIGO:** comportamiento demostrado por inspección del repositorio; no equivale a validación productiva.
- **PROPUESTA TÉCNICA:** precisión recomendada para volver verificable el requisito sin atribuirla a una decisión empresarial.
- **PENDIENTE DE NEGOCIO:** decisión empresarial que no debe inferirse del código ni de la maqueta.
- **PENDIENTE DE PRODUCCIÓN:** hecho que exige evidencia sanitizada del entorno desplegado.

## 2. Objetivo del MVP

Permitir que personal autorizado consulte una agenda médica clara y densa, encuentre o registre al paciente y programe una cita común en pocos segundos, con mínima navegación y sin perder el contexto visual. El mismo flujo debe admitir excepciones controladas, conservar creador y responsable y dejar trazabilidad suficiente para continuar pago y atención sin romper la compatibilidad necesaria con el llamador actual.

## 3. Requisitos funcionales prioritarios

| ID | Requisito | Clasificación |
|---|---|---|
| MVP-AG-001 | Configurar horarios por profesional, fecha o recurrencia aplicable, duración de cupo y recursos organizativos necesarios. | CONFIRMADO POR NEGOCIO |
| MVP-AG-002 | Consultar la agenda en vistas Día, Semana y Mes. | CONFIRMADO POR NEGOCIO |
| MVP-AG-003 | Filtrar por especialidad y profesional y comparar simultáneamente diferentes profesionales. | CONFIRMADO POR NEGOCIO |
| MVP-AG-004 | Representar citas y disponibilidad mediante tarjetas/estados visuales inequívocos. | CONFIRMADO POR NEGOCIO + REFERENCIA UX |
| MVP-AG-005 | Iniciar el agendamiento desde una fecha/horario visible aunque aparezca no disponible; la apertura del flujo no concede por sí sola permiso para confirmar una excepción. | CONFIRMADO POR NEGOCIO |
| MVP-AG-006 | Buscar primero por DNI en el registro local. | CONFIRMADO POR NEGOCIO |
| MVP-AG-007 | Si el paciente no existe, consultar RENIEC cuando corresponda, autocompletar los datos obtenidos y permitir completar los faltantes. | CONFIRMADO POR NEGOCIO |
| MVP-AG-008 | Si RENIEC no responde o no devuelve datos, permitir registro manual sin abandonar el flujo de agendamiento. | CONFIRMADO POR NEGOCIO |
| MVP-AG-009 | Registrar al paciente nuevo y continuar en el mismo flujo con la programación. | CONFIRMADO POR NEGOCIO |
| MVP-AG-010 | Distinguir disponibilidad, pre-reserva temporal y cita confirmada. | CONFIRMADO POR NEGOCIO |
| MVP-AG-011 | Usar 15 minutos como duración predeterminada configurable de pre-reserva; liberar al vencer sin adelanto validado y auditar toda extensión autorizada. | PROPUESTA TÉCNICA APROBADA PROVISIONALMENTE EN FASE 3 |
| MVP-AG-012 | Como regla general, confirmar la cita al acreditar el adelanto del 50 %; una excepción requiere autorización separada y trazable. | CONFIRMADO POR NEGOCIO |
| MVP-AG-013 | Crear, modificar y reprogramar citas solo con capacidades autorizadas y conservar actor, fecha/hora, motivo y cambios relevantes. | CONFIRMADO POR NEGOCIO / PROPUESTA TÉCNICA |
| MVP-AG-014 | Distinguir el sobreagendamiento autorizado de la cita regular y de la adicional; abrir o seleccionar un horario no disponible no determina por sí mismo cuál de esas figuras se guardará. | CONFIRMADO POR NEGOCIO en la distinción; regla exacta de autorización/guardado PENDIENTE DE NEGOCIO |
| MVP-AG-015 | Registrar una cita **ADICIONAL** cuando la agenda regular esté llena, distinguiéndola de un cupo regular y de un sobreagendamiento. | CONFIRMADO POR NEGOCIO |
| MVP-AG-016 | Antes de registrar una cita adicional, dejar constancia de que se informó al paciente sobre espera aproximada configurable y atención no garantizada. | CONFIRMADO POR NEGOCIO |
| MVP-AG-017 | Conservar para la adicional: profesional, servicio, fecha, sede, registrador, motivo/condición, constancia de información, llegada, eventual atención y resultado final. | CONFIRMADO POR NEGOCIO |
| MVP-AG-018 | Evaluar como resultados provisionales de adicional: ATENDIDO, NO ATENDIDO, RETIRADO y REPROGRAMADO, sin convertirlos aún en catálogo definitivo. | PENDIENTE DE NEGOCIO |
| MVP-AG-019 | COSTO 0 debe requerir solicitante, aprobador autorizado, motivo, fecha/hora, importe original, importe aprobado y auditoría. | CONFIRMADO POR NEGOCIO |
| MVP-AG-020 | Administrar los aprobadores de COSTO 0 mediante una lista/maestro configurable de personas o usuarios autorizados, activos/inactivos y con vigencia futura opcional; no hardcodearlos por rol ni sustituir identidad con texto libre. | CONFIRMADO POR NEGOCIO |
| MVP-AG-021 | Conservar por separado creador, responsable actual de la cita y usuarios que modifican/reprograman. | CONFIRMADO POR NEGOCIO |
| MVP-AG-022 | Al crear, asignar como responsable de la cita al usuario autenticado; permitir después reasignarlo a otro usuario autorizado. | CONFIRMADO POR NEGOCIO |
| MVP-AG-023 | Auditar toda reasignación con responsable anterior, responsable nuevo, usuario que efectuó el cambio y fecha/hora. El canal/medio de captación permanece como concepto distinto. | CONFIRMADO POR NEGOCIO |
| MVP-AG-024 | Mantener trazabilidad entre cita, adelanto/pago, obligación/venta y comprobante sin usar uno como sustituto semántico de otro. | PROPUESTA TÉCNICA derivada del Blueprint |
| MVP-AG-025 | Preservar durante el piloto los campos/estados consumidos por el llamador o proporcionar compatibilidad comprobada antes de cambiarlos. | PROPUESTA TÉCNICA obligatoria por dependencia AS-IS |
| MVP-AG-026 | Permitir que ADMISION o COMERCIAL gestionen citas adicionales dentro de su autorización, sin exigir por defecto aprobación manual del profesional y sin extender artificialmente su bloque de trabajo. | CONFIRMADO POR NEGOCIO |
| MVP-AG-027 | Evaluar una adicional contra horario activo, fin del bloque, citas regulares, adicionales existentes, duración estimada del servicio cuando exista y capacidad operativa. | CONFIRMADO POR NEGOCIO / PROPUESTA TÉCNICA para volver verificable la regla |
| MVP-AG-028 | Optimizar las operaciones frecuentes de agenda para ejecutarse con pocos pasos y sin perder contexto: hora manual, cambio de hora/fecha/profesional, búsqueda/alta de paciente, cambio de responsable, adicional, apertura de horario no disponible y reprogramación. | CONFIRMADO POR NEGOCIO |
| MVP-AG-029 | Configurar el grupo del piloto sin hardcodear profesionales, usuarios, roles ni funciones exclusivas; el grupo exacto se confirmará antes de UAT/activación. | CONFIRMADO POR NEGOCIO en escalabilidad; grupo exacto PENDIENTE DE NEGOCIO |
| MVP-AG-030 | Ofrecer un **Modo de Agendamiento Rápido** para COMERCIAL, ADMISION y otros usuarios repetitivos, usando la información mínima suficiente y sin abandonar constantemente la agenda. | CONFIRMADO POR NEGOCIO |
| MVP-AG-031 | Al ingresar DNI, mostrar feedback inmediato y claro que distinga paciente registrado de paciente nuevo; consultar RENIEC solo cuando corresponda. | CONFIRMADO POR NEGOCIO |
| MVP-AG-032 | Permitir agendar comercialmente con identidad mínima, señalando que el paciente está incompleto o pendiente de validación, sin exigir toda la ficha demográfica. | CONFIRMADO POR NEGOCIO; nombres definitivos de estados PENDIENTES |
| MVP-AG-033 | Completar o validar presencialmente la misma identidad del paciente, sin crear un segundo paciente. | CONFIRMADO POR NEGOCIO |
| MVP-AG-034 | Priorizar en desktop una agenda de alta densidad útil que combine profesionales, fecha, horas, citas, estados, pago y registro rápido con jerarquía y legibilidad. | CONFIRMADO POR NEGOCIO |
| MVP-AG-035 | Permitir seleccionar hora desde un slot y escribir/cambiar manualmente la hora con rapidez; los intervalos visuales deben ser configurables y 15 minutos no es una regla universal. | CONFIRMADO POR NEGOCIO |
| MVP-AG-036 | Semaforizar estados mediante color más texto, etiqueta o icono, sin depender solo del color ni cerrar todavía el enum o la paleta definitivos. | CONFIRMADO POR NEGOCIO |
| MVP-AG-037 | Evaluar una pantalla desktop integrada con zonas de filtros, calendario, agenda horaria y agendamiento rápido, manteniendo acceso al detalle completo. | PROPUESTA UX |
| MVP-AG-038 | Usar un solo concepto/caso de uso de cita detrás del agendamiento rápido y el detalle completo; no duplicar reglas, validaciones ni persistencia. | PROPUESTA TÉCNICA derivada del requisito operativo |
| MVP-AG-039 | Mantener visibles o consultables creado por, responsable actual, última modificación por, tipo y estado, sin sobrecargar el formulario rápido. | CONFIRMADO POR NEGOCIO |

## 4. Tipos de agendamiento que no son equivalentes

### 4.1 Cita regular

Ocupa un cupo producido por un horario médico válido. Su confirmación respeta disponibilidad, duración, política de adelanto y concurrencia.

### 4.2 Sobreagendamiento autorizado

Figura distinta de la cita regular y de la adicional para resolver, mediante una excepción trazable, un conflicto con la capacidad ordinaria o la disponibilidad. Abrir el formulario sobre un espacio no disponible no autoriza ni clasifica automáticamente el guardado. La regla exacta para convertir ese intento en cita regular válida o en sobreagendamiento autorizado permanece **PENDIENTE DE NEGOCIO**.

### 4.3 Cita adicional

Solicitud registrada cuando la agenda normal ya está llena. No crea ni aparenta un cupo regular y no garantiza atención. Debe:

- comunicar al paciente la condición antes del registro;
- comunicar que el tiempo de espera operativo de referencia puede llegar aproximadamente hasta dos horas;
- tratar ese tiempo como política configurable, sin hardcodear dos horas;
- conservar su propio resultado operativo;
- vincularse al profesional, servicio, fecha y sede;
- respetar el horario real del profesional y el final de su bloque, sin prolongarlo artificialmente;
- evaluar citas regulares, adicionales existentes, duración estimada del servicio cuando esté disponible y capacidad operativa;
- poder ingresar al flujo de llegada/atención si finalmente es aceptada.

**CONFIRMADO POR NEGOCIO:** se usa cuando la agenda regular del profesional está completa; no es cupo regular; el paciente debe ser informado expresamente; la atención no está garantizada y puede concluir atendido o no atendido. ADMISION o COMERCIAL pueden gestionarla conforme a autorización. No requiere necesariamente aprobación manual del profesional.

**PENDIENTE DE NEGOCIO:** máximo por profesional/bloque/día y reglas exactas de cierre/no atención.

`cita_doble` (duplicar duración), `additional_rate` (ajuste de precio) y exoneración/costo cero son conceptos distintos de una cita adicional.

## 5. Actores y separación de responsabilidades

| Actor conceptual | Responsabilidad en el MVP |
|---|---|
| Usuario creador | Ejecuta el alta; procede de la sesión autenticada y queda registrado de forma inmutable como creador. |
| Responsable de la cita | Se asigna inicialmente al creador y puede reasignarse a otro usuario con auditoría. Es distinto del creador y del modificador. |
| Usuario modificador | Reprograma o modifica y queda registrado sin borrar al creador. |
| Canal/medio de captación | Describe cómo llegó la solicitud; no sustituye la identidad del responsable de la cita. |
| Verificador de pago | Confirma que la evidencia corresponde a un pago real; no obtiene por ello permiso de excepción. |
| Aprobador de costo cero | Persona o usuario habilitado en el maestro configurable; puede ser profesional, Comercial, gerente u otro usuario autorizado. |
| Aprobador de sobreagenda | Autoriza el conflicto de capacidad; puede o no coincidir con el aprobador de costo cero según decisión empresarial. |
| Aprobador de excepción al adelanto | Permite confirmar sin el 50 %; es una capacidad distinta de verificar pago. |

## 6. Flujo UX de referencia

**CONFIRMADO POR NEGOCIO:** la calidad operativa de UX forma parte del MVP. La maqueta y el sistema anterior son referencias de intención, no diseños que deban copiarse. El criterio rector es:

> Un usuario entrenado de Comercial/Admisión debe poder registrar correctamente una cita común en pocos segundos, con el mínimo de navegación y sin perder el contexto visual de la agenda.

No se fija todavía un SLA en segundos. El flujo debe favorecer operaciones pequeñas y frecuentes con pocos pasos, retroalimentación clara, alta densidad útil y conservación del contexto.

Patrones funcionales requeridos:

- navegación temporal y vistas Día/Semana/Mes;
- filtro por especialidad/profesional;
- comparación de profesionales y espacios comunes;
- tarjetas con hora, paciente, profesional, servicio, costo y estado;
- asistente Paciente → Programación → Pago/Facturación;
- creación, edición y reprogramación desde agenda;
- ingreso manual de hora y cambio rápido de hora, fecha o profesional;
- búsqueda y creación de paciente dentro del mismo flujo;
- cambio de responsable y registro de adicional sin abandonar la agenda;
- apertura controlada de espacios no disponibles, distinguiendo disponibilidad, pago y tipo de excepción;
- captura de adelanto, medio y número de operación;
- identificación visible de disponibilidad y condición de pago.

### 6.1 Propuesta UX de pantalla de trabajo

- **Zona 1:** profesionales, especialidades y filtros;
- **Zona 2:** calendario Día, Semana y Mes;
- **Zona 3:** agenda horaria, slots y citas;
- **Zona 4:** agendamiento rápido.

La composición definitiva queda para diseño UX. La propuesta debe equilibrar claridad, densidad útil y velocidad, priorizar desktop para Comercial/Admisión y evitar tarjetas sobredimensionadas cuando oculten citas relevantes.

### 6.2 Dos niveles de interacción, un solo flujo

- **Agendamiento rápido:** DNI, identidad mínima, profesional, fecha, hora, servicio/tipo, responsable y condición básica de cita/pago.
- **Detalle completo:** pagos/evidencia, COSTO 0, adicionales, aprobaciones, historial, auditoría, reprogramaciones, observaciones y datos avanzados.

Ambos niveles aplican las mismas reglas de negocio y seguridad. El modo rápido no es un atajo para omitir autorizaciones, disponibilidad, concurrencia o trazabilidad.

### 6.3 Semaforización conceptual

La agenda debe diferenciar, entre otros, disponible, pre-reserva, confirmada, pendiente de pago, pagada, adicional, paciente llegó, en espera, en consulta, atendida, cancelada y no atendida. Esta lista no constituye enum técnico. Paleta, términos definitivos y transiciones se cerrarán después de reconciliar producción, ERP heredado y llamador.

La duración provisional de 15 minutos de la pre-reserva (MVP-AG-011) es un plazo de retención y no debe confundirse con el intervalo visual del calendario ni con la duración de la atención.

No se adoptan de la maqueta como reglas:

- datos, usuarios o precios de ejemplo;
- lógica JavaScript, contraseñas embebidas ni auditoría simulada;
- confirmar sin validar adelanto;
- arrastrar una cita sin autorización, control de conflicto y motivo;
- considerar el indicador visual de agenda llena como regla de capacidad;
- asumir que una sola sede o una tarifa hardcodeada es el modelo futuro.

## 7. Información mínima que debe conservarse

### Cita/pre-reserva

- paciente;
- profesional y servicio;
- sede y, cuando corresponda, consultorio;
- fecha, hora, duración y tipo de agendamiento;
- estado y transiciones;
- origen/canal de solicitud;
- creador, responsable actual y modificadores;
- historial de reasignaciones del responsable;
- política de precio aplicada;
- vínculo a pago/adelanto y a excepción, si existe;
- motivos de cancelación, reprogramación o excepción.

### Paciente en agendamiento rápido

Debe distinguirse documentalmente entre:

- **mínimos para agendar:** identidad suficiente para localizar o crear de forma inequívoca al paciente y datos estrictamente necesarios para la cita; la lista definitiva permanece **PENDIENTE DE NEGOCIO**;
- **obligatorios para completar/validar:** datos demográficos y de contacto que ADMISION verificará o completará presencialmente; la lista definitiva permanece **PENDIENTE DE NEGOCIO**.

El paciente nuevo conserva una única identidad que evoluciona conceptualmente de incompleta/pendiente de validación a completa/validada. Estos nombres no son estados técnicos definitivos.

### Aprobación sensible

- tipo de aprobación;
- solicitante;
- aprobador identificable;
- motivo;
- fecha/hora;
- valor anterior y aprobado;
- objeto afectado;
- resultado de la decisión.

### Cita adicional

- todos los datos comunes de cita;
- motivo/condición de adicional;
- versión o valor de la política de espera comunicada;
- constancia de información al paciente;
- llegada;
- decisión operativa de ingreso a espera/atención, cuando ocurra;
- eventual atención;
- resultado final.

## 8. Reglas no negociables de seguridad y consistencia

1. Toda autorización se valida en backend; ocultar botones no es autorización.
2. Disponibilidad se recalcula al confirmar, no se confía en datos enviados por el navegador.
3. Dos solicitudes concurrentes no pueden confirmar el mismo cupo regular incompatible.
4. Precio, saldo, costo cero y adelanto se calculan/verifican en servidor.
5. El actor autenticado no se recibe como campo editable del cliente.
6. Una aprobación no puede representarse solo por un nombre escrito libremente.
7. El archivo/evidencia de pago requiere custodia, acceso y trazabilidad; OCR no sustituye verificación.
8. La agenda no debe exponer DNI completo, evidencia de pago ni PII innecesaria.
9. Los cambios compatibles con el llamador se introducen después de confirmar qué flujo está desplegado.

## 9. Fuera de alcance de esta subfase

- implementación de código o interfaz;
- definición de tablas o migrations;
- contrato técnico definitivo ERP–llamador;
- matriz final de roles y permisos;
- catálogo definitivo de resultados de adicional;
- reglas completas de cancelación/devolución;
- facturación electrónica completa;
- rediseño integral de ventas/caja.

## 10. Decisiones de negocio todavía pendientes

1. ¿Un aprobador de COSTO 0 puede aprobar su propia solicitud y qué límites económicos —si existen— aplican por aprobador?
2. ¿Cuál es la regla exacta para guardar una selección no disponible como cita regular excepcional o como sobreagendamiento autorizado, y quién puede aprobarla?
3. ¿Cuál es el límite operativo de adicionales por profesional/bloque/día y cuáles son sus reglas exactas de cierre/no atención?
4. ¿Qué grupo exacto de profesionales, usuarios y roles participará en el piloto de la sede actual?

La asignación inicial y la reasignación básica del responsable de la cita, el tipo de gestor de adicionales y el uso de un maestro configurable de aprobadores de COSTO 0 ya están **CONFIRMADOS POR NEGOCIO** y no deben reaparecer como bloqueos.
