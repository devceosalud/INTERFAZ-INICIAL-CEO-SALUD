# Decisiones estructurales de negocio — cierre de Fase 3

## 1. Propósito y niveles de certeza

Este documento conserva las decisiones acumuladas y registra qué preguntas fueron reemplazadas por respuestas posteriores. No define tablas, APIs, pantallas ni implementación.

- **CONFIRMADO POR NEGOCIO:** dirección funcional estable comunicada por Rodrigo.
- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** regla vigente para el Blueprint, sustituible mediante una decisión posterior trazada.
- **REFERENCIA EXTERNA:** orientación conceptual sin copiar un producto.
- **CONFIRMADO EN CÓDIGO:** evidencia observada en el repositorio.
- **PROPUESTA TÉCNICA:** criterio de consistencia o seguridad que no decide política empresarial.
- **PENDIENTE DE NEGOCIO:** definición aún necesaria antes de implementar el alcance correspondiente.
- **PENDIENTE DE VALIDACIÓN PRODUCTIVA:** comportamiento no comprobado en operación real.

## 2. Decisiones confirmadas de alcance

### D-01 — Organización preparada para múltiples sedes

- CEO Salud opera actualmente una sede y prevé una segunda.
- El TO-BE admite sedes y asociación de consultorios, cajas, puntos de emisión, almacenes, horarios y otros recursos.
- No se construirán funciones multisede avanzadas antes de necesitarlas.

### D-02 — Identidades y autorización granular

- Persona, trabajador, usuario y profesional de salud son conceptos relacionados pero distintos.
- Todo trabajador que opere el ERP tendrá usuario y capacidades explícitas.
- Profesión, cargo, rol y permiso no son equivalentes.
- Ser ADMINISTRADOR no concede capacidades clínicas ni fiscales sensibles.

### D-03 — Historia clínica y Área de Trabajo Médico

- El ERP incorpora Historia Clínica Electrónica completa y longitudinal.
- El profesional trabaja desde un área clínica integrada con lista de trabajo, contexto, historia, registro, solicitudes y documentación.
- HOSIX es solo **REFERENCIA EXTERNA** conceptual; no se replica su arquitectura ni interfaz.
- IA asistiva es evolución futura y nunca reemplaza la decisión profesional.

### D-04 — Agenda, pre-reserva y cita

- Se distinguen disponibilidad, pre-reserva temporal y cita confirmada.
- La regla general de confirmación exige 50 % de adelanto.
- Si existe competencia por un horario, tiene prioridad quien realiza el abono requerido.
- La excepción requiere autorización independiente y auditable.

### D-05 — Reevaluación

- Deriva de una atención previa, se vincula con la atención/cita original y normalmente no genera un nuevo cobro.
- Su horario habitual no se convierte en regla fija.

### D-06 — Productos y stock

- El ERP vende servicios y productos y conserva tipo, cantidad y precio aplicado.
- El manejo de stock forma parte del alcance obligatorio.

### D-07 — Integraciones críticas separadas

- **CONFIRMADO EN CÓDIGO:** existen consulta DNI, consulta RUC, comprobante interno y campos preparatorios SUNAT.
- **CONFIRMADO EN CÓDIGO:** no existe emisión electrónica SUNAT completa.
- Consulta RUC, comprobante interno y emisión fiscal son capacidades separadas.
- El ERP será la fuente canónica del estado de atención frente al llamador.

## 3. Decisiones provisionales P-01 a P-05

Estas respuestas reemplazaron el segundo bloque de cinco preguntas.

### P-01 — Gobierno clínico

- Médicos tratantes crean y cierran sus atenciones.
- Profesionales clínicos autorizados consultan según permisos y contexto.
- Personal administrativo no modifica contenido clínico.
- Una atención cerrada solo se corrige mediante adenda trazable.
- Se admite break glass con motivo y auditoría de usuario, paciente, fecha/hora y acción.
- La primera etapa prioriza escritura para médicos; enfermería y otros profesionales son evolución configurable.

### P-02 — Primera entrega útil de HCE

- Incluye contexto, antecedentes, alergias, cronología, motivo, evolución/examen, diagnósticos, indicaciones, tratamiento, recetas, órdenes, resultados, informes/adjuntos, reevaluaciones, documentos, responsable, fecha/hora, cierre, adendas, trazabilidad, lista de trabajo y estado operativo.
- Plantillas avanzadas, laboratorio profundo, imágenes, alertas avanzadas, IA y funciones especializadas son evolución.

### P-03 — Vigencia de pre-reserva

- Duración predeterminada de 15 minutos y configurable.
- Sin adelanto validado al vencer, pasa a LIBERADA.
- Un usuario con permiso puede extenderla.
- Se auditan usuario, fecha/hora, vencimiento anterior, nuevo vencimiento y motivo cuando corresponda.

### P-04 — Excepción y verificación de pagos

- Autorizar una excepción al 50 % y verificar un pago son capacidades independientes.
- Jefatura/dirección, determinados médicos u otros roles podrán autorizar según la matriz futura.
- RECEPCIÓN y/o CAJA verificarán pagos según la matriz definitiva.
- El pago conserva monto, método, número de operación, evidencia, verificador, fecha/hora y estado de verificación.

### P-05 — Política de reevaluación

- La vigencia gratuita no es una constante global.
- Se configura por profesional + servicio o mediante una política equivalente flexible.
- `doctor_services.dias_reconsulta` es antecedente y no diseño definitivo.
- Los días exactos quedan pendientes de formalización empresarial.

## 4. Decisiones provisionales P-06 a P-10

Estas respuestas reemplazan el tercer bloque de cinco preguntas estructurales.

### P-06 — Historia clínica única multisede

- Cada paciente tiene una sola historia longitudinal dentro de CEO Salud.
- La historia no se fragmenta por sede.
- Profesionales autorizados consultan antecedentes generados en otras sedes según permisos.
- Cada atención conserva sede, consultorio, profesional, especialidad/servicio y fecha/hora.
- Pueden definirse restricciones futuras para información especialmente sensible sin crear historias separadas.

### P-07 — Cierre institucional y reapertura

- Mientras una atención está ABIERTA, el profesional responsable puede trabajar sobre su contenido.
- El profesional realiza una acción explícita FINALIZAR/CERRAR ATENCIÓN.
- Una atención CERRADA no se sobrescribe; correcciones posteriores son adendas trazables.
- La reapertura excepcional exige permiso clínico especial, motivo obligatorio y auditoría completa.
- Dirección Médica o responsable clínico son candidatos para ese permiso.
- Cierre/validación institucional y firma electrónica/digital avanzada son conceptos distintos.

### P-08 — Modelo clínico híbrido

- Se estructuran cuando corresponda paciente, profesional, sede, consultorio, fecha/hora, especialidad, servicio, alergias, antecedentes, diagnósticos, medicamentos/recetas, órdenes, resultados, reevaluaciones y estados.
- Motivo, anamnesis, examen/evolución, impresión clínica, plan, indicaciones y otros textos pueden comenzar como narrativa.
- No toda la atención se convierte en catálogos o campos rígidos.

### P-09 — Inventario basado en movimientos

- El diseño base sigue sede → almacén → movimientos → stock.
- El primer despliegue puede utilizar un único almacén lógico.
- El stock no se representa únicamente como número mutable; los movimientos/kardex conservan trazabilidad.
- Lotes, vencimientos, farmacia, compras, proveedores y transferencias son evolución futura.

### P-10 — Preparación para facturación electrónica

- El alcance provisional prepara BOLETA, FACTURA y mecanismos de corrección/anulación.
- Se contempla evolución hacia notas de crédito, otros documentos correctivos, bajas, rechazos y contingencia.
- El alcance tributario definitivo se valida con el área contable/administrativa antes de implementar SUNAT.
- Rechazos, notas, anulaciones y acciones fiscales sensibles requieren permisos específicos.
- Un usuario de caja no obtiene automáticamente esas facultades.

## 5. Decisiones pendientes después del cierre

No bloquean el inicio del diseño técnico de Fase 4, pero deberán resolverse antes de implementar el alcance relacionado:

- recursos, numeraciones y procesos no clínicos compartidos entre sedes;
- datos clínicos especialmente sensibles y restricciones adicionales;
- roles de break glass y responsables de revisar su uso;
- participación clínica de enfermería y otros profesionales;
- vocabularios clínicos específicos;
- documentos, órdenes y resultados prioritarios por especialidad;
- días exactos de reevaluación por profesional + servicio;
- límite de extensiones y avisos de pre-reserva;
- autorizadores definitivos de excepción y reparto RECEPCIÓN/CAJA;
- cantidad inicial de almacenes y momento de incorporar transferencias;
- alcance definitivo de farmacia, lotes, vencimientos, compras y proveedores;
- alcance tributario validado por contabilidad, puntos de emisión y responsabilidades;
- contrato funcional/técnico con el llamador tras auditar su repositorio;
- proveedores definitivos para DNI, RUC y SUNAT/OSE;
- prioridades e indicadores de reportes e IA asistiva.

## 6. Regla de evolución

Una decisión provisional solo se reemplaza mediante una nueva decisión registrada con fecha, responsable, motivo, alcance afectado y estrategia de transición. La documentación funcional prevalece sobre supuestos heredados no confirmados, pero no autoriza borrar datos históricos ni modificar producción.
