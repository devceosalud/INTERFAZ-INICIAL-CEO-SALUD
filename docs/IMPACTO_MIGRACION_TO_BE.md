# Impacto de migración hacia el TO-BE

## 1. Alcance

Esta evaluación cruza:

- arquitectura TO-BE de Fase 4;
- migrations y código local del ERP;
- auditoría local del llamador;
- DDL y agregados productivos ya confirmados para `appointments`, con el resto del esquema/perfil todavía pendiente.

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

- contiene reserva, cita, importes, adelanto, exoneración y estados; producción no contiene los cuatro timestamps/horas previstos para el llamador;
- la migration productiva declarada y la estabilizada difieren en `hora_llamado`;
- el enum productivo confirmado contiene `PACIENTE_LLEGO` y `REEVALUACION`, ausentes del enum versionado;
- las cinco FK reales usan `ON DELETE CASCADE`, con riesgo de pérdida histórica ante deletes físicos todavía no comprobados;
- `additional_rate_id` es obligatorio y no representa la cita adicional del MVP;
- no existe unique médico+fecha+hora que impida colisiones concurrentes;
- los campos financieros y `autorizado_por varchar(255)` confirman mezcla entre agenda y finanzas;
- el código legacy puede leer/escribir directamente esta tabla si `other_system` estuviera configurada, pero la configuración productiva revisada no la contiene y su uso no está confirmado;
- el flujo temporal productivo primario usa otra tabla `appointments` no sincronizada.

El DDL, enum, agregados básicos y flujo productivo del llamador ya están confirmados. La estrategia puede pasar a planificación, manteniendo como condiciones la evidencia de tablas dependientes y el diseño posterior del contrato explícito:

1. añadir estructuras nuevas sin retirar columnas;
2. backfill de conceptos demostrables;
3. diseñar correlación y proyección explícitas hacia el llamador temporal;
4. introducir el contrato controlado sin compartir tablas;
5. comprobar no uso y contener/retirar el legacy de forma controlada;
6. retirar columnas únicamente tras reconciliación.

No debe introducirse doble escritura entre tablas ERP y temporal. La integración debe tener un coordinador, idempotencia y reconciliación explícitos.

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
- roles productivos básicos confirmados; asignaciones y permisos detallados pendientes;
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
| Agenda/pre-reserva | Sí | Citas futuras | Sí, sin compartir tabla | VIABLE PARA PLANIFICACIÓN; REQUIERE CONTRATO ERP–LLAMADOR |
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

El flujo productivo primario está aislado y no sincronizado. No debe añadirse una escritura paralela improvisada entre `appointments` ERP y temporal; el contrato futuro debe ser el único coordinador. El legacy residual debe verificarse sin consumidores y contenerse antes del piloto.

## 6. Compatibilidad temporal recomendada

### Agenda/llamador

- conservar el esquema ERP actual mientras se introduce el contrato;
- definir correlación entre cita ERP y registro temporal sin reutilizar ids por suposición;
- impedir nuevos consumidores directos y verificar que el legacy no tenga uso;
- publicar/recibir transiciones mediante un contrato controlado e idempotente;
- retirar o contener las rutas legacy tras evidencia de no uso;
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

1. Enum versionado incompatible con el enum productivo confirmado.
2. Cuatro columnas horarias esperadas por el código/llamador ausentes de `appointments` productivo.
3. Cinco FK de citas con `ON DELETE CASCADE`; posible pérdida histórica si existen deletes físicos.
4. Sin protección unique médico+fecha+hora frente a concurrencia/doble reserva.
5. Datos financieros potencialmente duplicados en cita/voucher/payment/caja.
6. Correlativos sin reconciliación productiva.
7. Stock sin trazabilidad ni saldo inicial validado.
8. APP_DEBUG, jobs, cron, backups y restauración no verificados.
9. Despliegue puede no ser reproducible si el panel ejecuta `composer update`.
10. Exposición de PII operativa en visores/logs de navegador.

## 8. Decisiones y evidencias pendientes

### Evidencia técnica

- DDL de las 31 tablas restantes y detalles dependientes;
- perfil agregado restante y reconciliación financiera;
- asignaciones/permisos detallados; los roles productivos básicos ya fueron confirmados;
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

Continuar, mediante un operador autorizado, el paquete de solo lectura únicamente para la evidencia todavía pendiente documentada en:

- `SCHEMA_PRODUCTIVO_VERIFICADO.md`;
- `PERFIL_DATOS_PRODUCTIVOS.md`.

Después incorporar únicamente resultados agregados/DDL sanitizados, completar la matriz de drift y decidir qué primera migración de diseño detallado es viable. La evidencia actual no cierra la reconciliación ni autoriza implementación.

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

Estos faltantes requieren diseño posterior de BD. El DDL/enum de `appointments` ya reduce incertidumbre, pero las migrations solo serán viables después de reconciliar tablas dependientes, contrato del llamador y datos afectados.

### 10.2 Campos legacy que deben preservarse

Durante el piloto se deben conservar o proyectar compatiblemente:

- identidad y relaciones de `appointments`;
- `user_id` como creador legado;
- profesional, paciente, servicio, fecha, hora, duración y turno;
- `estado_cita` productivo y una proyección compatible para las cuatro horas que producción no almacena actualmente;
- importes/estado de pago como evidencia histórica, aunque dejen de ser la fuente canónica futura;
- ids usados por tickets, líneas, pagos y ventas.

No debe reinterpretarse `patients.user_id` como responsable de la cita ni `additional_rate_id`/`cita_doble` como cita adicional. `appointments.user_id` debe preservarse como creador legado; no debe convertirse retroactivamente en prueba de responsabilidad histórica después de una futura reasignación.

### 10.3 Orden de migración recomendado para el MVP

1. Conservar el DDL/enum de `appointments` ya reconciliado y confirmar el contrato productivo del llamador/tablas dependientes.
2. Añadir trazabilidad de creador, responsable, modificadores y reasignaciones sin retirar campos legacy.
3. Introducir regla unificada de horarios, bloqueos y concurrencia.
4. Incorporar pre-reserva y confirmación por adelanto/excepción.
5. Incorporar el maestro configurable y las autorizaciones de COSTO 0/sobreagenda.
6. Incorporar tipo y ciclo de cita adicional, respetando horario activo, fin de bloque, carga y capacidad.
7. Proyectar compatibilidad hacia llamador y finanzas.
8. Cortar lecturas/escrituras legacy solo con métricas de equivalencia y retorno probado.

### 10.4 Nuevos riesgos de migración identificados

- usar el enum versionado puede rechazar `PACIENTE_LLEGO` o `REEVALUACION`, presentes en producción;
- agregar una unicidad simplista por médico/fecha/hora puede invalidar citas dobles, duraciones variables, sobreagenda y adicionales;
- cambiar `ON DELETE CASCADE` sin analizar deletes existentes puede alterar comportamiento; mantenerlo sin control también arriesga historia;
- agregar directamente las cuatro horas del llamador sin confirmar el flujo desplegado puede crear un contrato falso o duplicado;
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
- **CONFIRMADO EN PRODUCCIÓN:** DDL/enum de `appointments`, agregados básicos de citas y roles productivos básicos.
- **CONFIRMADO EN PRODUCCIÓN:** el flujo productivo primario del llamador es `visorTemporal`, sobre base y `appointments` propios, sin sincronización confirmada con el ERP.
- **PENDIENTE DE PRODUCCIÓN:** DDL dependiente, asignaciones detalladas y evidencia operativa no bloqueante restante.

## 11. Impacto de la verificación pasiva del llamador

**CONFIRMADO EN PRODUCCIÓN:** ADMISION inicia en `visorTemporal`; usa la base por defecto propia del llamador y no depende de `other_system`. La pantalla vacía observada no prueba error: puede no haber filas para la fecha/filtros. **Traer Datos** solo recarga el índice temporal y no sincroniza desde el ERP.

**CONFIRMADO EN CÓDIGO:** `visorTemporal` requiere las cuatro horas ausentes de `appointments` ERP y opera sobre otro modelo de datos. No se encontró sincronización. El legacy compatible con el ERP permanece en el código, pero `OTHER_SYSTEM_DB_*` no está configurada en producción y su uso real no está confirmado.

**RIESGO PRODUCTIVO POTENCIAL:**

- las dos rutas legacy mutantes carecen de autenticación/autorización visible y siguen siendo deuda desplegada, aunque no se ha demostrado que tengan consumidores ni conexión efectiva al ERP;
- el legacy usa `updated_at` como señal operativa de rellamado;
- no existe consultorio/destino físico en el contrato auditado;
- no hay correlación ni transporte confirmado entre la cita administrativa del ERP y la atención temporal del llamador;
- compartir directamente una tabla nueva recrearía el acoplamiento que el TO-BE debe evitar.

Consecuencia: **GATE C = CONFIRMADO — FLUJO PRODUCTIVO PRIMARIO: `visorTemporal`**. Existe evidencia suficiente para planificar el MVP con un límite claro: ERP y llamador deben integrarse posteriormente mediante un contrato explícito, sin escritura directa compartida. Aún deben diseñarse correlación, autenticación, idempotencia, estados, privacidad, observabilidad, reconciliación y transición; esta fase no implementa ninguno.
