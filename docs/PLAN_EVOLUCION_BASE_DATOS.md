# Plan de evolución de base de datos

## 1. Objetivo

Definir una estrategia segura para evolucionar la base de datos legada hacia el modelo TO-BE sin reescritura masiva, pérdida de historia ni interrupciones innecesarias.

Este plan no autoriza conexiones a producción, migrations ni cambios de datos. Toda ejecución futura requerirá respaldo, ambiente aislado, revisión y ventana controlada.

## 2. Principios no negociables

1. No editar migrations ya ejecutadas en producción para “corregir” el pasado.
2. Incorporar cambios mediante migrations nuevas, pequeñas y compatibles hacia adelante.
3. No asumir que producción coincide con el repositorio por tener el mismo número de tablas/migrations.
4. No realizar transformaciones destructivas sin respaldo restaurable y reconciliación previa.
5. Conservar identificadores legados y trazabilidad de correspondencia.
6. Separar cambio de esquema, backfill, cambio de lectura y retiro de legado en despliegues distintos.
7. Diseñar scripts/migrations de datos idempotentes, reanudables y observables.
8. Usar MySQL aislado para validar restricciones, índices, bloqueos y concurrencia.
9. Preferir compensación y corte reversible de aplicación sobre rollback destructivo de datos.
10. No usar datos productivos sin anonimización y autorización.

## 3. Prerrequisitos antes de la primera migration TO-BE

### 3.1 Inventario productivo de solo lectura

Obtener, sin exponer datos personales ni secretos:

- versión de MySQL/MariaDB, charset, collation y modos SQL;
- tablas, columnas, tipos, nulos y valores por defecto;
- claves primarias/foráneas, índices y restricciones únicas;
- triggers, vistas, eventos o procedimientos, si existen;
- migrations registradas y sus lotes;
- conteos por tabla y volumen aproximado;
- distribución de estados y presencia de valores fuera de catálogo;
- duplicados, huérfanos y referencias rotas;
- archivos externos vinculados desde la base.

El resultado será un snapshot de metadatos versionado y sanitizado, no un volcado de secretos.

### 3.2 Respaldo y restauración

Antes de cambios reales se debe demostrar:

- respaldo consistente de base y archivos;
- restauración exitosa en un entorno separado;
- tiempo aproximado de restauración;
- responsable y procedimiento de decisión ante incidente;
- punto de recuperación y ventana de pérdida tolerable definidos por negocio.

Un respaldo no probado no se considera estrategia de recuperación.

### 3.3 Clon aislado y anonimizado

Construir un entorno MySQL con estructura y distribución representativas. Los datos personales, clínicos, financieros, fiscales y credenciales deben anonimizarse o sustituirse preservando relaciones y casos límite.

SQLite continúa siendo útil para tests rápidos, pero no valida adecuadamente:

- bloqueos `FOR UPDATE` y competencia real;
- semántica de índices/foreign keys específica;
- enums y conversiones de tipos;
- collations y unicidad de texto;
- DDL y tiempos sobre volúmenes reales;
- deadlocks o niveles de aislamiento.

## 4. Estrategia expandir → migrar → cortar → contraer

### Paso A: expandir

Agregar estructuras nuevas sin romper lecturas/escrituras existentes:

- columnas inicialmente nullable o con valor seguro;
- nuevas entidades y relaciones;
- índices creados con estrategia compatible con el motor/volumen;
- tablas de correspondencia y control de backfill;
- feature flags y adaptadores de compatibilidad.

No introducir de inmediato restricciones que los datos legados incumplen.

### Paso B: migrar y reconciliar

Copiar/transformar datos en lotes pequeños:

- selección por rango estable de identificadores;
- checkpoint del último lote;
- reintento idempotente;
- conteos y sumas de control;
- registro de excepciones sin descartarlas;
- ejecución limitada para no degradar producción.

### Paso C: doble escritura controlada

Cuando sea necesaria, una única acción de aplicación escribe en el modelo nuevo y mantiene compatibilidad con el legado dentro de la misma transacción. No se duplicará lógica en controladores o pantallas.

Cada doble escritura tendrá:

- propietario y fecha prevista de retiro;
- métrica de divergencia;
- reconciliación automática o reporte;
- comportamiento definido si una escritura falla.

### Paso D: lectura paralela y comparación

El sistema calcula o consulta ambos modelos sin cambiar todavía la respuesta visible. Se comparan:

- identificadores;
- estados;
- importes y saldos;
- disponibilidad;
- existencias;
- correlativos y relaciones.

Las diferencias se clasifican antes de activar la nueva lectura.

### Paso E: corte gradual

Activar por módulo, sede, rol o porcentaje, cuando sea seguro. Mantener una bandera reversible para volver a lectura legada si el modelo nuevo presenta divergencias.

### Paso F: endurecer

Solo después de completar y validar los datos:

- hacer obligatorias relaciones;
- crear restricciones únicas definitivas;
- cerrar escrituras legadas;
- reforzar foreign keys y checks compatibles.

### Paso G: contraer

Deprecar campos/tablas legados después de varios ciclos estables, cumplimiento de retención y respaldo verificado. La eliminación física es una fase separada y explícitamente aprobada.

## 5. Oleadas propuestas de evolución

### Oleada 0 — Reconciliación y guardas

Objetivo: conocer la realidad y evitar agravar inconsistencias.

- inventario de esquema productivo;
- perfilado de calidad de datos;
- conciliación preliminar financiera;
- catálogo de estados existentes;
- procedimientos de respaldo/restauración;
- ambiente MySQL aislado;
- identificadores de correlación y marco de auditoría para futuras migraciones.

Salida: informe de compatibilidad y decisión `go/no-go` por dominio.

### Oleada 1 — Organización e identidad

Objetivo: crear las referencias que condicionan todos los módulos.

- sede inicial validada;
- asociaciones opcionales de consultorios, cajas, puntos de emisión y almacenes;
- Persona, Trabajador, Profesional y enlaces a Usuario/Paciente/Doctor;
- correspondencias legadas;
- deduplicación asistida, nunca automática por coincidencia débil.

Condición: ninguna autoría o relación histórica queda huérfana.

### Oleada 2 — Catálogo y personal clínico

Objetivo: separar servicios, productos, especialidades y habilitaciones.

- relación profesional–especialidad;
- oferta profesional de servicio;
- reglas de precio con vigencia;
- correspondencia `services`/`items`;
- instantáneas históricas intactas.

Condición: cada referencia usada por citas y comprobantes tiene un destino inequívoco o queda marcada como excepción.

### Oleada 3 — Agenda, pre-reserva y atención operativa

Objetivo: retirar responsabilidades de agenda/llamador de `appointments` sin perder continuidad.

- plantillas y excepciones de horario;
- pre-reservas con 15 minutos por defecto, configuración, expiración y extensión auditada;
- citas con restricciones de concurrencia;
- atención y transiciones de llegada/espera/consulta;
- reconsulta vinculada al episodio origen.

Condición: comparación de disponibilidad y escenarios simultáneos satisfactoria en MySQL.

### Oleada 4 — Historia clínica

Objetivo: habilitar el registro longitudinal seguro.

- atención clínica como ancla;
- registro híbrido borrador/cerrado;
- adendas y reapertura excepcional;
- componentes estructurados priorizados;
- almacenamiento protegido y auditoría de acceso.

Condición: flujo clínico mínimo, permisos, retención y revisión legal aprobados.

### Oleada 5 — Comercial, obligaciones y pagos

Objetivo: establecer fuentes de verdad económicas.

- venta y líneas con snapshot;
- obligación/cargo;
- pago, evidencia, verificación y aplicación;
- excepción de adelanto;
- reversos/devoluciones compensatorias.

Condición: conciliación exacta con `appointments`, `vouchers` y `payments`; ningún importe contado dos veces.

### Oleada 6 — Caja

Objetivo: vincular custodia de valor con pagos sin confundirlos.

- cajas por sede;
- turnos y estados reforzados;
- movimientos tipificados;
- arqueos y diferencias;
- relaciones con pagos en efectivo.

Condición: saldo esperado, contado y diferencias coinciden con la historia legada o tienen excepción documentada.

### Oleada 7 — Inventario

Objetivo: reemplazar el saldo global por movimientos trazables.

- almacén inicial por sede;
- inventario físico y saldo de apertura aprobado;
- movimientos de ingreso/salida/traslado/ajuste;
- existencia derivada;
- integración con líneas de producto vendidas.

Condición: política de stock insuficiente y ajustes aprobada; conteo inicial conciliado.

### Oleada 8 — Facturación e integraciones fiscales

Objetivo: separar el documento tributario y su ciclo externo.

- puntos de emisión;
- series y asignación atómica;
- documento fiscal y líneas históricas;
- notas de corrección;
- intentos, XML, CDR, hash y estados;
- cola e idempotencia SUNAT.

Condición: validación contable/fiscal externa, pruebas en ambiente autorizado y plan de contingencia.

### Oleada 9 — Retiro del legado

Objetivo: cerrar compatibilidad temporal.

- detener dobles escrituras;
- retirar lecturas antiguas;
- archivar mapas y reportes de reconciliación;
- mantener columnas en solo lectura durante retención;
- proponer eliminación física en un cambio independiente.

## 5.1 Matriz de transición de cambios importantes

| Cambio | Datos históricos afectados | Migración posible | Compatibilidad temporal | Reversión preferida | Riesgo principal |
|---|---|---|---|---|---|
| Recursos asociados a Sede | Cajas, horarios, citas, documentos y futuros almacenes | Sí, asignando una sede inicial validada | Clave nullable + valor por defecto solo en capa de aplicación | Volver a lectura legada; conservar asociación nueva | Asignar una sede incorrecta a historia previa |
| Persona/Trabajador/Usuario/Profesional | Nombres, documentos, contactos, autorías y roles | Parcial, mediante correspondencias y revisión de duplicados | Enlaces opcionales y doble lectura | Desactivar lectura nueva sin borrar personas | Fusionar identidades distintas o duplicar una misma persona |
| Paciente y Responsable | Identidad clínica, números de historia y parentescos | Sí con excepciones revisadas | Id legado y relación nueva en paralelo | Restituir lectura desde tablas legadas | Perder identidad longitudinal o responsable correcto |
| Doctor, especialidades y servicios | Autorías, citas, precios y habilitaciones | Sí, con mapas M:N y vigencias inferidas/documentadas | `doctor_id` legado + referencias nuevas | Volver al mapa legado | Inventar una habilitación o vigencia no demostrada |
| Horarios, pre-reservas, citas y atención | Agenda futura y marcadores históricos | Parcial; estados pasados pueden carecer de eventos | Comparación de disponibilidad y referencia a `appointment` legado | Feature flag hacia agenda legada; reconciliar operaciones del corte | Doble reserva o secuencia de atención falsa |
| Historia clínica | Solo identificadores/marcadores existentes; no hay HCE completa demostrada | No inferir contenido clínico ausente | HCE nueva vinculada a atenciones; legado solo como contexto | Desactivar módulo nuevo conservando registros creados | Fabricar o exponer datos clínicos |
| `services` + `items` | Líneas vendidas, precios, impuestos, stock y citas | Sí mediante correspondencia explícita; no por nombre solamente | Referencia vendible compatible + snapshots legados | Volver a catálogo legado | Duplicar servicios o convertirlos erróneamente en productos |
| Venta/obligación/pago | Citas, vouchers, pagos, saldos y operaciones | Sí, solo tras conciliación de importes | Doble lectura/escritura centralizada y métricas de divergencia | Corte por feature flag + libro de operaciones posteriores | Doble contabilización o saldo incorrecto |
| Caja | Turnos, movimientos, aperturas, cierres y diferencias | Sí con clasificación de excepciones | Turnos legados relacionados con pagos nuevos | Mantener nueva relación sin alterar cifras legadas | Cambiar cierres históricos o confundir vuelto con ingreso |
| Inventario por movimientos | `items.stock_actual` y ventas de productos | Solo desde saldo inicial/conteo aprobado | Saldo legado en observación; nuevo movimiento como fuente gradual | Detener descargas automáticas y reconciliar | Stock inicial falso o doble descuento |
| Venta vs documento tributario | Vouchers, líneas, series, XML/CDR/hash y estados | Sí con revisión fiscal y mapeo de documentos | Campos SUNAT legados preservados; nuevo ciclo en paralelo controlado | Volver a lectura/cola anterior sin reasignar correlativos | Duplicar documentos o romper secuencia fiscal |
| Auditoría e integraciones | Logs, estados finales y escasa historia de intentos | Solo prospectiva salvo evidencia existente | Registrar eventos nuevos sin reinterpretar el pasado | Desactivar consumidores, conservar bitácora | Filtrar datos sensibles o perder eventos externos |

“Reversión” no significa borrar datos nuevos. Significa volver de forma controlada a la lectura/escritura anterior, conservar evidencia y reconciliar cualquier operación ocurrida durante el corte.

## 6. Tratamiento específico de los puntos de mayor riesgo

### 6.1 `appointments.hora_llamado` duplicado en migration

La migration conocida contiene una definición duplicada. No debe editarse silenciosamente porque ya forma parte de la historia versionada.

Acciones futuras:

1. comprobar cómo se ejecutó realmente en producción;
2. comparar DDL productivo, instalación limpia y expectativas del código;
3. documentar la divergencia;
4. emitir una migration correctiva nueva solo cuando se conozca el estado real;
5. cubrir ambos caminos de actualización en pruebas.

### 6.2 Pacientes e identidad

No fusionar por nombre o documento únicamente. Se necesita un proceso de coincidencia con niveles de confianza, revisión humana y capacidad de revertir enlaces sin borrar registros.

### 6.3 Finanzas

Preparar un libro de reconciliación por:

- cita;
- voucher/comprobante;
- pago y número de operación;
- turno/movimiento de caja;
- documento fiscal;
- venta/obligación futura.

Las diferencias se registran; no se “cuadran” cambiando el pasado sin evidencia.

### 6.4 Correlativos

Antes del corte:

- detectar duplicados y huecos;
- confirmar ámbito real de cada serie;
- fijar el siguiente correlativo a partir de documentos válidos y reglas fiscales;
- probar competencia simultánea;
- impedir que el mecanismo nuevo y legado asignen sobre la misma serie sin coordinación.

### 6.5 Inventario inicial

`items.stock_actual` no basta como historia. Debe compararse con conteo físico aprobado. El saldo inicial TO-BE se registra como movimiento de apertura con fecha, responsable y evidencia; las diferencias permanecen identificadas.

### 6.6 Historia clínica

No existe un backfill clínico automático desde marcadores operativos. Los datos legados solo se clasifican como clínicos si existe evidencia y una regla de migración aprobada. El texto clínico nunca se sintetiza o infiere para completar huecos.

## 7. Validaciones por cada migration futura

Antes:

- compatibilidad con versión real de motor;
- duración estimada y bloqueo esperado;
- espacio adicional;
- datos que incumplirían nuevas restricciones;
- respaldo y retorno de aplicación.

Durante:

- tiempos por lote;
- filas leídas/escritas/omitidas;
- errores y reintentos;
- carga del motor;
- divergencias de doble escritura.

Después:

- conteos y checksums lógicos;
- sumas financieras;
- referencias huérfanas;
- unicidad y estados;
- pruebas de lectura/escritura del módulo;
- observación durante un periodo acordado.

## 8. Estrategia de reversión

### Cambios aditivos

La reversión preferida es desplegar la versión anterior de la aplicación o desactivar el feature flag, dejando las estructuras nuevas sin uso. No se eliminan automáticamente columnas o datos generados.

### Backfills

Cada lote debe registrar qué transformó. Si el dato nuevo es incorrecto, se marca inválido o se reconstruye desde el legado; no se modifica el origen.

### Cortes de escritura

Se mantiene una ventana breve de compatibilidad y reconciliación. Volver al escritor legado exige comprobar qué operaciones ocurrieron durante el corte para no perderlas.

### Incidente grave

La restauración total se reserva a corrupción o indisponibilidad no compensable y sigue el procedimiento de continuidad aprobado. Puede implicar pérdida de operaciones posteriores al respaldo; por eso requiere decisión responsable, no una acción automática.

## 9. Artefactos de control requeridos

- snapshot sanitizado de esquema;
- matriz de correspondencia origen/destino;
- catálogo de estados y reglas de transformación;
- reporte de calidad y excepciones;
- libro de conciliación financiera;
- conteo/conciliación inicial de inventario;
- bitácora de backfills;
- resultados de pruebas de concurrencia;
- plan de despliegue y retorno por oleada;
- acta de validación empresarial cuando corresponda.

## 10. Pendientes de negocio y validación

- límite de extensiones de pre-reserva y desempate detallado ante simultaneidad;
- autorizadores de adelanto, anulaciones y reapertura clínica;
- política de stock insuficiente y ajustes;
- alcance de lotes/vencimientos/compras/proveedores;
- reglas fiscales/contables finales;
- retención de datos y evidencias;
- RPO/RTO y ventana de mantenimiento;
- volumen y calidad reales de producción;
- capacidades del hosting para workers, scheduler y despliegues escalonados.
