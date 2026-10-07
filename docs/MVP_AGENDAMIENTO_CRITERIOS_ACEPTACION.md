# MVP de agendamiento — criterios de aceptación

## 1. Uso del documento

Los criterios describen resultados observables para aceptar el futuro piloto. No implican que el código actual los cumpla. Todo escenario deberá verificarse en entorno aislado, con integraciones fake/sandbox y una matriz de roles aprobada para el piloto.

Estados de cobertura AS-IS se documentan en `MVP_AGENDAMIENTO_GAP_ANALYSIS.md`.

## 2. Agenda Día / Semana / Mes

### CA-AG-001 — Cambiar escala temporal

**Dado** un usuario con permiso de lectura de agenda, **cuando** alterna Día, Semana y Mes, **entonces** se muestran las citas del periodo correcto, se conservan filtros compatibles y no se duplican/omiten eventos por el cambio de vista.

### CA-AG-002 — Identificación visual

**Dado** un periodo con citas en estados distintos, **cuando** se renderiza la agenda, **entonces** cada tarjeta identifica hora, paciente de forma proporcional al rol, profesional, servicio y estado; la leyenda permite interpretar colores/indicadores sin depender solo del color.

### CA-AG-003 — Privacidad

**Dado** un usuario de agenda, **cuando** visualiza tarjetas o errores, **entonces** no se exponen DNI completos, evidencia de pago ni datos personales innecesarios en pantalla, consola o log técnico.

## 3. Agenda multi-profesional

### CA-AG-010 — Filtros

**Dado** un usuario con ámbito autorizado, **cuando** filtra por sede, especialidad y profesional, **entonces** solo ve recursos/citas dentro de ese ámbito y el servidor aplica los mismos límites.

### CA-AG-011 — Comparación

**Dado** dos o más profesionales seleccionados, **cuando** se activa comparación, **entonces** sus agendas se distinguen inequívocamente y los periodos disponibles/ocupados se calculan para cada uno.

### CA-AG-012 — Disponibilidad común

**Dado** varios profesionales comparados, **cuando** se solicita coincidencia de disponibilidad, **entonces** solo se presentan intervalos realmente compatibles con horarios, duración y bloqueos de todos los seleccionados.

## 4. Configuración de horarios

### CA-HO-001 — Plantilla y excepción

**Dado** un usuario autorizado para administrar horarios, **cuando** define una plantilla o excepción fechada, **entonces** se conservan profesional, sede/recurso, vigencia, inicio, fin, duración, tipo y actor.

### CA-HO-002 — Validación

**Dado** un rango inválido o solapado de forma no permitida, **cuando** se intenta guardar, **entonces** el backend rechaza la operación con mensaje claro y no persiste cambios parciales.

### CA-HO-003 — Bloqueo

**Dado** un bloqueo/ausencia aplicable, **cuando** se consulta disponibilidad, **entonces** no aparecen cupos regulares en el intervalo, aunque el usuario pueda abrir el flujo para solicitar una excepción.

### CA-HO-004 — Duración configurable

**Dado** una duración aprobada para profesional/servicio, **cuando** se generan cupos, **entonces** cada intervalo respeta esa duración y nunca excede la jornada.

## 5. DNI y paciente existente

### CA-PA-001 — Búsqueda local primero

**Dado** un DNI válido perteneciente a un paciente local, **cuando** se completa el DNI, **entonces** se selecciona el paciente sin llamar a RENIEC y se continúa en Programación.

### CA-PA-002 — Control de llamadas

**Dado** que el operador está digitando, **cuando** aún no se alcanza el formato válido o no terminó el debounce, **entonces** no se realiza búsqueda externa.

### CA-PA-003 — Coincidencia segura

**Dado** un paciente existente, **cuando** se muestra la coincidencia, **entonces** solo se presentan los datos mínimos necesarios y el usuario no puede editarlo sin capacidad específica.

## 6. DNI y paciente nuevo

### CA-PA-010 — Autocompletado RENIEC

**Dado** un DNI válido no encontrado localmente y una respuesta exitosa fake/sandbox de RENIEC, **cuando** termina la consulta, **entonces** se autocompletan los datos disponibles, se marcan los faltantes y el operador puede registrar al paciente sin abandonar la agenda.

### CA-PA-011 — Registro y continuidad

**Dado** datos mínimos válidos de un paciente nuevo, **cuando** se registra, **entonces** el paciente queda seleccionado y se conservan profesional, fecha, sede y demás contexto previo de programación.

### CA-PA-012 — Duplicidad

**Dado** dos intentos concurrentes con el mismo identificador, **cuando** ambos guardan, **entonces** no se crean dos pacientes y el segundo flujo recibe una resolución controlada.

### CA-PA-013 — Feedback de identidad

**Dado** un DNI válido, **cuando** termina la búsqueda local, **entonces** el usuario recibe feedback inmediato y claro equivalente a PACIENTE REGISTRADO o PACIENTE NUEVO; solo el segundo caso habilita RENIEC cuando corresponda.

### CA-PA-014 — Identidad mínima para agendar

**Dado** un paciente nuevo y un usuario comercial autorizado, **cuando** existe la identidad mínima suficiente, **entonces** puede continuar con la cita sin completar toda la ficha demográfica y se muestra que existen datos pendientes de completar/validar.

### CA-PA-015 — Completar sin duplicar

**Dado** un paciente agendado con información incompleta, **cuando** ADMISION verifica DNI y completa la ficha presencialmente, **entonces** se actualiza la misma identidad y no se crea un segundo paciente.

### CA-PA-016 — Separación de requisitos de datos

**Dado** el diseño funcional del piloto, **cuando** se documentan validaciones del paciente, **entonces** se distinguen campos mínimos para agendar de campos obligatorios para completar/validar; mientras negocio no confirme las listas exactas, no se convierten en requisitos definitivos.

## 7. Fallback manual RENIEC

### CA-RE-001 — Proveedor indisponible

**Dado** timeout, error o respuesta sin datos de RENIEC, **cuando** se informa el resultado, **entonces** no se muestra un error técnico ni se pierde el DNI y se habilita inmediatamente el alta manual.

### CA-RE-002 — Integración segura

**Dado** un entorno de pruebas, **cuando** se ejecutan escenarios RENIEC, **entonces** todo HTTP está fakeado y no se usan credenciales ni llamadas reales.

### CA-RE-003 — Logging

**Dado** una consulta local/RENIEC, **cuando** termina o falla, **entonces** DNI completo y respuesta personal no aparecen en consola ni logs técnicos.

## 8. Pre-reserva

### CA-PR-001 — Creación

**Dado** un cupo regular disponible, **cuando** se inicia una pre-reserva, **entonces** queda retenido con inicio, vencimiento, actor, paciente, profesional, servicio, sede y estado distinguible de cita confirmada.

### CA-PR-002 — Vigencia configurable

**Dado** la configuración predeterminada provisional de 15 minutos, **cuando** se crea la pre-reserva, **entonces** el vencimiento usa ese valor; cambiar configuración afecta nuevas pre-reservas sin alterar el historial.

### CA-PR-003 — Expiración

**Dado** una pre-reserva vencida sin adelanto validado ni excepción, **cuando** corre el proceso de expiración, **entonces** pasa una sola vez a LIBERADA y el cupo vuelve a disponibilidad.

### CA-PR-004 — Extensión auditada

**Dado** un usuario con capacidad, **cuando** extiende la pre-reserva, **entonces** se conservan vencimientos anterior/nuevo, actor, fecha/hora y motivo; un usuario sin capacidad recibe rechazo.

### CA-PR-005 — Concurrencia

**Dado** dos solicitudes simultáneas del mismo cupo, **cuando** intentan consolidarlo, **entonces** como máximo una obtiene la confirmación regular y la otra recibe una respuesta de conflicto recuperable.

## 9. Cita confirmada

### CA-CI-001 — Adelanto general

**Dado** una pre-reserva con precio mayor que cero, **cuando** existe un adelanto verificado igual o superior al 50 %, **entonces** puede transicionar a cita confirmada si el cupo continúa válido.

### CA-CI-002 — Pago insuficiente

**Dado** un pago inferior al 50 % sin excepción, **cuando** se intenta confirmar, **entonces** el backend rechaza la transición y conserva pago/pre-reserva de forma consistente.

### CA-CI-003 — Excepción al adelanto

**Dado** una aprobación válida de excepción al adelanto, **cuando** se confirma sin 50 %, **entonces** se conservan solicitante, aprobador, motivo y fecha/hora y la obligación pendiente no se convierte automáticamente en costo cero.

### CA-CI-004 — Transición válida

**Dado** cualquier cambio de estado, **cuando** no pertenece al flujo permitido para el estado/rol actual, **entonces** se rechaza sin modificar la cita.

## 10. Cita adicional

**CONFIRMADO POR NEGOCIO:** la adicional se usa con agenda regular completa, no es cupo regular, exige información expresa, no garantiza atención y puede terminar atendida o no atendida. La espera de referencia puede llegar aproximadamente hasta dos horas, como política configurable. ADMISION o COMERCIAL pueden gestionarla conforme a autorización y no requiere necesariamente aprobación manual del profesional. Solo los límites máximos y el cierre/no atención exactos permanecen pendientes.

### CA-AD-001 — Condición de agenda llena

**Dado** que no hay cupo regular, **cuando** un usuario autorizado inicia una adicional, **entonces** se identifica explícitamente como ADICIONAL y no crea un slot regular.

### CA-AD-002 — Información al paciente

**Dado** una adicional, **cuando** se intenta registrarla sin constancia de información sobre espera configurable y atención no garantizada, **entonces** el backend rechaza el registro.

### CA-AD-003 — Datos mínimos

**Dado** una adicional válida, **cuando** se registra, **entonces** conserva profesional, servicio, fecha, sede, registrador, motivo, política informada y constancia.

### CA-AD-004 — Resultado

**Dado** una adicional registrada, **cuando** concluye el día/flujo, **entonces** debe quedar un resultado final permitido y trazable; el catálogo definitivo se ajustará a la decisión empresarial.

### CA-AD-005 — Espera configurable

**Dado** una política de espera vigente cuya referencia empresarial inicial puede llegar aproximadamente hasta dos horas, **cuando** cambia, **entonces** las nuevas adicionales comunican el nuevo valor y las anteriores conservan qué política/valor se informó; dos horas no está hardcodeado.

### CA-AD-006 — Decisiones todavía abiertas

**Dado** que no se definieron límites máximos ni reglas exactas de cierre/no atención, **cuando** se prepare el piloto, **entonces** esas decisiones deben estar aprobadas —aunque sea provisionalmente— antes de habilitar adicionales reales.

### CA-AD-007 — Gestión sin aprobación médica obligatoria

**Dado** un usuario de ADMISION o COMERCIAL con capacidad para gestionar adicionales, **cuando** registra una dentro de las reglas operativas, **entonces** no se exige como paso estándar una aprobación manual del profesional.

### CA-AD-008 — Respeto del bloque de trabajo

**Dado** horario activo, fin del bloque, citas regulares, adicionales existentes, duración estimada disponible y capacidad operativa, **cuando** se evalúa una adicional, **entonces** el backend usa esos elementos y rechaza cualquier registro que prolongue artificialmente el bloque del profesional.

## 11. Sobreagendamiento autorizado

**CONFIRMADO POR NEGOCIO:** regular, sobreagendamiento autorizado y adicional son figuras distintas. **PENDIENTE DE NEGOCIO:** la regla exacta de guardado/autorización cuando se selecciona un espacio no disponible. Los criterios siguientes son una **PROPUESTA TÉCNICA** condicionada a que el caso sea clasificado finalmente como sobreagendamiento.

### CA-SA-001 — Apertura sin permiso implícito

**Dado** un periodo no disponible, **cuando** cualquier usuario lector autorizado lo selecciona, **entonces** puede ver/iniciar el flujo, pero no confirmar una excepción sin capacidad.

### CA-SA-002 — Autorización

**Dado** un conflicto clasificado como sobreagendamiento bajo la política aprobada, **cuando** el autorizador competente acepta la solicitud, **entonces** se conservan causa, solicitante, autorizador, fecha/hora y carga evaluada.

### CA-SA-003 — Rechazo backend

**Dado** un usuario sin permiso o sin aprobación, **cuando** manipula la solicitud para enviar hora ocupada/fuera de horario, **entonces** el backend rechaza y no crea/reprograma la cita.

### CA-SA-004 — Distinción

**Dado** una sobreagenda confirmada, **cuando** se visualiza/reportea, **entonces** no se contabiliza como cupo regular ni como cita adicional.

## 12. COSTO 0 con aprobador

### CA-C0-001 — Importe calculado en servidor

**Dado** profesional, servicio y reglas de tarifa, **cuando** se solicita COSTO 0, **entonces** el importe original se calcula en servidor y no puede alterarse desde el navegador.

### CA-C0-002 — Aprobación trazable

**Dado** una solicitud de COSTO 0, **cuando** se aprueba, **entonces** quedan solicitante, aprobador identificable, motivo, fecha/hora, importe original, importe aprobado y objeto afectado.

### CA-C0-003 — Maestro configurable

**Dado** una persona/usuario fuera del maestro configurable o con autorización inactiva/fuera de vigencia, **cuando** intenta aprobar costo cero, **entonces** el backend rechaza aunque se muestre o manipule el control de interfaz.

### CA-C0-004 — Texto libre insuficiente

**Dado** solo un nombre escrito en “autorizado por”, **cuando** se intenta aplicar costo cero, **entonces** no se considera aprobación válida.

### CA-C0-005 — Administración sin despliegue

**Dado** un usuario autorizado para administrar el maestro, **cuando** habilita o deshabilita a un profesional de salud, persona de Comercial, gerente u otro usuario como aprobador, **entonces** el cambio aplica sin modificar código y queda auditado; la vigencia opcional puede configurarse cuando el negocio la use.

La autoaprobación solicitante/aprobador y los límites económicos permanecen **PENDIENTES DE NEGOCIO**.

## 13. Usuario que agenda y modifica

### CA-AU-001 — Creador

**Dado** un usuario autenticado que crea una cita, **cuando** se confirma el registro, **entonces** el creador procede de la sesión y no de un campo editable.

### CA-AU-002 — Modificador

**Dado** otro usuario que reprograma/modifica, **cuando** guarda, **entonces** se conserva al creador original y se registra al modificador, fecha/hora, motivo y valores relevantes antes/después.

### CA-AU-003 — Auditoría de estados

**Dado** una transición operativa, **cuando** ocurre, **entonces** se conserva actor/origen y no se depende solo de `updated_at` para explicar el cambio.

## 14. Responsable de la cita

### CA-RC-001 — Asignación inicial

**Dado** un usuario autenticado que crea una cita, **cuando** se completa el alta, **entonces** ese usuario queda como creador y responsable inicial en conceptos separados.

### CA-RC-002 — Canal y responsable

**Dado** una solicitud recibida por un canal, **cuando** se registra, **entonces** se conservan canal/medio y responsable de la cita como conceptos distintos.

### CA-RC-003 — Reasignación

**Dado** una reasignación autorizada, **cuando** se ejecuta, **entonces** se conserva responsable anterior, responsable nuevo, usuario que cambia y fecha/hora sin reemplazar al creador; el motivo puede exigirse por política posterior.

### CA-RC-004 — Modificación independiente

**Dado** un usuario distinto que modifica o reprograma la cita, **cuando** guarda el cambio, **entonces** se registra como modificador y no se convierte implícitamente en responsable.

## 15. Pago, evidencia y trazabilidad

### CA-PG-001 — Evidencia durable

**Dado** un pago no efectivo que exige evidencia, **cuando** se registra, **entonces** el archivo queda almacenado de forma protegida y vinculado al pago; OCR solo propone datos y no reemplaza la verificación.

### CA-PG-002 — Verificación

**Dado** una evidencia pendiente, **cuando** un usuario autorizado verifica/rechaza, **entonces** se conserva quién, cuándo, resultado, referencia e importe.

### CA-PG-003 — No duplicación

**Dado** un reintento o la misma operación, **cuando** se procesa otra vez, **entonces** no se aplica el pago dos veces ni se emiten dos efectos financieros incompatibles.

### CA-PG-004 — Coherencia

**Dado** una cita cobrada al agendar y posteriormente abierta en Ventas, **cuando** se liquida/factura, **entonces** el adelanto se reconoce una sola vez y el saldo coincide en cita, obligación, pago y comprobante/proyección.

## 16. Reprogramación y drag & drop

### CA-RP-001 — Reprogramación segura

**Dado** una cita modificable, **cuando** se elige un nuevo cupo, **entonces** se validan permiso, disponibilidad, conflicto e impacto económico antes de guardar.

### CA-RP-002 — Arrastre como atajo

**Dado** drag & drop habilitado, **cuando** se suelta una cita, **entonces** se solicita confirmación/motivo según política, se valida en backend y se revierte visualmente si el servidor rechaza.

### CA-RP-003 — Excepción

**Dado** un destino ocupado, **cuando** se arrastra sin autorización de sobreagenda, **entonces** no se guarda.

## 17. Compatibilidad con llamador

### CA-LL-001 — Contrato conocido antes del piloto

**Dado** el inicio del piloto, **cuando** se habilita para usuarios reales, **entonces** están documentados versión desplegada del llamador, conexión usada, rutas activas, DDL/enum y campos consumidos.

### CA-LL-002 — Campos compatibles

**Dado** una cita creada/reprogramada por el MVP, **cuando** el llamador la consume, **entonces** ids, paciente, profesional, servicio, fecha, duración y estado se proyectan sin romper el flujo confirmado.

### CA-LL-003 — Adicional admitida

**Dado** una adicional, **cuando** todavía no fue admitida a espera, **entonces** no aparece engañosamente como cita regular garantizada en el llamador; al admitirla, la transición sigue la regla aprobada.

### CA-LL-004 — Escritura externa

**Dado** el contrato futuro, **cuando** un cliente no autenticado intenta modificar una cita, **entonces** recibe rechazo. Para aceptar el piloto debe existir mitigación explícita del riesgo de rutas legacy si están desplegadas.

### CA-LL-005 — Falla de integración

**Dado** el llamador no disponible, **cuando** se registra una cita/transición interna, **entonces** no se pierde ni se duplica; el fallo queda observable y recuperable.

## 18. Criterios transversales

### CA-TR-001 — Autorización backend

Cada lectura/escritura sensible tiene pruebas positivas y negativas por capacidad; ocultar menú/botón no basta.

### CA-TR-002 — Concurrencia realista

Disponibilidad, pre-reserva, confirmación y correlativos se prueban con MySQL aislado cuando dependan de bloqueos que SQLite no reproduce.

### CA-TR-003 — Integraciones fake

Pruebas automatizadas usan `Http::fake`, `Mail::fake`, `Queue::fake` y `Notification::fake` cuando corresponda; nunca llaman RENIEC, correo, SMS, SUNAT ni llamador reales.

### CA-TR-004 — Auditoría

Crear, modificar, reprogramar, cancelar, aprobar, verificar pago, sobreagendar y cerrar una adicional producen evidencia consultable con actor, momento, objeto y resultado.

### CA-TR-005 — Reversibilidad del piloto

El MVP puede deshabilitarse sin borrar historia; la convivencia con el flujo legado tiene métricas y procedimiento de retorno documentados.

### CA-TR-006 — Configuración escalable del piloto

**Dado** que el grupo exacto del piloto permanece pendiente, **cuando** se prepara la solución, **entonces** profesionales, usuarios y roles participantes se seleccionan por configuración/ámbito y no mediante condiciones hardcodeadas ni funciones exclusivas del grupo inicial.

## 19. Fluidez operativa y UX

### CA-UX-001 — Operaciones frecuentes sin pérdida de contexto

**Dado** un usuario trabajando en Día, Semana o Mes, **cuando** ingresa una hora manual, cambia hora/fecha/profesional, busca o crea paciente, cambia responsable, registra una adicional o reprograma, **entonces** completa el flujo con pocos pasos y conserva filtros, fecha, profesional y demás contexto compatible.

### CA-UX-002 — Espacio no disponible

**Dado** un espacio no disponible, **cuando** el usuario abre el flujo, **entonces** ve la causa, la disponibilidad y condición de pago relevantes y el sistema distingue antes de guardar entre cita regular, sobreagendamiento autorizado y adicional; abrir el flujo no concede autorización.

### CA-UX-003 — Referencia no literal

**Dado** la maqueta interna y la referencia operativa anterior, **cuando** se valida el MVP, **entonces** se evalúan rapidez, claridad y cobertura de tareas reales, sin exigir una copia visual literal ni incorporar datos, usuarios o reglas simuladas de las referencias.

### CA-UX-004 — Cita común en pocos segundos

**Dado** un usuario entrenado de COMERCIAL o ADMISION con datos mínimos válidos, **cuando** registra una cita común, **entonces** puede completar DNI/paciente, profesional, fecha, hora, servicio, responsable y condición básica sin abandonar continuamente la agenda ni atravesar pasos ajenos al caso. No se usa todavía un número de segundos como SLA.

### CA-UX-005 — Densidad desktop útil

**Dado** un día con múltiples profesionales y citas, **cuando** se usa la vista desktop, **entonces** el usuario puede consultar filtros, fecha, agenda horaria, citas, estado/pago esenciales y registro rápido en el mismo contexto, con jerarquía legible y sin tarjetas que oculten innecesariamente información operativa.

### CA-UX-006 — Slot y hora manual

**Dado** un horario visible, **cuando** el usuario selecciona un slot, **entonces** profesional, fecha y hora se proponen en el registro rápido; **cuando** escribe una hora válida o cambia 10:00 a 10:15, **entonces** puede hacerlo directamente sin encadenar múltiples modales y el backend vuelve a validar disponibilidad.

### CA-UX-007 — Intervalo configurable y teclado

**Dado** una configuración de intervalos visuales, **cuando** la agenda se presenta, **entonces** no asume universalmente 15 minutos ni confunde intervalo visual con duración clínica; la propuesta de interacción permite un orden de tabulación coherente y no bloquea operación eficiente por teclado.

### CA-UX-008 — Semaforización accesible

**Dado** citas o espacios en condiciones distintas, **cuando** se visualizan, **entonces** cada condición usa color más texto, etiqueta o icono, y sigue siendo reconocible sin color. Disponible, pre-reserva, confirmada, pendiente de pago, pagada, adicional, llegó, espera, consulta, atendida, cancelada y no atendida son conceptos de evaluación, no un enum definitivo.

### CA-UX-009 — Un solo modelo de cita

**Dado** una cita iniciada en modo rápido, **cuando** se abre el detalle completo para pago, evidencia, COSTO 0, adicional, aprobación, historial, reprogramación u observaciones, **entonces** se mantiene la misma cita y se aplican las mismas reglas, autorizaciones y auditoría; no existe una segunda implementación funcional.

### CA-UX-010 — Trazabilidad consultable sin sobrecarga

**Dado** el formulario rápido, **cuando** se agenda, **entonces** no se sobrecarga con todo el historial, pero creado por, responsable actual, última modificación por, tipo y estado permanecen visibles o accesibles desde el contexto.

## 20. Trazabilidad requisito–criterio

| Requisito | Criterios principales |
|---|---|
| Día/Semana/Mes | CA-AG-001..003 |
| Multi-profesional | CA-AG-010..012 |
| Horarios | CA-HO-001..004 |
| DNI existente/nuevo/manual | CA-PA-001..016, CA-RE-001..003 |
| Pre-reserva/confirmación | CA-PR-001..005, CA-CI-001..004 |
| Adicional | CA-AD-001..008 |
| Sobreagenda | CA-SA-001..004 |
| COSTO 0 | CA-C0-001..005 |
| Autoría | CA-AU-001..003 |
| Responsable de la cita | CA-RC-001..004 |
| Fluidez UX | CA-UX-001..010 |
| Pagos/trazabilidad | CA-PG-001..004, CA-TR-004 |
| Llamador | CA-LL-001..005 |
