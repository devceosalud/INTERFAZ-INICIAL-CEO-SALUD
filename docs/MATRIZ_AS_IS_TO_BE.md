# Matriz de evolución AS-IS → TO-BE

## 1. Propósito

Esta matriz relaciona el esquema versionado y las responsabilidades actuales con el modelo lógico objetivo. No confirma que el esquema productivo sea idéntico a las migrations del repositorio.

Las tablas y campos citados están **confirmados en código**. Su existencia y forma exacta en la base heredada están **pendientes de validación productiva**. Los destinos son **propuestas técnicas**, salvo las reglas marcadas en la documentación funcional como confirmadas por negocio.

### Estados de evolución

- **Conservar:** la responsabilidad principal permanece, con ajustes compatibles.
- **Ampliar:** conserva la entidad y añade alcance o relaciones.
- **Dividir:** la tabla actual mezcla responsabilidades que deben separarse gradualmente.
- **Consolidar:** conceptos duplicados convergen en una fuente de verdad.
- **Reemplazar gradualmente:** una nueva fuente de verdad asume el uso, manteniendo compatibilidad temporal.
- **Framework:** infraestructura estándar, fuera del rediseño funcional principal.
- **Pendiente:** requiere inventario productivo o decisión de negocio.

## 2. Advertencia sobre el inventario

El repositorio contiene 27 archivos de migration que, según el análisis previo, construyen 31 tablas de aplicación/framework; sumando la tabla `migrations`, una instalación limpia aspira a 32 tablas. Producción reporta 32 tablas y 27 migrations registradas, pero la coincidencia numérica no prueba igualdad de columnas, restricciones, índices ni datos.

Antes de implementar se requiere un inventario de solo lectura del esquema productivo y una comparación contra el repositorio.

## 3. Framework y seguridad

| Tabla AS-IS | Responsabilidad actual | Hallazgo o límite | Destino TO-BE | Evolución | Tratamiento histórico |
|---|---|---|---|---|---|
| `users` | Cuenta, nombre, correo y contraseña | Mezcla datos visibles de persona con credencial; no enlaza formalmente trabajador | Usuario relacionado con Persona/Trabajador | Dividir/ampliar | Conservar id y autorías; poblar vínculos de forma nullable y validada |
| `roles` | Roles Spatie | Matriz aún provisional | Rol como agrupador de capacidades | Conservar | Mantener asignaciones y auditar cambios futuros |
| `permissions` | Permisos Spatie | Nomenclatura/alcance deberán evolucionar por acciones | Capacidades granulares | Ampliar | Mapear permisos legados; no retirar hasta completar corte |
| `model_has_roles` | Asignación usuario/rol | Depende del modelo Spatie | Asignación de roles | Conservar | Preservar vigencias históricas mediante auditoría futura |
| `model_has_permissions` | Permiso directo | Riesgo de excepciones difíciles de gobernar | Excepción explícita y auditada, si se admite | Pendiente | Inventariar asignaciones antes de definir política |
| `role_has_permissions` | Capacidades por rol | Es la base actual de autorización | Matriz de capacidades | Conservar/ampliar | Migrar en paralelo con pruebas positivas y negativas |
| `password_resets` | Recuperación de contraseña Laravel 9 | Esquema de framework antiguo | Recuperación segura compatible con versión futura | Framework | Aplicar evolución oficial cuando se actualice Laravel |
| `personal_access_tokens` | Tokens Sanctum | Uso real no demostrado | Acceso API controlado, si se requiere | Pendiente/framework | Revocar tokens no identificados antes de cambios |
| `failed_jobs` | Fallos de cola | No hay flujo de jobs de negocio consolidado | Observabilidad de trabajos asíncronos | Conservar/framework | Definir retención sin exponer payload sensible |
| `migrations` | Registro de migrations ejecutadas y lotes | No demuestra por sí solo que el DDL real coincida con los archivos actuales | Trazabilidad de evolución del esquema | Conservar/framework | No reescribir registros; comparar contra DDL productivo |

## 4. Catálogos territoriales y de interacción

| Tabla AS-IS | Responsabilidad actual | Hallazgo o límite | Destino TO-BE | Evolución | Tratamiento histórico |
|---|---|---|---|---|---|
| `departments` | Catálogo geográfico | Maestro de referencia | Ubicación/dirección | Conservar | Mantener códigos usados; versionar actualizaciones |
| `provinces` | Catálogo geográfico | Maestro de referencia | Ubicación/dirección | Conservar | Mantener referencias existentes |
| `districts` | Catálogo geográfico | Maestro de referencia | Ubicación/dirección | Conservar | Mantener referencias existentes |
| `channels` | Canal de captación/solicitud | Semántica se solapa potencialmente con medio de interacción | Origen comercial o canal de solicitud | Consolidar tras inventario | Mapear valores reales antes de eliminar duplicados |
| `interaction_media` | Medio de interacción | Diferencia con canal no está formalizada | Medio de contacto/interacción | Consolidar o redefinir | Conservar valor original en citas/interacciones históricas |

## 5. Pacientes y responsables

| Tabla AS-IS | Responsabilidad actual | Hallazgo o límite | Destino TO-BE | Evolución | Tratamiento histórico |
|---|---|---|---|---|---|
| `patients` | Identidad, datos de contacto, demografía y números de historia | Duplica conceptos de persona; posee dos campos de historia; generación de número tiene riesgo de concurrencia; `user_id` no define claramente identidad | Persona + Paciente + identificador clínico | Dividir gradualmente | Conservar id legado y ambos números hasta reconciliar; no fusionar duplicados automáticamente |
| `responsibles` | Responsable ligado a paciente | Datos personales duplicados; relación/modelo previamente inconsistente; campo legado `parentezco` | Persona + relación Paciente–Responsable | Dividir/reemplazar gradualmente | Preservar texto y vínculo original; normalizar solo con reglas verificadas |

## 6. Personal, especialidades, servicios y agenda

| Tabla AS-IS | Responsabilidad actual | Hallazgo o límite | Destino TO-BE | Evolución | Tratamiento histórico |
|---|---|---|---|---|---|
| `specialties` | Maestro de especialidades | Estructura simple válida, pero el médico queda limitado indirectamente | Especialidad | Conservar/ampliar | Mantener ids y nombres usados históricamente |
| `doctors` | Médico, especialidad, CMP/RNE | Persona/profesional mezclados; una sola especialidad; no vincula usuario/trabajador | Persona + Profesional + registros + relación M:N con especialidades | Dividir gradualmente | Conservar id como referencia cruzada y datos originales regulatorios |
| `services` | Servicio por especialidad | Se duplica con artículos `items` de tipo servicio; carece de definición comercial completa | Servicio clínico/operativo | Consolidar con catálogo vendible | Crear mapeo explícito con líneas y tarifas históricas |
| `additional_rates` | Tarifas adicionales | Alcance y regla de aplicación poco expresivos | Regla de tarifa/precio | Reemplazar gradualmente | Guardar tarifa aplicada en documentos históricos |
| `doctor_services` | Servicio habilitado y precios por médico | Sin integridad/unicidad suficiente en migration; mezcla habilitación y tarifas | Oferta profesional de servicio + reglas de precio | Dividir/ampliar | Mantener combinaciones y precios con vigencias derivadas |
| `doctor_schedules` | Horario semanal o fechado por médico | Mezcla recurrencia, excepción y disponibilidad; no tiene sede/consultorio | Plantilla de horario + excepción/bloqueo + asignación de recursos | Dividir gradualmente | Preservar registros fuente y documentar interpretación usada |
| `appointments` | Cita, agenda, estados del llamador, precios, saldo, pago/exoneración y datos operativos | Agregado sobrecargado; responsabilidades financieras duplicadas; estados temporales sin historial; migration presenta duplicación conocida de `hora_llamado` | Pre-reserva + Cita + flujo/Atención + relaciones a obligación/pago | Dividir por etapas | Mantener fila como referencia legada; migrar estados y finanzas con reconciliación; no eliminar campos tempranamente |

## 7. Comercial, caja, pagos y facturación

| Tabla AS-IS | Responsabilidad actual | Hallazgo o límite | Destino TO-BE | Evolución | Tratamiento histórico |
|---|---|---|---|---|---|
| `items` | Catálogo unificado de servicio/producto, precio, impuesto y saldo de stock | Duplica servicios; `stock_actual` es saldo mutable global; servicio y producto tienen reglas distintas | Servicio + Producto + vista vendible; stock por almacén derivado | Dividir/consolidar | Conservar ids usados por comprobantes; crear correspondencias estables |
| `cashiers` | Maestro de cajas | No tiene sede | Caja asociada a Sede | Ampliar | Asignar sede inicial verificada sin alterar historia |
| `cashier_shifts` | Apertura/cierre, usuario, montos y diferencia | Base útil; debe reforzar estados, concurrencia y evidencia de arqueo | Turno de caja + arqueo/diferencia | Ampliar | Conservar aperturas/cierres; no recalcular cifras históricas sin evidencia |
| `cash_movements` | Ingreso/egreso por turno | Concepto libre y débil vínculo causal | Movimiento de caja tipificado y relacionado | Ampliar | Mantener concepto original y mapear tipo/origen cuando sea inequívoco |
| `vouchers` | Venta/comprobante, cliente, totales, crédito/débito, estado SUNAT y artefactos | Mezcla operación comercial, documento interno y documento tributario; estados fiscales parciales | Venta/obligación + documento tributario + intentos fiscales | Dividir gradualmente | Documento emitido permanece inmutable; conservar XML/CDR/hash con custodia segura |
| `voucher_items` | Línea facturada con snapshot e item polimórfico | Polimorfismo puede debilitar integridad; línea sirve a venta y a documento fiscal a la vez | Línea de venta + línea/instantánea fiscal | Dividir o proyectar | Conservar descripción, cantidad, precio, impuesto, médico y comisión originales |
| `payments` | Pago asociado a voucher, medio, operación, usuario y turno | Pago depende del comprobante; no modela aplicación a obligaciones, evidencia/verificación o reversos completos | Pago + evidencia + verificación + aplicación + reverso | Reemplazar/ampliar | Conservar cada pago; migrar su aplicación actual sin duplicar monto |
| `voucher_series` | Tipo, serie y correlativo actual por caja | Ámbito fiscal ligado a caja; necesita punto de emisión y concurrencia robusta | Punto de emisión + serie/correlativo fiscal | Ampliar/reemplazar gradualmente | Preservar secuencia y documentos; validar huecos/duplicados antes del corte |

## 8. Entidades que no existen como fuente de verdad separada

| Concepto TO-BE | Evidencia AS-IS relacionada | Situación | Estrategia de incorporación |
|---|---|---|---|
| Sede | Sistema opera actualmente en una sede; recursos sin clave de sede | No modelada | Crear entidad y asociar gradualmente recursos a una sede inicial validada |
| Consultorio | Marcadores/flujo de atención sin maestro consolidado identificado | Ausente o implícito | Incorporar antes de agenda multi-sede y llamador |
| Punto de emisión | Serie ligada a caja | Implícito | Separar ámbito fiscal del puesto de caja |
| Almacén | `items.stock_actual` global | Ausente | Crear almacén inicial y libro de movimientos antes de stock multi-sede |
| Persona | Datos repetidos en usuarios, pacientes, responsables y médicos | Ausente | Incorporar identidad canónica con enlaces no destructivos |
| Trabajador | Usuario representa parcialmente al operador | Ausente | Modelar relación operativa y vincular usuarios actuales |
| Registro profesional | CMP/RNE en `doctors` | Embebido | Separar cuando se valide cardinalidad y vigencia |
| Pre-reserva | Cita/flujo manual actual | Ausente | Crear estado temporal: 15 minutos por defecto, configurable, con expiración y extensión auditada |
| Excepción de agenda | `doctor_schedules` mezcla fechas y recurrencia | Embebida | Separar plantilla de modificaciones fechadas |
| Atención | Tiempos dentro de `appointments` | Embebida | Crear episodio operativo/clínico relacionado con cita |
| Historia clínica electrónica | Campos identificadores en pacientes y marcadores en citas | No implementada como dominio completo | Incorporar atención, registro híbrido, cierre, adenda y acceso auditado |
| Reevaluación | Precio/días en `doctor_services`; flujo no explícito | Parcial/implícita | Crear vínculo causal con atención original y política versionada |
| Venta | `vouchers` funciona también como venta | Mezclada | Extraer operación comercial conservando vínculo con comprobante legado |
| Obligación/cargo | Saldos en cita/voucher | Derivada y duplicada | Crear fuente de verdad y reconciliar importes antes de cortar lecturas |
| Evidencia/verificación de pago | Número de operación; captura gestionada fuera o sin modelo identificado | Parcial | Almacenamiento protegido y flujo verificable |
| Aplicación de pago | Pago directo a voucher | Ausente | Permitir asignación parcial a obligaciones y adelantos |
| Excepción de adelanto | Campos de exoneración/autorización en cita | Embebida | Migrar a autorización trazable con motivo y actor |
| Arqueo detallado | Totales en turno | Parcial | Añadir evidencia sin reescribir cierres legados |
| Movimiento de inventario | Solo saldo `stock_actual` | Ausente | Establecer saldo inicial verificado y registrar todo cambio posterior |
| Existencia por almacén | Saldo global en item | Ausente | Proyección derivada por producto/almacén |
| Documento fiscal separado | Datos fiscales en voucher | Mezclado | Separar ciclo fiscal de la venta/documento interno |
| Intento de integración | Campos SUNAT finales en voucher | Parcial | Registrar intentos, idempotencia y artefactos por operación |
| Auditoría de negocio | Timestamps y logs dispersos | Insuficiente | Evento append-only para acciones sensibles |
| Evento de salida | No identificado | Ausente | Incorporar cuando se activen colas/integraciones confiables |

## 9. Matriz por dominio

| Dominio | Fuente AS-IS predominante | Fuente TO-BE | Riesgo de transición | Condición de corte |
|---|---|---|---|---|
| Organización | Recursos sin sede | Sede y recursos asociados | Medio | Todos los recursos activos asignados y flujos compatibles |
| Identidad | Datos duplicados por perfil | Persona + perfiles | Alto | Reglas de deduplicación y mapeo completo verificadas |
| Pacientes | `patients`, `responsibles` | Paciente/Persona/Relación responsable | Alto | Identificadores y responsables conciliados |
| Personal | `doctors`, `users` | Trabajador/Usuario/Profesional | Alto | Autorías y agendas preservadas |
| Agenda | `doctor_schedules`, `appointments` | Horarios/excepciones/pre-reservas/citas | Alto | Pruebas de concurrencia y equivalencia de disponibilidad |
| Atención/HCE | Campos de cita | Atención + historia clínica | Alto | Flujo clínico mínimo, permisos, auditoría y custodia aprobados |
| Comercial | `items`, `services`, `vouchers` | Catálogo + venta + obligación | Alto | Totales históricos reconciliados y doble conteo descartado |
| Pagos | `payments`, campos en cita/voucher | Pago + aplicación + evidencia | Crítico | Conciliación exacta por operación/importe/obligación |
| Caja | `cashiers`, `cashier_shifts`, `cash_movements` | Caja/turno/movimientos/arqueo | Alto | Saldo esperado equivalente y diferencias preservadas |
| Inventario | `items.stock_actual` | Movimientos + existencia por almacén | Alto | Inventario físico y saldo inicial aprobados |
| Facturación | `vouchers`, `voucher_items`, `voucher_series` | Documento fiscal/serie/intentos | Crítico | Revisión fiscal, unicidad y artefactos preservados |
| Integraciones | Services y campos dispersos | Adaptadores + operaciones/eventos | Alto | Modo simulado, idempotencia y recuperación probados |
| Auditoría | Timestamps/logs | Auditoría de negocio | Medio/alto | Catálogo de eventos y acceso definido |

## 10. Reglas para conservar historia

1. No cambiar identificadores legados durante la primera transición.
2. Mantener una tabla o relación de correspondencia cuando una fila se divida en varias entidades.
3. Toda migración de datos registra versión, origen, destino, fecha, resultado y errores.
4. Los backfills son idempotentes y reanudables.
5. Los importes históricos se comparan antes y después por moneda, documento, pago y turno.
6. Los documentos emitidos y registros clínicos cerrados no se normalizan sobrescribiendo su contenido.
7. Los campos legados se dejan en lectura durante un periodo de estabilización antes de deprecarlos.
8. La eliminación física solo podrá considerarse en una fase posterior, con retención legal y respaldo comprobado.

## 11. Hallazgos que impiden declarar un mapeo definitivo

- no se ha contrastado el DDL real de producción;
- no se conocen volumen, nulos, duplicados, huérfanos ni distribuciones de estados;
- no se ha conciliado el valor financiero entre citas, vouchers, pagos y caja;
- no se ha inventariado el significado real de todos los estados y textos libres;
- no se ha validado si existen archivos/evidencias fuera de la base de datos;
- no se ha revisado con especialista el ciclo SUNAT real;
- no se ha definido la retención clínica y tributaria aplicable.

Por estas razones, toda estrategia de tabla física sigue siendo propuesta y debe pasar por descubrimiento productivo de solo lectura antes de implementarse.
