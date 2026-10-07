# MVP de agendamiento — flujos funcionales

## 1. Convenciones

Estos flujos describen comportamiento empresarial esperado, no endpoints ni tablas. Toda acción sensible exige permiso de backend y trazabilidad. La referencia UX aprobada orienta la presentación, pero no define reglas ni arquitectura.

Actores separados:

- **creador:** usuario autenticado que registra inicialmente la cita;
- **responsable de la cita:** usuario encargado del seguimiento; inicia siendo el creador y puede cambiar;
- **modificador:** usuario que cambia o reprograma la cita sin sustituir al creador;
- **canal/medio de captación:** origen de la solicitud, separado de las identidades anteriores;
- **verificador de pago:** usuario que comprueba la evidencia;
- **aprobador:** usuario autorizado para una excepción concreta;
- **profesional:** quien prestará o evaluará si puede prestar la atención.

## 2. Mapa general

```text
Canal de solicitud
  -> identificar creador y responsable inicial
  -> identificar/registrar paciente
  -> seleccionar sede + profesional + servicio + fecha
  -> evaluar disponibilidad
       |-- cupo regular -> pre-reserva
       |-- conflicto -> solicitar sobreagenda autorizada
       `-- agenda llena -> evaluar cita adicional informada
  -> precio/condición económica
       |-- adelanto requerido y verificado
       |-- excepción al adelanto autorizada
       `-- COSTO 0 aprobado, si corresponde
  -> cita confirmada
  -> llegada/espera/llamado/atención mediante contrato compatible
  -> resultado y trazabilidad
```

## 3. Consulta macro de agenda

### Flujo objetivo

1. El usuario entra a Agenda con una vista predeterminada permitida.
2. Puede alternar Día, Semana y Mes sin perder filtros relevantes.
3. Selecciona sede, especialidad y uno o más profesionales según su ámbito.
4. La vista distingue al menos: disponibilidad, pre-reserva, confirmada, adicional, bloqueada y estados operativos que se aprueben.
5. En comparación multi-profesional cada columna/tarjeta identifica inequívocamente al profesional.
6. Seleccionar cualquier fecha/segmento puede abrir el flujo de agendamiento.
7. Si no hay disponibilidad, el flujo explica la condición y ofrece únicamente las excepciones para las que el usuario tenga capacidad.

### Regla crítica

`abrir flujo` no equivale a `confirmar cita`. La disponibilidad y autorización se vuelven a evaluar en el momento de confirmar.

### 3.1 Modo de Agendamiento Rápido

**CONFIRMADO POR NEGOCIO:** COMERCIAL, ADMISION y otros usuarios que agendan repetitivamente deben disponer de un flujo de alta velocidad que conserve el contexto visual de la agenda.

```text
DNI
  -> búsqueda local
       |-- existe -> PACIENTE REGISTRADO
       `-- no existe -> RENIEC cuando corresponda
                            -> identidad mínima
                            -> PACIENTE NUEVO / DATOS PENDIENTES
  -> profesional
  -> fecha
  -> hora por slot o entrada manual
  -> servicio/tipo de consulta
  -> responsable de la cita
  -> condición de cita/pago
  -> validar disponibilidad, autorización y consistencia
  -> AGENDAR
```

El objetivo es completar una cita común en pocos segundos, sin fijar todavía un SLA exacto. El modo rápido no omite controles de backend ni crea una segunda implementación de la cita.

## 4. Identificación y alta del paciente

```text
Ingresar DNI
  -> validar formato/longitud localmente
  -> esperar debounce
  -> buscar paciente local
       |-- existe -> seleccionar paciente y continuar
       `-- no existe -> consultar RENIEC
             |-- responde -> autocompletar + pedir faltantes
             `-- no responde/no encuentra -> habilitar entrada manual
  -> validar posibles duplicados
  -> registrar paciente
  -> mantener contexto de la agenda
  -> continuar a Programación
```

### Resultado esperado

- El operador no cambia de módulo ni pierde profesional/fecha seleccionados.
- El origen RENIEC/manual queda distinguible sin convertir la respuesta externa en verdad incuestionable.
- Solo se muestran/registran los datos necesarios.
- El DNI o la respuesta completa no se escriben en consola/log técnico.

### 4.1 Evolución de paciente nuevo o incompleto

1. La búsqueda local siempre precede a RENIEC.
2. Si existe, se informa **PACIENTE REGISTRADO** y se reutiliza el mismo registro.
3. Si no existe, se consulta RENIEC cuando corresponda y se permite crear la identidad mínima suficiente para agendar.
4. Se informa de manera visible **PACIENTE NUEVO** y **DATOS PENDIENTES DE COMPLETAR/VALIDAR**, según corresponda.
5. La cita continúa sin exigir al usuario comercial toda la ficha demográfica.
6. En la atención presencial, ADMISION verifica el DNI y completa la información obligatoria pendiente.
7. La misma identidad evoluciona desde incompleta/pendiente a completa/validada; completar datos no crea otro paciente.

**PENDIENTE DE NEGOCIO:** campos exactos mínimos para agendar, campos obligatorios para completar/validar y nombres definitivos de los estados de completitud.

## 5. Responsabilidad y autoría de la cita

1. Se registra el canal de solicitud (WhatsApp, llamada, red social u otro).
2. El sistema obtiene al creador exclusivamente de la sesión autenticada.
3. Al crear, asigna ese mismo usuario como responsable inicial de la cita, conservando ambos conceptos por separado.
4. Un usuario autorizado puede reasignar después el responsable a otro usuario.
5. La reasignación conserva responsable anterior, responsable nuevo, usuario que efectuó el cambio y fecha/hora.
6. Si otro usuario modifica o reprograma, se conserva al creador y se agrega el modificador/evento sin cambiar implícitamente al responsable.
7. Caja registra al usuario que verifica/cobra sin reemplazar creador ni responsable.

Ejemplo conceptual:

```text
TikTok -> Usuario A crea la cita
       -> Usuario A queda como responsable inicial
       -> Usuario B reasigna el responsable a Usuario C
       -> Caja D verifica/cobra
```

La auditoría conserva al creador A, el cambio ejecutado por B, al responsable anterior/nuevo y la intervención de Caja D. La atribución comercial que pudiera requerirse en el futuro no se infiere del canal ni reemplaza la responsabilidad de la cita.

## 6. Cita regular con pre-reserva

1. Se seleccionan sede, profesional, servicio, fecha y cupo disponible.
2. El servidor comprueba horario, bloqueos y conflicto en ese instante.
3. Se crea una pre-reserva con vencimiento (15 minutos por defecto, configurable).
4. Se calcula el precio en servidor y se presenta el adelanto requerido del 50 %.
5. Se registra evidencia de pago.
6. Un usuario autorizado verifica la evidencia.
7. La confirmación vuelve a comprobar el cupo y realiza de forma indivisible:
   - aplicación del adelanto;
   - consumo de disponibilidad;
   - transición a cita confirmada;
   - trazabilidad del actor.
8. Si vence sin pago validado, la pre-reserva pasa a LIBERADA y el cupo vuelve a disponibilidad.

### Competencia por el cupo

La prioridad empresarial corresponde a quien acredita el abono requerido. La resolución técnica exacta de simultaneidad se diseñará después, pero nunca debe producir dos confirmaciones regulares incompatibles.

### Extensión

Solo un usuario con capacidad puede extender. Se conservan vencimiento anterior/nuevo, actor, fecha/hora y motivo. Cantidad máxima y avisos siguen pendientes.

## 7. Confirmación sin adelanto

1. El agendador solicita la excepción y registra motivo.
2. El sistema identifica al aprobador con capacidad independiente.
3. El aprobador acepta o rechaza; no basta escribir su nombre.
4. Se conservan solicitante, aprobador, decisión, motivo y fecha/hora.
5. Solo una aprobación vigente permite confirmar sin el 50 %.
6. La cita conserva la obligación económica pendiente salvo que exista además una aprobación distinta de COSTO 0.

`Excepción al adelanto` no equivale a `COSTO 0`.

## 8. COSTO 0

1. El servidor calcula y conserva el importe original aplicable.
2. El solicitante elige la razón y envía la solicitud.
3. Se verifica que el aprobador sea una persona/usuario activo del maestro configurable de aprobadores; puede corresponder a profesional de salud, Comercial, gerencia u otro usuario autorizado, sin hardcodearlo por rol.
4. El aprobador acepta o rechaza el importe cero.
5. Se conservan solicitante, aprobador, motivo, fecha/hora, importe original e importe aprobado.
6. La cita/obligación aplica el importe aprobado sin borrar la tarifa original.
7. El evento queda disponible para auditoría y reporte.

**CONFIRMADO POR NEGOCIO:** el maestro permite habilitar/deshabilitar aprobadores sin cambios de código y admite una vigencia futura opcional. **PENDIENTE DE NEGOCIO:** autoaprobación del solicitante y límites económicos exactos.

## 9. Sobreagendamiento autorizado

1. El usuario abre un periodo no disponible o intenta confirmar un cupo en conflicto.
2. El sistema informa la causa: fuera de horario, bloqueado, ocupado o capacidad agotada.
3. Si el usuario no tiene capacidad, puede abandonar o solicitar autorización; no puede forzar el guardado.
4. El aprobador evalúa profesional, servicio, fecha/hora, carga existente y motivo.
5. Si aprueba, se registra la excepción y se confirma una cita marcada como sobreagenda.
6. La agenda la diferencia visualmente y la disponibilidad regular no la presenta como un cupo ordinario.

La autorización no transforma la cita en “adicional”; sigue siendo una cita confirmada por excepción de capacidad. **PENDIENTE DE NEGOCIO:** la regla exacta que decide si una selección no disponible puede regularizarse como cita regular o debe tramitarse como sobreagendamiento, y quién la aprueba. La apertura del formulario no decide ese resultado.

## 10. Cita adicional

### Condición de entrada

**CONFIRMADO POR NEGOCIO:** la agenda regular del profesional está completa. La adicional no es un cupo regular; el paciente debe ser informado expresamente; su atención no está garantizada y puede concluir atendido o no atendido. La espera operativa de referencia puede llegar aproximadamente hasta dos horas, pero ese valor es una política configurable y no una constante del sistema. ADMISION o COMERCIAL pueden gestionarla conforme a sus autorizaciones y no se exige necesariamente aprobación manual del profesional.

**PENDIENTE DE NEGOCIO:** máximo por profesional/bloque/día y reglas exactas de cierre/no atención.

### Flujo objetivo

1. Un usuario de ADMISION o COMERCIAL con la capacidad correspondiente selecciona “Evaluar adicional”.
2. El sistema muestra profesional, servicio, fecha, sede, horario activo, fin de bloque, citas regulares, adicionales existentes, duración estimada del servicio cuando esté disponible, capacidad y política vigente.
3. El sistema rechaza la adicional si extendería artificialmente el bloque de trabajo del profesional o incumpliría una restricción operativa confirmada.
4. Se informa al paciente:
   - que no tiene cupo regular;
   - que la espera aproximada puede llegar al valor configurable vigente, inicialmente referenciado por negocio como hasta aproximadamente dos horas;
   - que la atención no está garantizada.
5. El agendador registra la confirmación de que el paciente fue informado.
6. Se registra motivo/condición y actor.
7. Se aplica la política económica normal o las excepciones aprobadas por separado.
8. Al llegar, se conserva el evento de llegada.
9. Si la operación permite atenderlo, ingresa a espera/llamado/atención mediante el contrato operativo; no se exige como paso estándar una aprobación manual previa del profesional.
10. Se registra un resultado final. Que pueda concluir atendido o no atendido está confirmado; el catálogo y reglas exactas —incluidos los candidatos provisionales ATENDIDO, NO ATENDIDO, RETIRADO y REPROGRAMADO— siguen pendientes.

### Invariantes

- no consume ni crea ficticiamente un slot regular;
- no prolonga artificialmente el horario ni el bloque del profesional;
- no se representa como `cita_doble`;
- una tarifa adicional no determina su tipo;
- COSTO 0 no se presume;
- el paciente informado no implica garantía ni renuncia a la trazabilidad;
- el resultado no se deduce solo del último estado del llamador.

## 11. Edición y reprogramación

1. El usuario abre una cita existente y visualiza condición actual, pago y restricciones.
2. El sistema comprueba la capacidad de modificar/reprogramar y las reglas del estado actual.
3. Se selecciona el nuevo cupo; el servidor verifica disponibilidad.
4. Se solicita motivo cuando la política lo exija.
5. Se resuelve el impacto en pre-reserva, adelanto, obligación y llamador.
6. Se aplica el cambio de forma consistente y se conserva antes/después, actor y fecha/hora.

### Drag & drop

Puede ser un atajo UX futuro del mismo flujo, nunca una escritura directa. Al soltar debe mostrar confirmación, validar servidor, pedir motivo/autorización cuando corresponda y revertir visualmente si falla.

## 12. Pago y facturación

1. Programación presenta precio calculado y adelanto requerido.
2. El usuario registra método, importe, referencia y evidencia.
3. La evidencia queda pendiente hasta verificación.
4. La verificación válida permite aplicar el pago a la obligación correcta.
5. Caja/comprobante continúan según el tipo de operación y reglas vigentes.
6. La cita refleja un resumen derivado, sin crear una segunda fuente financiera de verdad.

Para el piloto debe definirse cómo coexistirá el ticket automático del alta actual con `Sales`; no se debe cobrar dos veces ni recalcular con una tarifa distinta a la acordada.

## 13. Interacción con el llamador

```text
Cita confirmada/adicional admitida
  -> llegada
  -> espera
  -> llamado a consultorio
  -> en atención
  -> finalización
```

Durante el MVP:

- el ERP debe ser la fuente canónica de autorización de agenda;
- no se cambia el llamador hasta confirmar su versión y flujo productivo;
- se preservan ids/campos/estados consumidos o se genera una proyección compatible;
- una adicional solo se publica al llamador en el momento que negocio defina como admitida a espera;
- toda escritura externa directa debe cerrarse en una fase autorizada; mientras exista es un riesgo prioritario.

## 14. Flujos de error

| Situación | Resultado funcional esperado |
|---|---|
| RENIEC no responde | Aviso no técnico, entrada manual disponible y contexto conservado. |
| Paciente duplicado | Mostrar coincidencia segura y permitir seleccionar/revisar según permiso. |
| Cupo tomado durante el flujo | No confirmar; explicar conflicto y ofrecer otro cupo o excepción autorizada. |
| Pre-reserva vencida | Liberar; impedir confirmación silenciosa y permitir iniciar otra solicitud. |
| Pago no verificable | Mantener pendiente; no confirmar por regla general. |
| COSTO 0 rechazado | Conservar rechazo y mantener importe original. |
| Sobreagenda rechazada | No crear cita excepcional. |
| Adicional finalmente no atendida | Registrar resultado y aplicar política comercial aún pendiente. |
| Llamador no disponible | No perder la cita ni inventar transición; registrar fallo operativo y permitir recuperación controlada. |

## 15. Fluidez operativa del MVP

**CONFIRMADO POR NEGOCIO:** la experiencia de uso es parte del alcance funcional del MVP. La maqueta es referencia interna y el sistema anterior es referencia operativa; ninguno es una especificación visual literal. La interfaz prioriza desktop para COMERCIAL y ADMISION y equilibra claridad, densidad útil y velocidad.

Los flujos deben permitir, con pocos pasos y sin perder el contexto de agenda:

- ingresar una hora manual;
- cambiar hora, fecha o profesional;
- buscar o crear un paciente dentro del mismo flujo;
- cambiar al responsable de la cita;
- registrar una adicional;
- abrir un espacio no disponible y conocer antes de guardar si procede como regular, sobreagenda o adicional;
- identificar disponibilidad y condición de pago;
- reprogramar desde vistas Día, Semana o Mes;
- filtrar y comparar profesionales.

La cantidad exacta de interacciones se validará con guiones de usabilidad del piloto; la interfaz no debe hardcodearse para un grupo concreto de profesionales, usuarios o roles.

### 15.1 Hora y navegación

- un click sobre un slot propone fecha, hora y profesional;
- el usuario puede escribir una hora válida o cambiar, por ejemplo, 10:00 a 10:15 sin encadenar múltiples modales;
- el intervalo visual puede ser de 15 minutos u otra configuración y no determina por sí solo la duración clínica;
- el futuro diseño debe considerar un orden de tabulación y operación por teclado eficiente;
- reprogramar reutiliza el mismo flujo corto con validación de backend.

### 15.2 Semaforización accesible

La lectura rápida usa color acompañado de texto, etiqueta o icono. Debe diferenciar conceptualmente disponibilidad, pre-reserva, confirmación, pago, adicional y etapas de llegada/atención/cierre. La paleta, nombres y enum se mantienen pendientes de reconciliación con producción y llamador.

### 15.3 Agendamiento rápido y detalle completo

El formulario rápido presenta solo lo necesario para una cita común. El detalle completo concentra pagos, evidencia, COSTO 0, adicional, aprobaciones, historial, auditoría, reprogramaciones, observaciones e información avanzada. Ambos invocan las mismas reglas y conservan el mismo creador, responsable, tipo, estado e historial.

### 15.4 Propuesta de contexto integrado

**PROPUESTA UX:** mantener en una misma pantalla de trabajo zonas para filtros/profesionales, calendario Día/Semana/Mes, agenda horaria/citas y registro rápido. El detalle completo puede abrirse cuando sea necesario sin obligar al usuario a abandonar continuamente la agenda.
