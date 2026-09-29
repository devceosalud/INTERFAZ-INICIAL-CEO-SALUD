# Modelo de datos lógico TO-BE

## 1. Alcance

Este documento define entidades conceptuales, relaciones e invariantes del ERP futuro. No prescribe nombres de tablas, columnas, tipos SQL, índices ni migrations. Es una guía para el diseño físico posterior.

### Convenciones

- **Confirmado en código:** estructura o relación observable en el repositorio heredado.
- **Confirmado:** regla validada por negocio.
- **Propuesto:** solución técnica recomendada, ajustable durante el diseño detallado.
- **Pendiente de negocio:** requiere definición empresarial.
- **Pendiente de validación productiva:** requiere comprobar esquema o datos reales sin modificarlos.
- Las referencias históricas deben conservarse aunque una entidad operativa quede inactiva.
- Los importes se registran con moneda y precisión explícitas; nunca se reconstruye un importe histórico desde la tarifa vigente.
- Las fechas operativas conservan zona horaria y autoría cuando corresponda.

## 2. Principios del modelo

1. Una sola fuente de verdad por concepto.
2. Separar identidad natural, relación laboral, acceso al sistema y habilitación profesional.
3. Separar disponibilidad, pre-reserva, cita, atención y registro clínico.
4. Separar venta, obligación, pago, caja y documento tributario.
5. Conservar instantáneas históricas de nombres, precios, impuestos y datos fiscales donde el documento deba permanecer inmutable.
6. Modelar stock como resultado de movimientos, no como un número editable sin origen.
7. Representar correcciones mediante nuevas operaciones relacionadas, no mediante borrado de historia.
8. Incorporar sede en los recursos operativos desde el diseño, aunque inicialmente exista una sola.

## 3. Vista de relaciones principales

```text
Persona
  |-- Trabajador -- Usuario -- Roles/Capacidades
  |       |
  |       `-- Profesional de salud -- Especialidades/Servicios habilitados
  |
  `-- Paciente -- Responsables
          |
          `-- Historia longitudinal
                 `-- Atención -- Registro clínico -- Adendas/Contenido clínico

Sede
  |-- Consultorios
  |-- Cajas -- Turnos -- Movimientos
  |-- Puntos de emisión -- Series -- Documentos tributarios
  `-- Almacenes -- Movimientos de inventario -- Existencias proyectadas

Agenda profesional + recursos
  `-- Disponibilidad -- Pre-reserva -- Cita -- Atención

Catálogo/Oferta + Tarifa
  `-- Venta -- Líneas -- Obligación <- Aplicación de pago <- Pago
                                  |
                                  `-- Documento tributario
```

## 4. Organización

### 4.1 Sede

Representa una ubicación operativa de CEO Salud.

Relaciones:

- tiene consultorios, cajas, puntos de emisión y almacenes;
- contextualiza horarios, citas, atenciones, ventas y documentos cuando corresponda.

Invariantes propuestas:

- cada recurso físico pertenece a una sede;
- desactivar una sede no elimina su historia;
- inicialmente existirá una sede activa, pero no se codifica esa cantidad como restricción global.

### 4.2 Consultorio

Recurso físico donde se desarrolla una atención. Pertenece a una sede. Puede estar activo, temporalmente bloqueado o fuera de servicio.

Una atención registra el consultorio realmente utilizado, incluso si difiere del previsto en agenda.

### 4.3 Caja

Puesto de custodia y operación financiera perteneciente a una sede. Sus turnos, movimientos y arqueos son propios del dominio Caja.

### 4.4 Punto de emisión

Unidad operativa desde la que se emiten documentos tributarios. Pertenece a una sede y puede administrar una o varias series autorizadas.

### 4.5 Almacén

Ubicación lógica o física de inventario perteneciente a una sede. Es origen o destino de movimientos de stock.

## 5. Identidad, personal y acceso

### 5.1 Persona

Identidad natural compartida de un ser humano. Puede relacionarse con uno o más perfiles: paciente, trabajador, profesional o responsable.

Debe permitir deduplicación controlada sin asumir que un documento de identidad siempre existe o es infalible.

### 5.2 Trabajador

Relación de una persona con CEO Salud para realizar funciones operativas. Contiene estado y vigencia laboral/operativa, no credenciales.

**Confirmado:** todos los trabajadores que operen el ERP tendrán acceso según corresponda. Esto no implica que todo trabajador deba tener siempre un usuario activo.

### 5.3 Usuario

Cuenta de acceso vinculada, cuando corresponda, a un trabajador. Conserva credenciales, estado, mecanismos de recuperación y controles de seguridad.

Invariantes:

- una cuenta inactiva no autoriza operaciones aunque conserve roles históricos;
- la baja de acceso no borra la autoría pasada;
- cambios de roles y permisos son auditables.

### 5.4 Rol y capacidad

El rol agrupa capacidades. La capacidad representa una acción autorizable sobre un recurso: leer, crear, modificar, eliminar, aprobar, anular, verificar, cerrar, reabrir u otra acción sensible.

La matriz definitiva queda pendiente. La implementación debe admitir cambios sin alterar el modelo de cada dominio.

### 5.5 Profesional de salud

Perfil de una persona habilitada para prestar atención. Puede o no ser trabajador directo, según futura definición contractual, pero sus actuaciones clínicas siempre deben tener identidad verificable.

Desactivar su relación laboral, habilitación o usuario no elimina ni reasigna su autoría histórica.

Relaciones propuestas:

- muchos a muchos con especialidades;
- habilitaciones para uno o más servicios;
- agenda y restricciones por sede;
- autoría de atenciones y registros clínicos.

### 5.6 Registro profesional

Representa identificadores o habilitaciones como CMP/RNE y su vigencia. Evita reducir toda acreditación profesional a dos campos fijos.

## 6. Pacientes y responsables

### 6.1 Paciente

Perfil asistencial asociado a una persona. Mantiene un identificador clínico interno estable y atributos administrativos propios.

**Confirmado:** la historia clínica es longitudinal y única para el paciente, incluso entre sedes.

### 6.2 Responsable del paciente

Relación entre un paciente y una persona responsable. Debe conservar tipo de vínculo, vigencia, prioridad y datos de contacto válidos para esa relación.

No se debe sobrescribir silenciosamente un vínculo histórico cuando cambia el responsable.

### 6.3 Historia clínica longitudinal

Es el conjunto lógico de todas las atenciones y documentos clínicos de un paciente. Puede existir un identificador/expediente que actúe como ancla, pero no debe duplicar el contenido de cada atención.

## 7. Catálogo, especialidades y tarifas

### 7.1 Especialidad

Clasificación asistencial de profesionales y servicios. No debe limitar al profesional a una sola especialidad.

### 7.2 Servicio

Prestación intangible ofrecida al paciente. Puede requerir especialidad, profesional, duración, agenda y reglas de reevaluación.

### 7.3 Producto

Bien físico vendible y potencialmente inventariable. Conserva unidad, clasificación tributaria y políticas de stock.

### 7.4 Entrada vendible de catálogo

**Propuesta técnica:** ofrecer una vista o referencia común para aquello que puede aparecer en una venta, sin borrar la diferencia entre Servicio y Producto. Así, una línea comercial referencia un tipo conocido y conserva integridad.

### 7.5 Oferta profesional de servicio

Relación que habilita a un profesional para brindar un servicio, potencialmente por sede. Puede definir duración, vigencia y condiciones operativas.

### 7.6 Tarifa y regla de precio

Precio vigente para un servicio o producto bajo condiciones definidas. La tarifa orienta el cálculo, pero la línea de venta conserva el precio efectivamente aplicado y la justificación de descuentos o excepciones.

## 8. Agenda y reserva

### 8.1 Plantilla de horario

Disponibilidad recurrente propuesta para un profesional, servicio, sede y posiblemente consultorio.

### 8.2 Excepción o bloqueo de agenda

Modifica una plantilla para una fecha o periodo concreto: ausencia, feriado, bloqueo, ampliación o cambio autorizado.

### 8.3 Disponibilidad

Resultado calculado a partir de horarios, excepciones, duración, recursos y reservas vigentes. No equivale necesariamente a una fila persistida.

### 8.4 Pre-reserva

Retención temporal de un horario solicitado por un canal. Se asocia a paciente o prospecto identificado, profesional/servicio/recurso, vencimiento y estado.

Estados conceptuales:

```text
ACTIVA -> CONVERTIDA
ACTIVA -> EXPIRADA
ACTIVA -> LIBERADA
ACTIVA -> DESPLAZADA_POR_PRIORIDAD_DE_PAGO
```

**Confirmado:** el pago requerido tiene prioridad para obtener la cita.

**Confirmado:** la duración por defecto es 15 minutos y debe ser configurable. La expiración libera el recurso. Toda extensión conserva actor, motivo, fecha/hora y nuevo vencimiento. Continúan pendientes el límite de extensiones y el desempate exacto ante simultaneidad.

### 8.5 Cita

Compromiso confirmado de atención. Relaciona paciente, sede, servicio, profesional, horario y origen/canal.

No debe ser la fuente única de verdad de pagos, caja, historia clínica o documentos fiscales.

Estados conceptuales propuestos:

```text
CONFIRMADA -> REPROGRAMADA
CONFIRMADA -> CANCELADA
CONFIRMADA -> REGISTRÓ_LLEGADA -> EN_ESPERA -> LLAMADA -> EN_CONSULTA -> FINALIZADA
```

Los estados operativos de llegada/atención pueden residir en una entidad de atención o en eventos de flujo; la decisión física se posterga.

### 8.6 Reevaluación o reconsulta

Solicitud de atención derivada de una atención previa. Conserva el vínculo con la atención/cita origen y la razón clínica. Puede no generar nuevo cobro.

La ventana gratuita exacta queda pendiente y no se modela como un horario fijo.

La elegibilidad se evalúa mediante una política asociada a la combinación profesional + servicio y a la atención original. Si cumple las condiciones, no genera un nuevo cobro; una excepción queda trazable.

## 9. Atención e historia clínica electrónica

### 9.1 Atención clínica

Episodio real de contacto asistencial. Registra paciente, cita de origen si existe, sede, consultorio, profesional, servicio, inicio, fin y estado.

Puede existir una atención sin una cita previa solo si el negocio define el flujo y la autorización correspondientes.

### 9.2 Registro clínico

Documento clínico principal de una atención. Combina información estructurada y narrativa.

Estados conceptuales:

```text
BORRADOR -> CERRADO
CERRADO -> ADENDADO
CERRADO -> REABIERTO_EXCEPCIONALMENTE -> CERRADO
```

Invariantes confirmadas:

- el cierre conserva autor, fecha y hora;
- el contenido cerrado no se edita silenciosamente;
- la corrección ordinaria se realiza mediante adenda;
- la reapertura es excepcional, autorizada y auditada;
- no se exige firma criptográfica en el alcance inicial.

### 9.3 Adenda clínica

Contenido adicional vinculado a un registro cerrado. Conserva autor, motivo, fecha/hora y nunca sustituye el texto original.

### 9.4 Datos clínicos estructurados

Entidades candidatas, cuyo contenido exacto se definirá por especialidad:

- antecedentes;
- alergias;
- signos y mediciones;
- diagnósticos;
- indicaciones;
- órdenes clínicas;
- recetas y sus líneas;
- resultados;
- documentos adjuntos.

**Confirmado:** el enfoque será híbrido: campos estructurados donde aporten búsqueda/seguridad y narrativa para el juicio clínico.

### 9.5 Clasificación inicial del contenido clínico

| Concepto | Representación recomendada | Motivo |
|---|---|---|
| Paciente, profesional, sede, consultorio, fecha/hora, especialidad y servicio | Relaciones/identificadores estructurados en Atención | Contexto obligatorio, consultable y auditable |
| Historia clínica | Agregado longitudinal lógico del paciente | Une episodios sin convertirse en una tabla gigante |
| Atención | Entidad independiente | Episodio con ciclo de vida y contexto propios |
| Registro clínico | Entidad independiente vinculada a Atención | Controla borrador, cierre, autoría y narrativa principal |
| Adenda | Entidad independiente inmutable | Corrige o complementa sin sobrescribir el registro cerrado |
| Diagnóstico | Relación estructurada con código/sistema y texto clínico | Permite búsqueda conservando expresión profesional |
| Antecedente | Dato estructurado con detalle narrativo | Reutilizable longitudinalmente y contextualizable |
| Alergia | Entidad/relación estructurada longitudinal | Es un dato de seguridad que requiere estado y trazabilidad |
| Receta | Entidad independiente con líneas snapshot | Conserva indicación, producto/medicamento y dosis históricos |
| Orden | Entidad independiente | Tiene tipo, estado, solicitante y resultado relacionado |
| Resultado | Entidad independiente o documento asociado a una orden | Conserva origen, fecha, estado y archivo/datos recibidos |
| Documento clínico | Entidad independiente de metadatos + archivo protegido | Autoriza acceso sin exponer directamente el archivo |
| Motivo, anamnesis, examen/evolución, impresión, plan e indicaciones libres | Contenido narrativo versionado en el Registro clínico | Preserva libertad clínica sin crear catálogos artificiales |

No se propone una tabla por cada campo clínico. Un componente adquiere entidad propia cuando tiene ciclo de vida, cardinalidad, seguridad o necesidad de consulta independientes.

### 9.6 Evento de acceso clínico

Audita consulta, impresión, descarga, cierre, adenda y reapertura cuando sea sensible. Conserva actor, motivo/contexto, objeto y fecha/hora.

## 10. Comercial, obligaciones y pagos

### 10.1 Venta

Operación comercial que agrupa bienes o servicios ofrecidos en una fecha, sede y contexto. Conserva estado, cliente/paciente relacionado y totales calculados desde sus líneas.

### 10.2 Línea de venta

Instantánea de lo vendido: producto o servicio, descripción, cantidad, unidad, precio aplicado, descuento, impuestos y total. Puede relacionarse con profesional, cita o atención cuando corresponda.

El cambio posterior del catálogo no modifica la línea histórica.

### 10.3 Cargo u obligación

Importe que una persona debe por una venta, cita o prestación. Permite distinguir lo vendido de lo efectivamente cobrado y soportar adelantos, saldos y aplicaciones parciales.

### 10.4 Pago

Recepción o salida de valor por un medio determinado. Es la fuente de verdad del importe, fecha, moneda, medio, operación y estado del pago.

Estados conceptuales:

```text
REGISTRADO -> PENDIENTE_DE_VERIFICACIÓN -> VERIFICADO -> APLICADO
REGISTRADO/VERIFICADO -> RECHAZADO
APLICADO -> REVERSADO o DEVUELTO mediante operación compensatoria
```

No todos los medios requieren la misma verificación, pero toda transición sensible conserva actor y motivo.

### 10.5 Evidencia y verificación de pago

La evidencia puede ser una captura u otro archivo protegido. La verificación conserva número de operación visible/normalizado, entidad o medio, verificador, fecha/hora y resultado.

La evidencia no debe ser pública ni almacenarse en logs.

Verificar evidencia de pago y autorizar una excepción de adelanto son decisiones independientes, con capacidades y actores potencialmente distintos.

### 10.6 Aplicación de pago

Relación que distribuye un pago entre una o más obligaciones. Permite pagos parciales y evita duplicar campos de `total_pagado` o `saldo` en cada módulo.

Invariante: la suma aplicada no supera el importe disponible salvo una regla explícita de crédito o sobrepago.

### 10.7 Excepción de adelanto

Autorización para confirmar una cita sin alcanzar el 50 % requerido. Conserva autorizador, motivo, fecha/hora y objeto beneficiado.

**Confirmado:** la regla general es adelanto del 50 %. La lista de autorizadores queda pendiente.

### 10.8 Reverso y devolución

Operaciones nuevas que compensan el efecto previo sin eliminarlo. Deben indicar causa, aprobación, relaciones con caja/documento fiscal y estado resultante.

## 11. Caja

### 11.1 Turno de caja

Periodo de responsabilidad de un usuario sobre una caja. Conserva apertura, monto inicial, cierre, conteo, diferencia y observaciones.

Invariantes propuestas:

- un turno no puede recibir movimientos después del cierre sin reapertura controlada;
- el saldo esperado se deriva del monto inicial y movimientos válidos;
- el conteo físico y la diferencia se conservan como evidencia, no se corrigen sobrescribiéndolos.

### 11.2 Movimiento de caja

Entrada o salida de valor bajo custodia. Puede originarse en un pago en efectivo, devolución, retiro, ingreso extraordinario o ajuste autorizado.

Un pago no es sinónimo de movimiento de caja: los pagos no efectivos pueden no tocar caja física, y ciertos movimientos de caja no son ventas.

En efectivo se distinguen el monto entregado por el paciente, el importe aplicado al cobro y el vuelto entregado. Solo el importe neto retenido incrementa la custodia de caja; el vuelto no se registra como un segundo ingreso.

### 11.3 Arqueo y diferencia

Registro de conteo por denominación o total, según alcance futuro, con comparación contra el saldo esperado y explicación/aprobación de diferencias.

## 12. Inventario

### 12.1 Movimiento de inventario

Hecho que incrementa, disminuye o traslada existencias. Conserva almacén origen/destino, producto, cantidad, causa, documento de origen, actor y fecha/hora.

Tipos conceptuales: ingreso inicial, compra futura, venta, devolución, traslado, ajuste, merma y vencimiento futuro.

### 12.2 Existencia

Proyección del saldo por producto y almacén obtenida de movimientos confirmados. Puede materializarse por rendimiento, pero no se modifica sin generar el movimiento correspondiente.

### 12.3 Reserva de stock

Entidad candidata para apartar producto antes de completar una venta. Su necesidad y política se validarán con el flujo real.

### 12.4 Lote y vencimiento

Quedan preparados conceptualmente como dimensiones futuras del movimiento y la existencia, pero no son obligatorios hasta confirmar el alcance de farmacia.

## 13. Facturación y documentos

### 13.1 Documento interno

Comprobante operativo no necesariamente tributario, cuando el negocio requiera constancia interna. Debe distinguirse claramente del documento aceptado por SUNAT.

### 13.2 Documento tributario

Representa boleta, factura y documentos de corrección. Conserva emisor, receptor, punto de emisión, serie, correlativo, fecha, moneda, totales, líneas históricas, estado fiscal y relación comercial.

### 13.3 Serie y correlativo fiscal

La serie pertenece a un punto de emisión y tipo de documento. El correlativo se asigna de forma atómica y única.

No debe depender de contar filas ni de un valor actual sin bloqueo.

### 13.4 Envío fiscal e intento

Registra cada generación, firma, envío, consulta o reintento, con artefactos, respuesta sanitizada, tiempos y estado. Los reintentos no crean documentos duplicados.

El ciclo conceptual mínimo es:

```text
PENDIENTE -> GENERADO -> FIRMADO -> ENVIADO -> ACEPTADO / RECHAZADO
RECHAZADO -> CORREGIDO, cuando corresponda
ACEPTADO -> ANULADO, únicamente por el mecanismo fiscal aplicable
```

### 13.5 Documento de corrección

Nota de crédito, nota de débito u otro mecanismo admitido. Referencia al documento original, causal, sustento y autorización.

**Confirmado:** el sistema debe prepararse para factura, boleta y correcciones. La validación contable/fiscal definitiva queda pendiente.

## 14. Integraciones y auditoría

### 14.1 Operación de integración

Registro técnico-operativo de una solicitud externa relevante: proveedor, tipo, entidad local, clave idempotente, estado, intentos y tiempos. No almacena secretos ni respuestas personales completas innecesarias.

### 14.2 Evento del llamador

Transición versionada del flujo llegada → espera → llamado → consulta → finalización. Debe poder reconciliarse con el estado interno de la atención.

### 14.3 Evento de auditoría

Registro append-only de acción sensible. Incluye actor, capacidad ejercida, entidad, identificador, resultado, motivo, contexto de sede, fecha/hora y cambios relevantes sanitizados.

No reemplaza el historial propio de estados de cada agregado.

Un evento de acceso de emergencia (*break glass*) registra además el motivo obligatorio, el alcance extraordinario, la alerta generada y su revisión posterior.

### 14.4 Evento de salida

Registro durable para entregar de forma asíncrona una notificación o integración después de confirmar la operación local. Conserva estado e intentos sin duplicar el hecho de negocio.

## 15. Invariantes transversales prioritarias

1. Una cita confirmada no comparte un recurso incompatible en el mismo intervalo.
2. La pre-reserva vencida no bloquea disponibilidad.
3. Confirmar sin 50 % requiere una excepción válida y trazable.
4. Un pago solo se aplica una vez por el importe disponible.
5. Ninguna reversión elimina el movimiento original.
6. El cierre de caja conserva conteo y diferencia originales.
7. Un correlativo fiscal es único en su ámbito y no se reutiliza.
8. Una salida de inventario tiene un movimiento causal y una política explícita frente a stock insuficiente.
9. Un registro clínico cerrado solo cambia mediante adenda o reapertura excepcional.
10. Toda atención identifica paciente, sede, profesional, servicio y tiempo; el consultorio se registra cuando aplique.
11. Desactivar maestros no rompe documentos históricos.
12. Toda acción sensible registra autoría y motivo cuando la política lo exige.

## 16. Datos históricos y privacidad

Se conservarán:

- identificadores legados y referencias cruzadas durante la transición;
- instantáneas comerciales y fiscales;
- estados y transiciones relevantes;
- autoría original;
- evidencias necesarias según política de retención.

Se deben definir antes del diseño físico:

- reglas de unificación de personas/pacientes duplicados;
- formato y custodia de documentos de identidad;
- clasificación de datos sensibles;
- retención de evidencias de pago y archivos clínicos;
- anonimización para ambientes no productivos;
- derechos de corrección sin destrucción del historial legal.

## 17. Pendientes que bloquean el diseño físico

- inventario real de esquema y calidad de datos de producción;
- reglas de deduplicación de personas y pacientes;
- límite de extensiones de pre-reserva y desempate detallado ante simultaneidad;
- autorizadores de excepciones y anulaciones;
- flujo clínico mínimo por especialidad;
- alcance de farmacia, lotes, vencimientos, compras y proveedores;
- reglas fiscales y contables revisadas por especialista;
- medios de pago y criterios de verificación admitidos;
- política de stock negativo, reservas y ajustes;
- retención y acceso legal a historia clínica, auditoría y evidencias.
