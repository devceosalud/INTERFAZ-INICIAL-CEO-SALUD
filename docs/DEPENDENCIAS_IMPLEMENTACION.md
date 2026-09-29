# Dependencias y orden de implementación

## 1. Propósito

Ordenar la evolución técnica por dependencias, riesgo y capacidad de validación. Este documento no constituye un cronograma ni autoriza implementación.

## 2. Criterio de prioridad

El orden combina:

1. **Riesgo:** proteger producción, datos clínicos, dinero, correlativos y stock.
2. **Dependencia:** construir primero las fuentes de verdad que otros módulos necesitan.
3. **Beneficio:** habilitar flujos completos y reducir duplicidad de lógica.
4. **Reversibilidad:** preferir cambios aditivos y activación gradual.

## 3. Grafo resumido

```text
[0. Descubrimiento, respaldo y entornos]
                    |
        +-----------+-----------+
        |                       |
[1. Organización]       [2. Identidad/Seguridad/Auditoría]
        |                       |
        +-----------+-----------+
                    |
      [3. Pacientes, Personal y Catálogo]
          |             |              |
          |             |              `--------+
          |             v                       |
          |       [4. Agenda/Pre-reserva]       |
          |             |                       |
          |             v                       |
          +------> [5. Atención/HCE]             |
                        |                        |
                        +---------+--------------+
                                  v
                     [6. Comercial/Obligaciones/Pagos]
                         |              |
                         v              v
                      [7. Caja]    [8. Inventario]
                         |              |
                         +-------+------+
                                 v
                        [9. Facturación/SUNAT]
                                 |
                                 v
                       [10. Reportes y retiro legado]

Integraciones y observabilidad atraviesan las etapas, pero se activan solo cuando
el dominio propietario ya tiene una fuente de verdad estable.
```

## 4. Etapas y puertas de entrada/salida

### Etapa 0 — Seguridad de evolución

Incluye:

- inventario productivo de solo lectura;
- respaldo y restauración probados;
- datos representativos anonimizados;
- MySQL aislado;
- pipeline de tests y configuración segura;
- análisis de DDL y calidad de datos;
- definición de despliegue/retorno.

Depende de: nada.

Bloquea: toda migration o backfill TO-BE.

Puerta de salida: esquema real conocido, restauración demostrada y cero uso de credenciales productivas en entornos de prueba.

### Etapa 1 — Organización multi-sede

Incluye el concepto de sede y las relaciones futuras de consultorios, cajas, puntos de emisión y almacenes.

Depende de: Etapa 0.

Bloquea:

- agenda por sede;
- atención con contexto físico;
- caja, inventario y facturación multi-sede.

Puerta de salida: recursos actuales asignables a una sede inicial sin alterar su historia.

### Etapa 2 — Identidad, seguridad y auditoría

Incluye Persona, Trabajador, Usuario, Profesional, capacidades y marco de auditoría.

Depende de: Etapa 0; usa Sede para contexto, pero puede avanzar en paralelo con Etapa 1.

Bloquea:

- autoría clínica confiable;
- aprobaciones y anulaciones sensibles;
- deduplicación de pacientes/profesionales;
- trazabilidad transversal.

Puerta de salida: todo usuario activo tiene identidad operativa trazable; ninguna autoría histórica se pierde; permisos actuales siguen cubiertos por tests.

### Etapa 3 — Pacientes, personal y catálogo

Subfrentes:

#### 3A. Pacientes y responsables

Depende de: identidad.

Entrega: vínculo Persona–Paciente, identificador clínico estable y relaciones de responsabilidad con vigencia.

#### 3B. Profesionales y especialidades

Depende de: identidad y organización.

Entrega: profesional separado de usuario/trabajador, registros profesionales y especialidades múltiples.

#### 3C. Servicios, productos y tarifas

Depende de: organización para alcance futuro; puede iniciar con conciliación de maestros.

Entrega: separación Servicio/Producto, oferta profesional y precios versionables.

Puerta común: correspondencias completas de ids legados y ninguna referencia activa sin destino.

### Etapa 4 — Agenda y pre-reserva

Incluye horarios recurrentes, excepciones, recursos, disponibilidad, pre-reserva, cita y reconsulta vinculada.

La pre-reserva parte de 15 minutos configurables, expira liberando el recurso y solo se extiende con auditoría. La reevaluación aplica una política por profesional + servicio y, cuando cumple sus condiciones, no genera un nuevo cobro.

Depende de:

- sede/consultorio;
- paciente;
- profesional/especialidad/servicio;
- reglas comerciales mínimas para adelanto.

Bloquea: atención, llamador y HCE.

Puerta de salida:

- configuración de expiración, límites de extensión y prioridad acordados;
- competencia de reserva probada en MySQL;
- comparación de disponibilidad nueva y legada;
- reprogramación/cancelación y liberación de recursos verificadas.

### Etapa 5 — Atención e historia clínica electrónica

Incluye llegada, espera, llamado, consulta, finalización, episodio clínico, registro híbrido, cierre, adenda y reapertura excepcional.

Depende de:

- agenda/cita;
- identidad del paciente y profesional;
- sede/consultorio;
- permisos y auditoría;
- almacenamiento protegido.

El conector con el llamador puede diseñarse en paralelo, pero no debe convertirse en fuente de verdad.

Puerta de salida:

- conjunto clínico mínimo aprobado;
- estados e invariantes validados por usuarios clínicos;
- pruebas de acceso, cierre y corrección;
- retención/custodia revisadas.

### Etapa 6 — Comercial, obligaciones y pagos

Incluye venta, líneas, cargo/obligación, adelantos, evidencias, verificación, aplicaciones y reversos.

Depende de:

- pacientes/clientes;
- catálogo/tarifas;
- identidad y aprobaciones;
- agenda para el 50 % y excepciones de cita.

Bloquea: conciliación definitiva de caja, inventario por venta y facturación separada.

Puerta de salida:

- reconciliación exacta contra cita/voucher/payment;
- idempotencia probada;
- ningún pago o adelanto duplicado;
- reglas de reverso y excepción autorizadas.

### Etapa 7 — Caja

Incluye caja por sede, turno, movimientos tipificados, arqueo y diferencias.

Depende de: organización, identidad, pagos.

Puede modernizarse parcialmente antes del nuevo Comercial, pero el corte de fuente de verdad debe esperar la relación Pago–Movimiento.

Puerta de salida: saldos esperados y cierres históricos reconciliados; conflicto de turnos y escrituras post-cierre impedidos.

### Etapa 8 — Inventario

Incluye almacenes, movimientos, saldos derivados e integración con productos vendidos.

Depende de: organización, productos, venta.

Puede iniciar maestros y conteo físico en paralelo; la descarga automática espera el flujo comercial estable.

Puerta de salida:

- alcance empresarial mínimo confirmado;
- saldo inicial aprobado;
- política de stock insuficiente/ajustes definida;
- reversos de venta y devoluciones conciliados.

### Etapa 9 — Facturación y SUNAT

Incluye puntos de emisión, series, correlativos, documentos tributarios, correcciones, artefactos e intentos externos.

Depende de:

- organización;
- venta y líneas estables;
- pagos cuando la condición fiscal los requiera;
- identidad y autorizaciones;
- revisión contable/fiscal;
- infraestructura de cola, archivos y certificados.

Puerta de salida:

- reglas validadas por especialista;
- competencia de correlativos probada;
- idempotencia y contingencia verificadas;
- ambiente de homologación/autorizado exitoso;
- ningún secreto en código/logs.

### Etapa 10 — Reportes, proyecciones y retiro legado

Depende de fuentes de verdad estables. Los reportes críticos se reconstruyen y comparan con cifras legadas antes de retirar columnas o tablas.

Puerta de salida: periodos operativos estables, diferencias explicadas, retención aprobada y eliminación física autorizada por separado.

## 5. Líneas transversales

### 5.1 Integraciones

Orden sugerido:

1. contratos internos y adaptadores fake;
2. sanitización, timeouts e idempotencia;
3. registro de intentos y observabilidad;
4. colas/reintentos;
5. activación por entorno;
6. prueba controlada con proveedor real.

RENIEC/RUC pueden integrarse tras estabilizar identidad. SMS/correo, tras disponer de eventos y consentimiento. Llamador, tras estabilizar Atención. SUNAT, al final del flujo comercial/fiscal.

### 5.2 Auditoría y privacidad

No es una fase final. Debe preceder a:

- cambios de permisos;
- verificaciones/aprobaciones financieras;
- cierres y reaperturas clínicas;
- anulaciones fiscales;
- ajustes de inventario.

### 5.3 Pruebas

Cada etapa agrega:

- tests unitarios de invariantes;
- tests de aplicación por caso de uso y rol;
- integración con MySQL para transacciones/concurrencia;
- contrato con adaptadores externos simulados;
- reconciliación de datos;
- pruebas de regresión del comportamiento legado que deba conservarse.

### 5.4 Observabilidad

Antes de activar una fuente nueva deben existir métricas de divergencia, errores, latencia y cola. No se realiza un corte que no pueda medirse.

## 6. Trabajo paralelizable

Puede avanzarse en paralelo, sin saltar puertas:

- organización e identidad tras Etapa 0;
- pacientes, profesionales y conciliación del catálogo después de la base de identidad;
- diseño de almacenamiento clínico y contrato del llamador mientras se estabiliza agenda;
- conteo físico y diseño de movimientos mientras se estabiliza comercial;
- revisión fiscal/contable y diseño del adaptador SUNAT antes de su implementación;
- reportes de reconciliación desde las primeras oleadas.

No debe paralelizarse como escrituras independientes:

- dos fuentes asignando citas al mismo recurso;
- dos mecanismos asignando correlativos de una serie;
- dos fuentes descontando stock;
- dos lógicas aplicando el mismo pago;
- edición simultánea de un registro clínico cerrado.

## 7. Camino crítico

```text
Esquema real y recuperación
  -> Identidad/organización
  -> Pacientes/profesionales/catálogo
  -> Agenda y pre-reserva
  -> Atención e HCE

Esquema real y recuperación
  -> Catálogo
  -> Comercial/obligaciones/pagos
  -> Caja + inventario
  -> Facturación/SUNAT
```

La rama clínica y la económica comparten identidad, organización, paciente, catálogo, autorización y auditoría. Por eso esas bases deben resolverse antes de escalar el desarrollo funcional.

## 8. Riesgos que pueden reordenar el plan

- divergencia importante entre esquema productivo y migrations;
- datos financieros no conciliables automáticamente;
- pacientes o profesionales duplicados sin criterio confiable;
- limitaciones de hosting para colas/scheduler/despliegue gradual;
- requisitos legales de historia clínica no contemplados;
- revisión fiscal que obligue a cambiar el modelo documental;
- decisión empresarial de farmacia completa con lotes/vencimientos/compras;
- contrato del llamador incompatible con los estados propuestos.

Ante uno de estos riesgos, se pausa únicamente la rama afectada; no se improvisa un modelo irreversible.

## 9. Próxima fase técnica recomendada

Antes de programar módulos TO-BE, preparar un paquete de diseño detallado para la primera rebanada vertical, incluyendo:

- alcance y criterios de aceptación;
- estados e invariantes;
- autorización;
- contrato de datos y compatibilidad legada;
- estrategia de migration/backfill;
- pruebas y reconciliación;
- despliegue, observabilidad y retorno.

La primera rebanada recomendada es **Organización + identidad vinculada**, porque habilita multi-sede, autorización auditable y referencias estables sin cambiar todavía los flujos clínicos o financieros.

## 10. Decisiones pendientes que bloquean etapas

| Decisión | Etapa bloqueada |
|---|---|
| Límite de extensiones y desempate de pre-reserva | 4 |
| Autorizadores de excepción al 50 % | 4 y 6 |
| Ventana gratuita de reconsulta | 4 y 6 |
| Contenido clínico mínimo y reglas por especialidad | 5 |
| Autorizadores de reapertura clínica | 5 |
| Medios/evidencias y verificación de pagos | 6 |
| Política de stock insuficiente y alcance de farmacia | 8 |
| Reglas contables y fiscales validadas | 9 |
| Retención y privacidad | 2, 5, 6, 9 y 10 |
| Capacidades de infraestructura productiva | 4, 5, 9 y líneas transversales |
