# Impacto de migración hacia el TO-BE

## 1. Alcance

Esta evaluación cruza:

- arquitectura TO-BE de Fase 4;
- migrations y código local del ERP;
- auditoría local del llamador;
- ausencia de DDL y perfil de datos productivos.

La evaluación es provisional. Ningún área está autorizada para implementación hasta completar la evidencia productiva aplicable.

## 2. Clasificación por dominio

| Dominio | Riesgo | Migración de datos | Compatibilidad temporal | Decisión de negocio | Motivo principal |
|---|---|---:|---:|---:|---|
| Organización/sedes | MEDIO | Sí | Sí | Parcial | Recursos actuales no tienen sede; asignación inicial debe validarse |
| Identidad y seguridad | ALTO | Sí | Sí | Sí | Persona/trabajador/usuario/profesional están mezclados o ausentes |
| Pacientes/responsables | ALTO | Sí | Sí | Sí | Duplicados potenciales, dos historias y datos personales repetidos |
| Personal/profesionales | ALTO | Sí | Sí | Parcial | Doctor no está vinculado a persona/usuario y tiene una especialidad |
| Agenda/citas | ALTO | Sí | Sí, obligatoria | Sí | `appointments` concentra agenda, finanzas y contrato del llamador |
| Atención/HCE | ALTO | Parcial/prospectiva | Sí | Sí | Dominio nuevo con información sensible; no debe inferirse historia |
| Catálogo/comercial | ALTO | Sí | Sí | Parcial | Duplicación `services`/`items`, precios y snapshots históricos |
| Pagos/obligaciones | ALTO | Sí | Sí | Sí | Pago depende de voucher y cita mantiene finanzas paralelas |
| Caja | ALTO | Sí | Sí | Parcial | Conciliación de turnos, movimientos, pagos y diferencias |
| Inventario | ALTO | Sí | Sí | Sí | Solo existe saldo mutable global; falta saldo inicial sustentado |
| Facturación/SUNAT | ALTO | Sí | Sí | Sí + validación fiscal | Voucher mezcla venta/documento/estado SUNAT y correlativos |
| Integraciones | ALTO | No necesariamente | Sí | Parcial | Dependencias directas, secretos y recuperación pendientes |
| Auditoría | MEDIO | Principalmente prospectiva | No extensa | Sí, retención | Puede añadirse, pero no reconstruye eventos pasados inexistentes |
| Reportes | MEDIO | Proyecciones | Sí | Sí | Deben reconciliarse con las nuevas fuentes de verdad |

## 3. Entidades críticas solicitadas

### 3.1 `appointments` — riesgo alto y bloqueante

Impactos:

- contiene reserva, cita, timestamps de llamador, importes, adelanto, exoneración y estados;
- la migration productiva declarada y la estabilizada difieren en `hora_llamado`;
- el enum versionado no contiene todos los estados usados;
- el llamador legacy lee y escribe directamente esta tabla;
- el llamador temporal usa otra tabla `appointments` no sincronizada.

Estrategia viable solo después de obtener DDL/estados/datos y confirmar el flujo del llamador:

1. añadir estructuras nuevas sin retirar columnas;
2. backfill de conceptos demostrables;
3. mantener una proyección legacy de lectura;
4. cortar la escritura directa del llamador mediante un contrato posterior;
5. cambiar escritores/lectores de forma controlada;
6. retirar columnas únicamente tras reconciliación.

No es viable una doble escritura segura mientras dos aplicaciones puedan modificar directamente la tabla sin un único coordinador.

### 3.2 `payments` — riesgo alto

Impactos:

- referencia obligatoriamente un voucher y un turno;
- no representa aplicación a varias obligaciones;
- no separa evidencia, verificación, autorización, reverso o devolución;
- `numero_operacion` podría duplicarse o no existir según medio;
- no existe vínculo directo y canónico con cita/adelanto.

La migración requiere conciliar cada pago con voucher, caja y campos financieros de cita. Los casos ambiguos deben permanecer como excepciones, no asignarse automáticamente.

### 3.3 `vouchers`/`voucher_items`/`voucher_series` — riesgo alto

Impactos:

- voucher funciona como venta, documento interno y potencial documento tributario;
- contiene estados comerciales y SUNAT;
- las líneas son simultáneamente snapshot comercial/fiscal;
- las series están ligadas a caja, no a punto de emisión;
- la secuencia productiva real y artefactos XML/CDR no se verificaron.

La separación TO-BE es viable de forma aditiva, preservando ids, snapshots y campos SUNAT. El corte fiscal requiere revisión contable y pruebas de correlativo concurrente.

### 3.4 `patients`/`responsibles` — riesgo alto

Impactos:

- dos campos de número de historia;
- identificación única según migration, pendiente en producción;
- persona responsable duplicada como texto;
- `user_id` nullable con semántica no cerrada.

La identidad canónica debe agregarse mediante correspondencias. No se permite fusionar personas por nombre ni modificar historia original.

### 3.5 `doctors`/`doctor_services` — riesgo alto

Impactos:

- doctor mezcla persona y profesional;
- una especialidad directa;
- `doctor_services` no declara FK ni unique compuesto en migration;
- precios y política de reconsulta están embebidos.

El backfill es técnicamente viable si no hay huérfanos/duplicados ambiguos. Esa condición está pendiente del perfil productivo.

### 3.6 `users`, roles y permisos — riesgo alto

Impactos:

- usuario no está vinculado formalmente a trabajador/persona;
- autoría histórica depende de ids actuales y cascades;
- roles productivos y asignaciones no se verificaron;
- el despliegue futuro de Fase 1 depende de compatibilidad con roles reales.

Se recomienda agregar vínculos sin cambiar ids ni eliminar cuentas. La desactivación debe conservar autoría. Los nombres de roles se compararán antes de cualquier despliegue.

### 3.7 `items` — riesgo alto

Impactos:

- mezcla servicio y producto;
- duplica el concepto `services`;
- stock es un integer global nullable y mutable;
- no existe almacén, kardex, lote o movimiento.

El catálogo puede mapearse, pero el inventario requiere conteo físico y movimiento de apertura aprobado. No puede reconstruirse un kardex histórico desde `stock_actual`.

## 4. Viabilidad de la estrategia Fase 4

| Área | Expansión aditiva | Backfill | Corte sin doble escritura prolongada | Evaluación |
|---|---:|---:|---:|---|
| Sedes/recursos | Sí | Sí, sede inicial | Sí | VIABLE, previa validación de asignación |
| Persona/trabajador/usuario | Sí | Condicional | Sí, por enlaces | VIABLE CON REVISIÓN DE DUPLICADOS |
| Pacientes/responsables | Sí | Condicional | Sí | VIABLE CON EXCEPCIONES MANUALES |
| Profesionales/especialidades | Sí | Condicional | Sí | VIABLE si se resuelven huérfanos/duplicados |
| Agenda/pre-reserva | Sí | Citas futuras | No mientras llamador escriba directo | BLOQUEADA POR CONTRATO AS-IS |
| Atención/HCE | Sí | Solo hechos demostrables | Sí | VIABLE COMO MÓDULO NUEVO |
| Comercial/obligaciones | Sí | Condicional | Probable | BLOQUEADA POR CONCILIACIÓN FINANCIERA |
| Pagos | Sí | Condicional | Probable | BLOQUEADA POR APLICACIONES/EXCEPCIONES |
| Caja | Sí | Condicional | Probable | BLOQUEADA POR SALDOS Y TURNOS REALES |
| Inventario | Sí | Saldo inicial, no kardex histórico | Sí tras conteo | BLOQUEADA POR INVENTARIO FÍSICO/POLÍTICA |
| Facturación | Sí | Condicional | Sí con ventana controlada | BLOQUEADA POR DDL/CORRELATIVOS/VALIDACIÓN FISCAL |
| Auditoría | Sí | No reconstruir pasado | Sí | VIABLE PROSPECTIVAMENTE |

La secuencia general expansión → backfill → compatibilidad → reconciliación → corte → deprecación sigue siendo válida, pero no puede declararse ejecutable hasta completar los bloqueos indicados.

## 5. Criterio de doble escritura

La doble escritura **no se recomienda por defecto**.

### No requerida inicialmente

- asociación de recursos a sede;
- vínculos Persona/Trabajador/Usuario;
- auditoría prospectiva;
- estructuras clínicas nuevas;
- backfills de solo lectura;
- correspondencias de catálogo.

### Posible solo durante una transición corta

- venta/obligación mientras pantallas legadas aún consulten vouchers;
- pagos/aplicaciones si ambos modelos operan durante un corte escalonado;
- movimiento de inventario y actualización del saldo legado como proyección;
- estado de cita y proyección del llamador tras eliminar escritura directa externa.

Condiciones obligatorias:

- un único caso de uso centraliza ambas escrituras;
- misma transacción cuando comparten base;
- clave idempotente;
- comparación/reconciliación automática;
- métrica de divergencia;
- fecha y condición de retiro;
- retorno definido sin borrar datos.

El llamador actual incumple la condición de centralización porque escribe directamente en el ERP. No debe añadirse otra escritura paralela hasta resolver ese límite.

## 6. Compatibilidad temporal recomendada

### Agenda/llamador

- conservar columnas/estados legacy durante la transición;
- impedir nuevos consumidores directos;
- obtener primero un inventario de rutas desplegadas;
- posteriormente sustituir escritura directa por contrato controlado;
- mantener proyección de visor mientras se valida el nuevo flujo.

### Finanzas

- conservar campos de cita y voucher como evidencia legacy;
- poblar obligaciones/aplicaciones nuevas desde datos conciliados;
- cambiar lecturas antes de dejar de mantener campos anteriores;
- no recalcular ni sobrescribir documentos emitidos.

### Inventario

- mantener `stock_actual` solo como referencia/proyección temporal;
- crear saldo inicial mediante movimiento auditado;
- comparar saldo derivado con legado;
- cortar escrituras directas al saldo.

### Facturación

- preservar estados/paths/hash/respuesta SUNAT;
- incorporar intentos y documento fiscal en forma aditiva;
- no reasignar series ni correlativos;
- deprecar campos solo tras revisión tributaria.

## 7. Riesgos críticos

### RIESGO CRÍTICO PRODUCTIVO POTENCIAL

El llamador contiene rutas no autenticadas que, si están desplegadas y `other_system` está configurado, pueden modificar directamente `appointments` del ERP. La exposición productiva no fue verificada.

### Otros riesgos altos

1. Enum de citas incompatible con estados usados por ERP/llamador.
2. DDL productivo desconocido pese a depender de cambios manuales posibles.
3. Datos financieros potencialmente duplicados en cita/voucher/payment/caja.
4. Correlativos sin reconciliación productiva.
5. Stock sin trazabilidad ni saldo inicial validado.
6. Roles productivos no comparados con la matriz provisional.
7. APP_DEBUG, jobs, cron, backups y restauración no verificados.
8. Despliegue puede no ser reproducible si el panel ejecuta `composer update`.
9. Exposición de PII operativa en visores/logs de navegador.

## 8. Decisiones y evidencias pendientes

### Evidencia técnica

- DDL de 32 tablas;
- perfil agregado y reconciliación financiera;
- roles/permisos agregados;
- commit y rutas realmente desplegados del llamador;
- configuración efectiva de Hostinger;
- backups disponibles y restauración probada.

### Decisiones de negocio

- autoaprobación y límites económicos del maestro configurable de aprobadores de COSTO 0;
- regla exacta de autorización/guardado de sobreagendamiento;
- límites y cierre/no atención de citas adicionales;
- límites de extensión de pre-reserva;
- ventana de reconsulta gratuita;
- reglas de devolución;
- política de stock negativo/ajustes;
- alcance fiscal validado;
- privacidad/retención y exposición aceptable del visor.

## 9. Próximo paso seguro

Ejecutar, por un operador autorizado, el paquete de consultas de solo lectura documentado en:

- `SCHEMA_PRODUCTIVO_VERIFICADO.md`;
- `PERFIL_DATOS_PRODUCTIVOS.md`.

Después incorporar únicamente resultados agregados/DDL, recalcular la matriz de drift y decidir qué primera migración de diseño detallado es viable. No implementar antes de esa reconciliación.

## 10. Fase 5A — impacto del MVP prioritario de agendamiento

El requerimiento de jefatura convierte Agenda en la primera rebanada funcional prioritaria, pero no elimina las condiciones de reconciliación productiva. El MVP debe evolucionar de forma aditiva y compatible; no se autoriza reemplazar `appointments` ni aplicar migrations en esta fase.

### 10.1 Brechas que requieren evolución de datos

El esquema versionado no representa de forma suficiente:

- sede/consultorio de la agenda;
- pre-reserva, vencimiento y extensión;
- distinción entre regular, sobreagenda autorizada y adicional;
- política informada y resultado de una adicional;
- autorizaciones identificables de COSTO 0, sobreagenda y excepción al adelanto;
- creador, responsable actual, modificadores e historial de una cita;
- historial de reasignación del responsable anterior/nuevo, actor y fecha/hora;
- maestro configurable de aprobadores de COSTO 0, con estado activo/inactivo y vigencia futura opcional;
- evidencia y verificación de pago;
- restricción de concurrencia del cupo.

Estos faltantes requieren diseño posterior de BD, pero sus migrations solo serán viables después de obtener DDL/índices/enums productivos y perfilar los datos afectados.

### 10.2 Campos legacy que deben preservarse

Durante el piloto se deben conservar o proyectar compatiblemente:

- identidad y relaciones de `appointments`;
- `user_id` como creador legado;
- profesional, paciente, servicio, fecha, hora, duración y turno;
- `estado_cita` y timestamps consumidos por el llamador;
- importes/estado de pago como evidencia histórica, aunque dejen de ser la fuente canónica futura;
- ids usados por tickets, líneas, pagos y ventas.

No debe reinterpretarse `patients.user_id` como responsable de la cita ni `additional_rate_id`/`cita_doble` como cita adicional. `appointments.user_id` debe preservarse como creador legado; no debe convertirse retroactivamente en prueba de responsabilidad histórica después de una futura reasignación.

### 10.3 Orden de migración recomendado para el MVP

1. Reconciliar DDL, estados y contrato del llamador.
2. Añadir trazabilidad de creador, responsable, modificadores y reasignaciones sin retirar campos legacy.
3. Introducir regla unificada de horarios, bloqueos y concurrencia.
4. Incorporar pre-reserva y confirmación por adelanto/excepción.
5. Incorporar el maestro configurable y las autorizaciones de COSTO 0/sobreagenda.
6. Incorporar tipo y ciclo de cita adicional, respetando horario activo, fin de bloque, carga y capacidad.
7. Proyectar compatibilidad hacia llamador y finanzas.
8. Cortar lecturas/escrituras legacy solo con métricas de equivalencia y retorno probado.

### 10.4 Nuevos riesgos de migración identificados

- usar el enum actual para nuevos estados puede fallar si producción difiere;
- agregar una unicidad simplista por médico/fecha/hora puede invalidar citas dobles, duraciones variables, sobreagenda y adicionales;
- poblar retroactivamente el responsable de la cita desde `user_id` produciría una certeza histórica no demostrada; como máximo puede proponerse un backfill provisional explícitamente marcado y validado;
- recalcular precios históricos desde `doctor_services` puede cambiar el importe acordado;
- mover estados del llamador sin proyección puede interrumpir llegada/llamado/atención;
- crear dos fuentes de pre-reserva/confirmación sin corte controlado puede liberar u ocupar cupos incorrectamente.

La especificación detallada de brechas y criterios está en:

- `MVP_AGENDAMIENTO_REQUERIMIENTOS.md`;
- `MVP_AGENDAMIENTO_GAP_ANALYSIS.md`;
- `MVP_AGENDAMIENTO_FLUJOS.md`;
- `MVP_AGENDAMIENTO_CRITERIOS_ACEPTACION.md`.

### 10.5 Decisiones confirmadas y efecto sobre el diseño posterior

- **CONFIRMADO POR NEGOCIO:** los aprobadores de COSTO 0 se administrarán en un maestro/lista configurable y no mediante un rol hardcodeado. Puede incluir profesionales de salud, Comercial, gerencia u otros usuarios; requiere activación/desactivación y admite vigencia futura opcional. Permanecen pendientes solo autoaprobación y límites económicos.
- **CONFIRMADO POR NEGOCIO:** ADMISION o COMERCIAL pueden gestionar citas adicionales según autorización; no es obligatoria una aprobación manual del profesional. La adicional debe caber dentro del horario/bloque real y considerar carga, adicionales previas, duración estimada disponible y capacidad. Permanecen pendientes máximos y cierre/no atención.
- **CONFIRMADO POR NEGOCIO:** creador, responsable de la cita y modificadores son datos diferentes. El responsable inicia como el usuario autenticado creador y puede reasignarse con historial anterior/nuevo, actor y fecha/hora.
- **CONFIRMADO POR NEGOCIO:** el piloto se configurará para un grupo todavía por confirmar en la sede actual; el modelo no puede hardcodear integrantes ni funciones exclusivas para ese grupo.
- **CONFIRMADO POR NEGOCIO:** la fluidez UX forma parte del MVP, pero la maqueta es solo una referencia interna.
- **PENDIENTE DE NEGOCIO:** la selección de un horario no disponible no define automáticamente cita adicional. Debe cerrarse la regla que distingue cita regular excepcional, sobreagendamiento autorizado y adicional.
- **PENDIENTE DE PRODUCCIÓN:** DDL/estados reales de `appointments`, compatibilidad del llamador y roles productivos básicos siguen siendo el gate técnico previo a cambios integrados.
