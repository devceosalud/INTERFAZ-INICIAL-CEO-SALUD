# Glosario funcional del ERP CEO Salud

## 1. Propósito

Este glosario establece un lenguaje funcional común para el ERP futuro. Evita tratar como equivalentes conceptos que el sistema heredado mezcla. No define tablas, clases ni interfaces.

Convenciones:

- **Confirmado AS-IS:** concepto demostrado por una operación actual.
- **Confirmado por negocio:** decisión comunicada por Rodrigo que forma parte del TO-BE.
- **Propuesta funcional aprobada provisionalmente:** regla aceptada para cerrar la definición actual, modificable mediante una decisión posterior documentada.
- **Confirmado en código:** comportamiento o estructura observado directamente en el repositorio.
- **Referencia externa:** concepto útil observado en una fuente ajena, sin compromiso de copiar su producto o arquitectura.
- **Propuesta técnica:** criterio recomendado para hacer viable, seguro y consistente el modelo confirmado.
- **Pendiente de negocio:** el alcance exacto depende de una decisión empresarial.
- **Pendiente de validación productiva:** el código existe, pero su funcionamiento o uso real no se ha comprobado en producción.

## 2. Personas e identidad

### Persona

Ser humano identificado por sus datos personales y medios de contacto.

- Puede participar en uno o varios roles de negocio: paciente, responsable, trabajador o profesional.
- No equivale a usuario del sistema.
- **Propuesta técnica:** debe ser la identidad común que evite duplicar datos de una misma persona.
- **Pendiente de negocio:** reglas de deduplicación e identificadores aceptados además de DNI.

### Trabajador

Persona con un vínculo vigente para prestar servicios a CEO Salud, bajo una modalidad que el negocio determine.

- Puede tener cargo, sede, vigencia y responsabilidades.
- **Confirmado por negocio:** todo trabajador que opere el ERP tendrá acceso según corresponda.
- No equivale a usuario; el trabajador representa la relación organizacional y el usuario representa el acceso.
- No todo trabajador es profesional de salud.
- **AS-IS:** no existe como entidad.
- **Pendiente de negocio:** tipos de vínculo, cargos y alcance de gestión de personal.

### Usuario

Cuenta autorizada para ingresar al ERP y ejecutar capacidades asignadas.

- Tiene credenciales, estado de acceso, roles/permisos y trazabilidad.
- Puede corresponder a un trabajador, pero no representa sus datos laborales.
- **Confirmado AS-IS:** existe en `users` y Spatie Permission.
- **Confirmado por negocio:** la autorización deberá ser granular por módulo y por operación, incluyendo lectura, creación, modificación, eliminación, aprobación, anulación y otras acciones sensibles.
- **Propuesta técnica:** el cierre de una relación laboral no debe borrar las acciones históricas del usuario.
- **Pendiente de negocio:** matriz definitiva de roles y capacidades.

### Profesional de salud

Trabajador o colaborador habilitado para prestar servicios de salud.

- Puede tener colegiatura, registro de especialidad y servicios habilitados.
- No equivale a usuario ni a trabajador, aunque los tres conceptos puedan relacionarse sobre una misma persona.
- **Confirmado AS-IS:** existe parcialmente como “Doctor”.
- **Confirmado por negocio:** los tres perfiles deben permanecer conceptualmente separados.
- **Pendiente de negocio:** profesionales externos, varias especialidades y reglas de asignación a sedes.

### Paciente

Persona que recibe o puede recibir atención/servicios de CEO Salud.

- Conserva un identificador administrativo o número de historia.
- Puede ser también trabajador, responsable o pagador en contextos distintos.
- **Confirmado AS-IS:** registro, búsqueda, actualización, responsables, citas y documentos asociados.
- No equivale a “cliente pagador”: quien paga puede ser otra persona o empresa.

### Responsable

Persona que representa, acompaña o responde por un paciente bajo un tipo de relación y vigencia.

- No debe asumirse que existe un único responsable por paciente.
- Su facultad legal o administrativa depende del tipo de responsabilidad.
- **Confirmado AS-IS:** se registran documento, nombre, teléfono y parentesco.
- **Pendiente de negocio:** cardinalidad, vigencia, representación legal y autorizaciones.

## 3. Organización y operación física

### Sede

Unidad organizacional o ubicación desde la que CEO Salud presta servicios.

- Puede agrupar consultorios, cajas, almacenes y puntos de emisión.
- **Confirmado AS-IS:** CEO Salud opera actualmente en una sola sede, aunque el sistema no la representa como entidad.
- **Confirmado por negocio:** el TO-BE debe quedar preparado para múltiples sedes por la expansión prevista.
- **Propuesta técnica:** toda operación debe conservar un ámbito organizacional explícito cuando corresponda, sin diseñar todavía su persistencia.

### Consultorio

Recurso físico donde se realiza una atención.

- Puede formar parte de la disponibilidad junto con profesional y horario.
- No equivale a sede.
- **AS-IS:** no existe como entidad; la cita solo referencia médico.
- **Confirmado por negocio:** debe poder asociarse a una sede.
- **Pendiente de negocio:** si debe reservarse como recurso dentro de la disponibilidad y qué otros recursos son reservables.

### Caja

Puesto o fondo físico/lógico donde se custodian cobros durante un turno.

- Puede tener varios turnos a lo largo del tiempo, pero no debería tener dos turnos activos incompatibles.
- **Confirmado AS-IS:** existe `cashiers` y apertura/cierre por usuario.
- No equivale a punto de emisión ni a cuenta bancaria.

### Punto de emisión

Ámbito autorizado que utiliza una serie para generar documentos internos o tributarios.

- Puede estar vinculado a una sede y/o caja, según definición fiscal.
- No equivale necesariamente a caja.
- **AS-IS:** se aproxima mediante `voucher_series.cashier_id`, pero no está definido funcionalmente.
- **Confirmado por negocio:** debe poder asociarse a una sede.
- **Pendiente de negocio:** estructura fiscal y operativa de series y puntos de emisión.

### Almacén

Ubicación lógica o física responsable del stock de productos.

- Un producto puede tener saldo diferente por almacén.
- No equivale a caja ni a catálogo de productos.
- **AS-IS:** no existe; solo hay `items.stock_actual` global.
- **Confirmado por negocio:** el ERP debe manejar stock y permitir que los almacenes se asocien posteriormente a una sede.
- **Propuesta funcional aprobada provisionalmente:** el diseño base relaciona sede → almacén → movimientos → stock, aunque el primer despliegue utilice un almacén lógico.
- **Pendiente de negocio:** cantidad inicial de almacenes, transferencias, lotes, vencimientos, compras y proveedores.

### Movimiento de inventario o kardex

Evento trazable que aumenta, disminuye o traslada existencias y conserva origen, cantidad, fecha/hora y actor.

- **Propuesta funcional aprobada provisionalmente:** los movimientos son la base de trazabilidad; el stock no será únicamente un número mutable.
- Debe poder evolucionar hacia transferencias, compras, lotes, vencimientos y farmacia sin exigir esas capacidades en la primera entrega.

## 4. Oferta y catálogo

### Especialidad

Área profesional o clínica en la que se agrupan capacidades y servicios.

- Puede vincular profesionales y servicios.
- **Confirmado AS-IS:** maestro de especialidades asociado a médicos y servicios.
- **Pendiente de negocio:** si un profesional puede tener varias especialidades y cómo se acredita su vigencia.

### Servicio

Prestación no física ofrecida por CEO Salud, clínica o administrativa/comercial según catálogo.

- Puede requerir profesional, duración, tarifa e impuestos.
- No equivale a producto inventariable.
- **Confirmado AS-IS:** existe como catálogo clínico y también como tipo dentro de `items`.
- **Propuesta técnica:** debe existir un concepto canónico que evite precios contradictorios entre ambos catálogos.

### Producto

Bien físico que puede venderse, consumirse y, si corresponde, controlarse en inventario.

- Puede tener unidad, precio, costo, impuesto y stock.
- **Confirmado AS-IS:** `items.tipo = PRODUCTO` participa en ventas.
- **Confirmado por negocio:** el ERP venderá productos y servicios y manejará stock.
- **Pendiente de negocio:** alcance de farmacia, lotes, vencimientos, compras, proveedores y múltiples almacenes.

### Tarifa

Regla de precio aplicable a un servicio bajo condiciones y vigencia definidas.

- Puede depender de profesional, sede, tipo de consulta, convenio o campaña.
- No equivale al precio histórico de una venta; la venta conserva un snapshot.
- **Confirmado AS-IS:** precio por médico-servicio, reconsulta y tarifa adicional.
- **Pendiente de negocio:** precedencia, acumulación, autorización y vigencias.

## 5. Agenda y atención

### Agenda

Conjunto de reglas y excepciones que determinan cuándo un recurso puede recibir citas.

- Incluye plantillas, bloqueos y cambios puntuales.
- No es lo mismo que el listado de citas ya reservadas.
- **Confirmado AS-IS:** hay horarios médicos y calendario, aunque mezclan fecha concreta y recurrencia.

### Disponibilidad

Capacidad reservable resultante de combinar agenda, duración, recursos y reservas existentes.

- Es un resultado dinámico; no implica que el turno haya sido reservado.
- **Confirmado AS-IS:** el sistema calcula slots al consultar.
- **Propuesta técnica:** reservar debe ser una operación atómica para evitar doble asignación del mismo horario.

### Pre-reserva temporal

Retención provisional de una disponibilidad solicitada por un canal de atención mientras el paciente gestiona el adelanto.

- No equivale a cita confirmada ni garantiza definitivamente el horario.
- **Confirmado por negocio:** una solicitud recibida por WhatsApp, llamada, redes sociales u otro canal puede originarla; si otro paciente realiza primero el abono requerido, quien pagó tiene prioridad.
- **Propuesta técnica:** debe tener estado, inicio, vencimiento y trazabilidad, y liberarse de manera consistente al expirar o perder prioridad.
- **Propuesta funcional aprobada provisionalmente:** duración predeterminada de 15 minutos, configurable; al vencer sin adelanto validado pasa a LIBERADA.
- **Propuesta funcional aprobada provisionalmente:** un usuario con permiso puede extenderla y debe conservar usuario, fecha/hora, vencimiento anterior, nuevo vencimiento y motivo cuando corresponda.
- **Pendiente de negocio:** política definitiva de avisos, límite o número de extensiones y posibles diferencias por canal/cercanía de la cita.

### Cita

Compromiso administrativo de atender a un paciente en una fecha, hora, servicio y recursos determinados.

- Puede reprogramarse, cancelarse o terminar en inasistencia.
- No equivale a atención realizada ni a pago.
- **Confirmado AS-IS:** alta, calendario y actualización de estado.
- **Confirmado por negocio:** la secuencia conceptual es disponibilidad, pre-reserva temporal y cita confirmada; la confirmación requiere como regla general un adelanto del 50 %.
- **Confirmado por negocio:** una persona autorizada puede aprobar una excepción al adelanto, conservando autorizador, motivo y fecha/hora.
- **Pendiente de negocio:** autorizadores definitivos, límite de extensiones y reglas de cancelación, reprogramación y devolución.

### Atención

Ejecución efectiva del servicio de salud o prestación programada.

- Comienza y termina mediante eventos propios y puede existir como resultado de una cita.
- Debe conectarse con la historia clínica sin confundir el evento operativo con el contenido clínico.
- **AS-IS:** solo hay estados y marcas de tiempo dentro de `appointments`; no existe módulo completo.
- **Confirmado por negocio:** el flujo futuro comprende cita, llegada, espera, llamado a consultorio, en consulta, atención y finalización, con futura integración al llamador.
- **Pendiente de negocio:** reglas detalladas de transición y contrato funcional con el llamador.

### Historia clínica electrónica

Registro clínico longitudinal del paciente compuesto por las atenciones y demás información asistencial que corresponda.

- No equivale al número administrativo de historia ni al estado de una cita.
- **Confirmado por negocio:** formará parte del ERP futuro y estará relacionada con el flujo operativo de atención.
- **Confirmado por negocio:** se espera incluir evolución, diagnósticos, indicaciones, órdenes, recetas, resultados y otros componentes.
- **Propuesta técnica:** debe preservar autoría, integridad, confidencialidad, trazabilidad y contexto de cada atención.
- **Propuesta funcional aprobada provisionalmente:** el médico tratante crea y cierra sus atenciones; profesionales clínicos autorizados consultan la historia según permisos y contexto asistencial.
- **Propuesta funcional aprobada provisionalmente:** personal administrativo, incluido un administrador técnico sin capacidad clínica, no altera contenido clínico.
- **Propuesta funcional aprobada provisionalmente:** una atención cerrada no se sobrescribe; una adenda/corrección conserva autor, fecha/hora, motivo y contenido anterior cuando corresponda.
- **Propuesta funcional aprobada provisionalmente:** existe una única historia longitudinal por paciente para todas las sedes; cada atención conserva sede, consultorio, profesional, especialidad/servicio y fecha/hora.
- **Propuesta funcional aprobada provisionalmente:** un profesional autorizado puede consultar antecedentes de otras sedes según permisos y contexto asistencial, sin fragmentar la historia.
- **Pendiente de negocio:** información especialmente sensible, conservación y participación clínica de enfermería u otras profesiones.

### Área de Trabajo Médico

Entorno funcional unificado desde el que el profesional organiza y realiza su trabajo clínico del día.

- Puede reunir agenda, pacientes en espera/llamados/en consulta, reevaluaciones, alertas, historia longitudinal y documentos/resultados pendientes.
- No equivale a una pantalla específica ni obliga a copiar un producto externo.
- **Confirmado por negocio:** el profesional debe atender desde una experiencia clínica integrada y no desde módulos administrativos inconexos.
- **Referencia externa:** el concepto de “Área de Trabajo” de HOSIX inspira esta organización funcional exclusivamente a nivel conceptual.
- **Pendiente de negocio:** prioridad de información, estructura clínica por especialidad y evolución posterior al núcleo aprobado.

### Registro clínico

Contenido asistencial atribuido a uno o más profesionales dentro del contexto de una atención.

- Puede comprender evolución, diagnóstico, indicaciones, tratamiento y documentos vinculados.
- **Propuesta técnica:** una vez cerrado o firmado no debe sobrescribirse; correcciones y adendas conservan autor, fecha/hora, motivo y contenido previo.
- No equivale a una sugerencia generada por IA ni a un estado operativo de atención.

### Cierre clínico

Acción explícita mediante la que el profesional responsable finaliza y valida institucionalmente el contenido de una atención.

- Mientras la atención permanece ABIERTA, el profesional responsable puede trabajar sobre su contenido.
- **Propuesta funcional aprobada provisionalmente:** una vez CERRADA preserva integridad histórica y solo admite adendas/correcciones trazables.
- **Propuesta funcional aprobada provisionalmente:** la reapertura excepcional exige permiso clínico especial, motivo y auditoría completa; Dirección Médica o responsable clínico son candidatos futuros.
- No equivale a una firma electrónica o digital avanzada, que permanece como concepto independiente y futuro.

### Dato clínico estructurado

Información representada mediante atributos o catálogos que permiten validación, búsqueda y reutilización controlada.

- **Propuesta funcional aprobada provisionalmente:** se estructuran cuando correspondan paciente, profesional, sede, consultorio, fecha/hora, especialidad, servicio, alergias, antecedentes, diagnósticos, medicamentos/recetas, órdenes, resultados, reevaluaciones y estados.
- No obliga a transformar toda la atención en catálogos o campos rígidos.

### Contenido clínico narrativo

Texto profesional que conserva libertad clínica para documentar motivo, anamnesis, examen/evolución, impresión, plan, indicaciones y otros contenidos.

- **Propuesta funcional aprobada provisionalmente:** coexistirá con datos estructurados en un modelo híbrido.

### Acceso excepcional de emergencia

Acceso clínico extraordinario fuera del contexto ordinario, también denominado “break glass”.

- **Propuesta funcional aprobada provisionalmente:** debe exigir motivo y registrar usuario, paciente, fecha/hora y acción realizada.
- Está sujeto a auditoría y no concede privilegios clínicos permanentes.
- **Pendiente de negocio:** roles habilitados, situaciones admitidas, revisión posterior y consecuencias por uso indebido.

### Orden o petición clínica

Solicitud de una prueba, estudio, procedimiento u otra actuación clínica realizada desde una atención.

- Puede producir uno o varios resultados y mantener estado propio.
- **Pendiente de definición clínica:** tipos, prioridad, responsables y sistemas que la ejecutan.

### Resultado clínico

Información producida como respuesta a una orden o estudio, conservando origen, paciente, fechas y contexto asistencial.

- Puede ser estructurado, un informe, una imagen o una referencia controlada a un sistema externo.
- No debe incorporarse automáticamente como conclusión clínica del profesional.

### IA asistiva clínica

Capacidad futura que ayuda a resumir, organizar, alertar o preparar borradores sin sustituir el juicio profesional.

- **Confirmado por negocio:** el profesional toma la decisión clínica y revisa, acepta o descarta toda sugerencia.
- **Propuesta técnica:** las sugerencias deben distinguirse del contenido validado o firmado y ser auditables cuando corresponda.
- **Candidato futuro:** no forma parte obligatoria de la primera versión ni implica seleccionar ahora un modelo o proveedor.

### Reconsulta

Atención de continuidad derivada de una atención previa, por ejemplo para revisar un examen solicitado y completar la evaluación.

- No debe tratarse automáticamente como estado de cita.
- **AS-IS:** hay precio y días de reconsulta; el código también consulta un estado `REEVALUACION` inexistente en el esquema.
- **Confirmado por negocio:** normalmente no genera un nuevo cobro y debe vincularse con la atención/cita original.
- **Confirmado por negocio:** el horario aproximado observado de 12:30 a 13:30 es una práctica operativa, no una regla fija del diseño.
- **Confirmado en código:** `doctor_services` ya contempla un concepto de días de reconsulta, aunque su implementación heredada no se considera definitiva.
- **Propuesta funcional aprobada provisionalmente:** la vigencia gratuita se define por profesional + servicio, o por una política equivalente suficientemente flexible, nunca como una única constante global.
- **Pendiente de negocio:** número exacto de días por combinación, elegibilidad y excepciones.

## 6. Comercial, caja y facturación

### Venta

Acuerdo comercial que registra qué se entrega o presta, a quién, a qué precio y con qué tratamiento tributario.

- Puede originar uno o más cargos y documentos.
- No equivale al pago ni al comprobante.
- **Confirmado AS-IS:** el componente de ventas crea voucher, líneas y pagos en una operación.
- **Propuesta técnica:** debe conservar líneas históricas aunque cambie el catálogo.

### Cargo u obligación

Monto que una persona o entidad debe a CEO Salud por una venta, cita, atención u otro concepto autorizado.

- Tiene monto original, saldo y estado de cobranza.
- Puede existir antes o después del documento tributario, según regla de negocio.
- **AS-IS:** está implícito y duplicado entre campos de cita y saldo calculado de voucher.
- **Propuesta técnica:** debe ser la fuente única de deuda.

### Pago

Registro de valor recibido para extinguir total o parcialmente una obligación.

- Incluye método, importe, referencia, fecha y actor.
- Puede requerir reverso o devolución; no debe borrarse para corregirlo.
- **Confirmado AS-IS:** efectivo, tarjeta, Yape y Plin asociados a voucher/turno.
- **Confirmado por negocio:** el pago de adelanto debe conservar evidencia verificable, actualmente una captura donde pueda comprobarse el número de operación, y trazabilidad de su verificación.
- **Propuesta técnica:** evidencia, referencia declarada y verificación deben tratarse como conceptos relacionados pero distintos.
- **Pendiente de negocio:** distribución definitiva entre RECEPCIÓN/CAJA, criterio de verificación, conservación de evidencia, aplicación múltiple, crédito, devoluciones y vuelto.

### Verificación de pago

Acto por el que un usuario autorizado revisa la evidencia y determina el estado de validación de un pago declarado.

- **Propuesta funcional aprobada provisionalmente:** conserva monto, método, número de operación, evidencia, verificador, fecha/hora y estado.
- Será responsabilidad operativa de RECEPCIÓN y/o CAJA según la matriz definitiva.
- No equivale a autorizar una excepción al adelanto.

### Autorización de excepción al adelanto

Aprobación para confirmar una cita sin cumplir la regla general del 50 %.

- **Propuesta funcional aprobada provisionalmente:** constituye una capacidad separada de verificar pagos.
- Puede corresponder a jefatura/dirección, determinados médicos u otros roles que se definan.
- **Pendiente de negocio:** lista definitiva de autorizadores y límites de la excepción.

### Movimiento de caja

Ingreso o egreso que modifica el efectivo esperado de un turno y no representa necesariamente una venta.

- Debe indicar concepto, actor y, cuando corresponda, autorización/reverso.
- **Confirmado AS-IS:** ingresos y egresos manuales editables/eliminables.
- No equivale a pago: un pago puede afectar caja, pero conserva su propio origen.

### Comprobante

Evidencia entregada al cliente de una operación o cobro.

- Puede ser interno o tributario.
- Un ticket interno no adquiere validez tributaria por imprimirse.
- **Confirmado AS-IS:** TICKET, BOLETA, FACTURA y notas comparten `vouchers` e impresión.
- **Propuesta técnica:** distinguir comprobante interno de documento tributario.

### Documento tributario

Documento fiscal emitido conforme a las reglas aplicables y cuyo ciclo incluye generación, firma, envío, respuesta y conservación cuando corresponda.

- No equivale a la vista HTML impresa.
- **AS-IS:** hay campos y estados preparatorios, pero no emisión SUNAT.
- **Propuesta funcional aprobada provisionalmente:** el diseño contempla BOLETA, FACTURA y mecanismos de corrección/anulación, preparando notas de crédito, otros correctivos, bajas, rechazos y contingencia.
- **Propuesta funcional aprobada provisionalmente:** acciones fiscales sensibles requieren permisos financieros/administrativos específicos; operar caja no los concede automáticamente.
- **Pendiente de negocio:** alcance tributario definitivo, empresa/establecimientos, proveedor y reglas fiscales validadas por contabilidad.

### Consulta de RUC

Obtención de datos registrales de una persona jurídica o contribuyente a partir de su RUC.

- **Confirmado en código:** `SunatService` y `Sales::buscarPorRuc()` consultan y completan datos del cliente para FACTURA.
- No equivale a generar, firmar ni enviar un documento electrónico a SUNAT.
- **Pendiente de validación productiva:** proveedor, credenciales, formato vigente, disponibilidad y calidad de respuesta.

### Emisión electrónica SUNAT

Ciclo fiscal que transforma un documento tributario en una representación electrónica firmada, enviada y respondida por SUNAT/OSE.

- Comprende generación, firma, envío, respuesta, CDR, aceptación/rechazo, consulta, reintento y anulación cuando corresponda.
- **Confirmado en código:** solo existen campos y estados preparatorios; no existe el ciclo de emisión.
- No equivale a consulta RUC ni a impresión de un voucher interno.

## 7. Reglas terminológicas transversales

- “Cliente” debe especificar si es paciente atendido, pagador, persona o empresa.
- “Doctor” se usará solo cuando el negocio quiera limitar el concepto; en el modelo general se prefiere “profesional de salud”.
- “Historia clínica” no debe usarse para referirse solo al número administrativo del paciente.
- “Factura electrónica” o “boleta electrónica” solo se usará cuando exista emisión fiscal verificable.
- “Anular”, “eliminar”, “reversar” y “devolver” no son equivalentes.
- “Estado de cita”, “estado de atención”, “estado de cobranza” y “estado SUNAT” son ciclos independientes.
