# Alcance preliminar de módulos del ERP CEO Salud

## 1. Criterio de clasificación

- **OBLIGATORIO:** capacidad demostrada por el ERP actual o confirmada previamente. Debe preservarse funcionalmente, aunque su diseño cambie.
- **PROBABLE:** consecuencia razonable del Blueprint o necesaria para estabilizar una capacidad actual, pero requiere confirmar alcance de negocio.
- **CANDIDATO:** podría aportar valor, pero el repositorio no demuestra necesidad suficiente.
- **FUERA DE ALCANCE POR AHORA:** no debe diseñarse ni implementarse hasta que cambie explícitamente el alcance.

“Obligatorio” no significa conservar la implementación heredada; significa conservar la necesidad funcional demostrada.

Dentro de las matrices se distingue **Confirmado por negocio**, **Propuesta funcional aprobada provisionalmente**, **Confirmado en código**, **Referencia externa**, **Propuesta técnica**, **Pendiente de negocio** y **Pendiente de validación productiva** para evitar convertir una preparación conceptual en un compromiso funcional no aprobado.

## 2. Módulos obligatorios

| Módulo | Capacidad mínima demostrada | Límite preliminar |
|---|---|---|
| Identidad y autenticación | Ingreso/salida de usuarios internos. | Cuenta segura, estado de acceso y sesión. |
| Usuarios, roles y permisos | Administración de usuarios y control por rol. | Sustituir matriz provisional cuando se definan cargos; no asumir permisos finales. |
| Organización multisede | CEO Salud opera una sede y prevé una segunda. | **Propuesta funcional aprobada provisionalmente:** HCE única multisede y contexto de sede por atención; operación no clínica compartida pendiente. |
| Personal/trabajadores | Quienes operen el ERP tendrán acceso y deben distinguirse de usuario/profesional. | **Confirmado por negocio:** separación conceptual; tipos de vínculo y matriz definitiva pendientes. |
| Pacientes | Registrar, buscar, consultar, actualizar e inactivar. | Identidad/contacto, número de historia seguro y captación. |
| Responsables | Registrar y mantener persona responsable vinculada al paciente. | Cardinalidad y representación pendientes, pero la capacidad actual debe preservarse. |
| Especialidades | Mantener catálogo activo/inactivo. | Catálogo clínico básico. |
| Profesionales de salud | Mantener profesional, CMP/RNE y especialidad. | **Confirmado por negocio:** concepto separado de trabajador/usuario; reglas de vinculación pendientes. |
| Servicios y tarifas | Mantener servicios y precio por profesional, reconsulta/ajustes actuales. | Se rediseñará el catálogo; las reglas exactas siguen pendientes. |
| Agenda y horarios | Mantener horarios y consultar calendario/disponibilidad. | Debe evitar solapes y distinguir agenda de cita. |
| Pre-reservas y citas | Captar solicitudes, retener temporalmente un horario y confirmar citas. | **Propuesta funcional aprobada provisionalmente:** 15 minutos configurables, liberación y extensión auditada. |
| Atención e historia clínica | Mantener flujo de atención e historial clínico longitudinal único entre sedes. | **Propuesta funcional aprobada provisionalmente:** núcleo inicial, cierre explícito, adendas y modelo híbrido definidos. |
| Área de Trabajo Médico | Reunir lista diaria, contexto del paciente, historia y registro clínico. | **Confirmado por negocio:** experiencia integrada; HOSIX es solo referencia externa conceptual. |
| Reevaluaciones | Vincular continuidad asistencial con la atención/cita original. | **Propuesta funcional aprobada provisionalmente:** política por profesional + servicio; días exactos pendientes. |
| Caja | Abrir/cerrar turno y calcular diferencia. | Una caja/turno consistente y trazable. |
| Movimientos de caja | Registrar ingresos y egresos no originados directamente en ventas. | Correcciones mediante reverso/auditoría, no borrado silencioso. |
| Ventas | Vender servicios y productos, identificando cantidad y precio aplicado. | **Confirmado por negocio:** ambos tipos; detalle fiscal y catálogo canónico pendientes. |
| Cargos y pagos | Registrar cobros parciales/totales y múltiples medios existentes. | Una fuente única de saldo; reglas de anticipos pendientes. |
| Evidencia y verificación de pagos | Conservar evidencia, referencia y trazabilidad de verificación. | **Propuesta funcional aprobada provisionalmente:** RECEPCIÓN/CAJA verifican; capacidad separada de excepciones. |
| Comprobantes internos | Crear ticket/voucher, líneas, serie/correlativo e impresión. | No confundir con emisión SUNAT. |
| Consulta DNI | Autocompletar datos del paciente mediante proveedor externo. | **Confirmado en código:** existe; preservar revisión/ingreso manual. **Pendiente de validación productiva.** |
| Consulta RUC | Completar datos del cliente empresarial. | **Confirmado en código:** existe; no equivale a emisión SUNAT. **Pendiente de validación productiva.** |
| Inventario básico | Controlar stock mediante almacén y movimientos/kardex. | **Propuesta funcional aprobada provisionalmente:** sede → almacén → movimientos → stock; evolución logística pendiente. |
| Configuración y pruebas seguras | Ejecutar el ERP y verificar su núcleo sin tocar producción/proveedores. | Preservar aislamiento y pruebas de regresión. |

## 3. Módulos probables

| Módulo | Justificación | Condición pendiente |
|---|---|---|
| Cuentas por cobrar | El AS-IS maneja saldos y pagos parciales de forma duplicada. | Alcance de crédito, cancelaciones y devoluciones. |
| Auditoría | Caja, pagos, permisos y agenda requieren trazabilidad. | Confirmar conservación y usuarios de consulta. |
| Notificaciones | Existe correo preparado y configuración SMS residual. | Canales, consentimiento y eventos aprobados. |
| Llamador | La integración futura con `LLAMADOR-PACIENTE-CEO` está confirmada como intención. | Contrato funcional, responsabilidades y prioridad de implementación. |
| Reportes operativos | El dashboard actual solo muestra agenda diaria. | Indicadores a priorizar después de estabilizar fuentes. |
| Organización avanzada entre sedes | La preparación multisede es obligatoria, pero la segunda sede aún no opera. | Recursos compartidos, numeraciones, catálogos y consolidación. |
| Historia clínica especializada | Plantillas avanzadas, laboratorio profundo, imágenes y alertas avanzadas. | Evolución posterior a la primera entrega útil. |
| Facturación electrónica real | Existen comprobantes y campos preparatorios, no generación/firma/envío/CDR. | Preparar boleta, factura y correctivos; alcance definitivo requiere validación contable. |

## 4. Módulos candidatos

| Módulo | Motivo de evaluación | No asumir todavía |
|---|---|---|
| Consultorios/recursos | Puede ser necesario para disponibilidad real. | No hay recurso físico en el AS-IS. |
| Farmacia | El stock está confirmado, pero no la dispensación regulada. | Alcance empresarial de farmacia pendiente. |
| Laboratorio | Hay ítems de laboratorio. | No existen órdenes, muestras ni resultados. |
| Compras y proveedores | Serían necesarios para inventario completo. | No hay flujo actual. |
| Almacenes múltiples | Posible con varias sedes o inventario real. | No existe hoy. |
| Lotes y vencimientos | Relevante para farmacia/insumos. | Solo si productos controlados lo requieren. |
| Cuentas por pagar | Posible consecuencia de compras. | No hay obligación a proveedores demostrada. |
| Documentos y consentimientos | Posible necesidad clínica/legal. | Depende del alcance asistencial. |
| Reportes gerenciales avanzados | Producción, rentabilidad y tendencias. | Definir indicadores y calidad de datos primero. |
| Comisiones profesionales | Hay campos de comisión en líneas. | No existe proceso de liquidación demostrado. |
| Convenios/aseguradoras | Podrían afectar tarifas y pagadores. | No hay evidencia en código. |
| IA asistiva clínica | Resúmenes, alertas, organización, inconsistencias, apoyo y borradores revisables. | El profesional decide; prioridad, gobierno y viabilidad se definirán en una fase futura. |

## 5. Fuera de alcance por ahora

| Módulo | Motivo |
|---|---|
| Nómina/planillas completas | No existe evidencia ni decisión de incorporar RRHH remunerativo. |
| Contabilidad general | Caja y ventas no equivalen a libro mayor contable. |
| Tesorería bancaria completa | No hay conciliación o gestión bancaria demostrada. |
| CRM/marketing avanzado | Canales de captación no prueban campañas/embudos completos. |
| Portal/app del paciente | No existe requerimiento confirmado. |
| Telemedicina | No existe evidencia ni definición. |
| Hospitalización/emergencia | El flujo actual es ambulatorio y no demuestra estos procesos. |
| Microservicios | No son un módulo de negocio ni se justifican por el tamaño actual. |
| IA clínica autónoma o decisoria | Contradice el principio confirmado de decisión profesional y queda expresamente fuera. |

## 6. Efecto de las decisiones confirmadas

| Decisión confirmada | Efecto sobre alcance |
|---|---|
| Multisede preparada | Organización pasa a obligatorio; las capacidades avanzadas para varias sedes siguen pendientes. |
| Identidades separadas | Personal y autorización granular pasan a obligatorios; la matriz final sigue pendiente. |
| Historia clínica completa | Atención e historia clínica pasan a obligatorios; detalle clínico, laboratorio y documentos especializados aún requieren alcance. |
| Área de Trabajo Médico | La experiencia clínica integrada pasa a obligatoria sin definir todavía pantallas. |
| Historia única multisede | Un paciente conserva una historia longitudinal; cada atención registra su contexto de sede y consultorio. |
| Cierre clínico | Cierre explícito, integridad histórica, adenda y reapertura excepcional auditada; firma digital avanzada no incluida. |
| Modelo híbrido | Datos clínicos clave estructurados y contenido clínico expresivo narrativo. |
| Pre-reserva y 50 % | Regla provisional de 15 minutos, liberación automática conceptual y extensión auditada; avisos y límites siguen pendientes. |
| Reevaluación vinculada | Política flexible por profesional + servicio; los días exactos siguen pendientes. |
| Gobierno clínico | Escritura inicial para médicos, consulta clínica autorizada, administración sin modificación, adendas y break glass auditado. |
| Verificación y excepción | Son capacidades independientes; autorizadores definitivos y reparto RECEPCIÓN/CAJA siguen pendientes. |
| Productos con stock | Kardex y relación sede/almacén son base obligatoria; farmacia, lotes, compras, proveedores y transferencias son evolución. |
| Integraciones críticas | DNI/RUC se preservan; SUNAT y llamador deben quedar desacoplados y requieren validación/auditoría antes de implementar contratos. |
| Facturación | Boleta, factura y mecanismos correctivos quedan preparados; acciones fiscales sensibles requieren permiso específico. |

## 7. Alcance clínico progresivo

### OBLIGATORIO NÚCLEO

- Área de Trabajo Médico y lista de pacientes del profesional;
- contexto de cita, llegada y atención;
- antecedentes relevantes y alergias;
- historia cronológica, motivo de consulta y evolución/examen clínico;
- diagnósticos, indicaciones, tratamiento y recetas;
- órdenes/peticiones y resultados;
- informes, adjuntos y documentos generados;
- responsable y fecha/hora de cada registro;
- contexto estructurado de sede, consultorio, profesional, especialidad y servicio;
- cierre, correcciones y adendas auditables;
- reapertura excepcional con permiso clínico, motivo y auditoría;
- reevaluaciones vinculadas;
- control y trazabilidad de acceso.

### EVOLUCIÓN

- plantillas avanzadas por especialidad;
- integración profunda con laboratorio e imágenes clínicas;
- alertas clínicas avanzadas;
- escritura por enfermería y otras profesiones;
- otras funciones especializadas;
- IA asistiva bajo revisión profesional.

### PENDIENTE DE DEFINICIÓN CLÍNICA

- contenido obligatorio y estructura por especialidad;
- datos especialmente sensibles, firma digital avanzada y revisión de emergencia;
- vocabularios clínicos;
- documentos, órdenes y resultados prioritarios;
- integraciones clínicas iniciales.

## 8. Regla de control de alcance

Ningún módulo probable o candidato debe convertirse en compromiso de implementación por aparecer en esta matriz. Requiere una decisión registrada, definición funcional y prioridad dentro del roadmap.
