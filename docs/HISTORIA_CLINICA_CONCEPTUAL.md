# Historia Clínica Electrónica conceptual — CEO Salud

## 1. Propósito y nivel de certeza

Este documento define el modelo funcional inicial de la Historia Clínica Electrónica (HCE) del ERP futuro. No diseña pantallas, tablas, APIs, clases ni una primera versión cerrada.

- **CONFIRMADO POR NEGOCIO:** el ERP incluirá una HCE completa, longitudinal y relacionada con el flujo operativo de atención.
- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** regla vigente para el Blueprint que podrá sustituirse únicamente registrando la nueva decisión y su motivo.
- **REFERENCIA EXTERNA:** HOSIX se usa exclusivamente como inspiración conceptual para un área de trabajo médico integrada. No se copiarán sus interfaces, arquitectura, código ni estructuras propietarias.
- **PROPUESTA TÉCNICA:** separar el estado operativo de atención, el registro clínico firmado y las integraciones, manteniendo trazabilidad entre ellos.
- **PENDIENTE DE NEGOCIO:** contenido clínico exacto por especialidad y alcance de cada entrega.
- **PENDIENTE DE VALIDACIÓN PRODUCTIVA:** flujo real utilizado hoy por los profesionales, documentos existentes y necesidades de continuidad desde soportes actuales.

Referencias oficiales consultadas:

- [HOSIX — sistema integrado y modular](https://www.sivsa.com/site/hosix/)
- [HOSIX Clinic — Médicos y Área de Trabajo](https://www.sivsa.com/site/hosix/hosix-clinic/medicos/)

De estas referencias se extraen únicamente ideas generales útiles: lista de trabajo, contexto clínico resumido, historia accesible durante la atención, registro estructurado, peticiones, resultados y documentos desde un entorno clínico integrado.

## 2. Visión funcional

**CONFIRMADO POR NEGOCIO:** el profesional debe disponer de una única Área de Trabajo Médico orientada a atender pacientes, no de múltiples módulos administrativos inconexos.

```text
PROFESIONAL
    → lista de trabajo del día
    → paciente seleccionado
    → contexto clínico relevante
    → historia longitudinal
    → registro de la atención
    → solicitudes y consulta de información clínica
    → documentación
    → finalización de la atención
```

El área de trabajo es un concepto funcional. No implica todavía una pantalla única, un diseño visual ni una arquitectura técnica específica.

## 3. Principios clínicos

1. **CONFIRMADO POR NEGOCIO — Decisión humana:** el profesional de salud toma toda decisión clínica.
2. **PROPUESTA TÉCNICA — Longitudinalidad:** la historia presenta continuidad entre atenciones sin sobrescribir el pasado.
3. **PROPUESTA TÉCNICA — Autoría:** cada registro identifica profesional responsable y fecha/hora.
4. **PROPUESTA TÉCNICA — Integridad:** un contenido cerrado o firmado no se corrige mediante borrado silencioso; las correcciones y adendas conservan el original y su motivo.
5. **PROPUESTA TÉCNICA — Necesidad de acceso:** cada actor consulta solo la información necesaria para su función.
6. **PROPUESTA TÉCNICA — Separación de ciclos:** cita, atención, documento clínico, orden y resultado tienen estados propios relacionados.
7. **PROPUESTA TÉCNICA — Diseño evolutivo:** reglas y componentes variables no deben quedar fijados de forma irreversible.

## 3.1 Gobierno clínico provisional

- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** los médicos tratantes crean y cierran sus propias atenciones clínicas.
- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** profesionales clínicos autorizados consultan la historia longitudinal según permisos y contexto asistencial.
- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** la escritura clínica inicial se prioriza para médicos; enfermería y otras profesiones quedan como evolución configurable.
- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** el personal administrativo no modifica contenido clínico y el rol ADMINISTRADOR no concede por sí mismo capacidades clínicas.
- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** una atención cerrada no se sobrescribe; las correcciones se realizan mediante adenda trazable con autor, fecha/hora, motivo y contenido anterior cuando corresponda.
- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** existe acceso excepcional de emergencia con motivo y auditoría de usuario, paciente, fecha/hora y acción.
- **PENDIENTE DE NEGOCIO:** roles habilitados para emergencia, revisión posterior, política definitiva de firma y participación de otras profesiones.

## 3.2 Historia única multisede

- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** cada paciente tiene una única historia longitudinal dentro de CEO Salud; no se crean historias independientes por sede.
- Profesionales autorizados pueden consultar antecedentes producidos en otras sedes según permisos y contexto asistencial.
- Cada atención conserva sede, consultorio, profesional, especialidad/servicio y fecha/hora.
- Podrán existir restricciones futuras para información especialmente sensible sin fragmentar la historia.

## 3.3 Cierre y reapertura

- Mientras una atención está ABIERTA, el profesional responsable puede trabajar sobre su contenido.
- El profesional ejecuta una acción explícita FINALIZAR/CERRAR ATENCIÓN.
- Una atención CERRADA conserva integridad histórica; cualquier corrección posterior es una adenda trazable.
- Una reapertura excepcional requiere permiso clínico especial, motivo y auditoría completa; Dirección Médica o responsable clínico son candidatos funcionales.
- El cierre constituye validación institucional del profesional y no se confunde con una eventual firma electrónica/digital avanzada.

## 3.4 Modelo híbrido de información

**Estructurado desde la primera entrega cuando corresponda:** paciente, profesional, sede, consultorio, fecha/hora, especialidad, servicio, alergias, antecedentes relevantes, diagnósticos, medicamentos/recetas, órdenes, resultados, reevaluaciones y estados de atención.

**Narrativo inicialmente:** motivo de consulta, anamnesis, examen/evolución, impresión clínica, plan, indicaciones y otros textos clínicos.

La estructura permite seguridad, búsqueda e integración; la narrativa conserva libertad clínica. No se busca convertir toda la atención en campos rígidos.

## 4. Área de Trabajo Médico

### 4.1 Lista de trabajo

**CONFIRMADO POR NEGOCIO:** debe poder reunir, según autorización y relación profesional:

- pacientes asociados al profesional;
- agenda del día;
- pacientes que llegaron y están en espera;
- pacientes llamados;
- pacientes en consulta;
- atenciones pendientes de completar;
- reevaluaciones;
- alertas clínicas relevantes;
- documentos, órdenes o resultados pendientes.

**PROPUESTA TÉCNICA:** la lista debe derivar de estados canónicos del ERP y no de duplicados mantenidos independientemente por el llamador.

### 4.2 Contexto del paciente

Al seleccionar un paciente, el profesional debe poder acceder de manera contextual a:

- identificación inequívoca;
- alertas críticas visibles según relevancia;
- antecedentes y alergias;
- problemas o diagnósticos previos;
- tratamientos activos cuando existan;
- atenciones y reevaluaciones anteriores;
- línea de tiempo clínica;
- órdenes, resultados, informes y documentos vinculados;
- profesional y fecha/hora de cada registro.

La composición y prioridad visual de este contexto quedan **PENDIENTES DE DEFINICIÓN CLÍNICA**.

## 5. Relación entre agenda, atención e historia

```text
AGENDA / DISPONIBILIDAD
    → PRE-RESERVA
    → CITA CONFIRMADA
    → LLEGADA
    → EN ESPERA
    → LLAMADO A CONSULTORIO
    → EN CONSULTA
    → ATENCIÓN CLÍNICA
    → REGISTROS / ÓRDENES / DOCUMENTOS
    → FINALIZACIÓN
    → HISTORIA LONGITUDINAL
```

- La cita organiza una prestación futura; no es la historia clínica.
- La atención representa el encuentro asistencial y aporta contexto al registro clínico.
- La historia longitudinal reúne registros clínicos de distintas atenciones sin perder su origen.
- Finalizar el estado operativo no debe alterar silenciosamente registros clínicos ya cerrados.
- Una reevaluación referencia la atención/cita original y agrega continuidad a la misma historia.

## 6. Relación funcional con el llamador

**CONFIRMADO POR NEGOCIO:** el llamador formará parte del flujo de atención y el ERP será la fuente principal de su estado.

```text
CITA CONFIRMADA
    → PACIENTE LLEGA
    → EN ESPERA
    → PROFESIONAL DISPONIBLE
    → LLAMADO
    → “Paciente, pasar al consultorio X”
    → EN CONSULTA
    → ATENCIÓN CLÍNICA
    → FINALIZADA
```

- **PROPUESTA TÉCNICA:** el llamador consume o comunica eventos sin convertirse en fuente paralela de verdad.
- **PROPUESTA TÉCNICA:** la exposición pública debe minimizar datos personales y clínicos.
- **PENDIENTE DE AUDITORÍA:** capacidades, datos, estados y restricciones del repositorio `devceosalud/LLAMADOR-PACIENTE-CEO`.
- **PENDIENTE DE NEGOCIO:** reglas de llamado, repetición, prioridad, ausencia y reasignación de consultorio.

## 7. Clasificación de capacidades clínicas

### 7.1 OBLIGATORIO NÚCLEO

Capacidades necesarias para que exista una HCE longitudinal y trazable:

- identificación inequívoca del paciente;
- Área de Trabajo Médico y lista de pacientes del profesional;
- contexto de cita, llegada y atención;
- antecedentes relevantes y alergias;
- historial cronológico de atenciones;
- motivo de consulta;
- registro de evolución clínica;
- diagnósticos de la atención;
- indicaciones y tratamiento;
- recetas;
- órdenes o peticiones;
- resultados;
- informes y documentos adjuntos;
- documentos generados;
- vínculo de reevaluaciones con la atención original;
- profesional responsable y fecha/hora;
- cierre del registro, correcciones y adendas auditables;
- control de acceso y trazabilidad de consulta/modificación;
- capacidad de adjuntar o vincular documentación clínica relevante.

El carácter obligatorio no define que todos estos elementos deban entregarse simultáneamente ni su diseño físico.

### 7.2 EVOLUCIÓN

Capacidades confirmadas como dirección funcional, cuya prioridad y profundidad se definirán progresivamente:

- plantillas clínicas por especialidad;
- integración profunda con laboratorio;
- integración de imágenes clínicas;
- alertas clínicas avanzadas;
- participación clínica de enfermería y otros profesionales;
- otras funciones especializadas;
- resúmenes longitudinales y apoyo asistivo mediante IA.

### 7.3 PENDIENTE DE DEFINICIÓN CLÍNICA

- vocabularios y codificaciones clínicas requeridas;
- contenido obligatorio y estructura por especialidad y tipo de atención;
- reglas definitivas de firma, reapertura, coautoría y revisión de adendas;
- perfiles que pueden consultar o registrar cada sección;
- manejo de datos aportados por terceros;
- tipos de receta, orden, informe, consentimiento y certificado;
- prioridad de laboratorio, radiología, farmacia u otras integraciones;
- conservación, exportación y entrega de copia de historia;
- alertas que se consideran críticas y responsables de mantenerlas.
- reglas y revisión del acceso excepcional de emergencia.

## 8. Documentos, órdenes y resultados

**PROPUESTA TÉCNICA:** una orden, un resultado y un documento no deben ser archivos aislados sin contexto. Deben conservar relación con paciente, atención, autor/origen, tipo, estado y fecha/hora.

- Una orden expresa una solicitud clínica y puede producir uno o varios resultados.
- Un resultado conserva su origen y puede ser revisado durante atenciones posteriores.
- Un informe o documento clínico puede derivarse de la atención, pero mantiene su propio estado.
- Una imagen puede almacenarse externamente; la HCE debe conservar al menos una referencia controlada cuando ese alcance se confirme.

## 9. IA asistiva futura

**CONFIRMADO POR NEGOCIO:** la arquitectura funcional debe permitir incorporar apoyo mediante IA en el futuro sin delegar la decisión clínica.

**CANDIDATO FUTURO:** resumen de antecedentes, resumen longitudinal, identificación de información relevante, recordatorios, alertas, organización documental, sugerencias de apoyo, detección de inconsistencias y borradores clínicos.

Reglas obligatorias:

- el profesional revisa toda salida antes de utilizarla;
- puede aceptarla, modificarla o descartarla;
- la IA no registra automáticamente una decisión clínica definitiva;
- el contenido sugerido permanece diferenciado del contenido validado o firmado;
- origen, revisión y aceptación se auditan cuando corresponda;
- el funcionamiento clínico esencial no depende de la disponibilidad de IA.

No se seleccionan modelos, proveedores, datos de entrenamiento ni técnicas en esta fase.

## 10. Decisiones pendientes prioritarias

Las decisiones anteriores de gobierno y núcleo inicial fueron reemplazadas por la propuesta funcional provisional registrada en este documento. Continúan pendientes:

1. Tipos de información especialmente sensible que podrían necesitar restricciones adicionales.
2. Roles de break glass y responsables de revisar su uso.
3. Vocabularios clínicos concretos para diagnósticos, alergias, medicamentos, órdenes y resultados.
4. Tipos de documentos, órdenes y resultados prioritarios por especialidad.
5. Flujo real y contrato funcional con el llamador después de auditar su repositorio.
