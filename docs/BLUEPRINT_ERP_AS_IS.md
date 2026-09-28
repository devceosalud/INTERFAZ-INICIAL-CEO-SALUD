# Blueprint maestro ERP CEO Salud — AS-IS

## 1. Alcance y criterio de evidencia

Este documento describe el sistema heredado tal como aparece en el repositorio. No certifica que un flujo funcione en producción ni convierte la lógica existente en requisito futuro.

- Commit productivo de referencia: `6552e52521ac59d9c1bf8bc3efd880533a87d11d`.
- Baseline técnico aislado: `9519043`.
- Contención de seguridad provisional incorporada: `b6b6246`.
- Rama de esta documentación: `planning/phase-2-erp-blueprint`.
- Base de datos declarada en producción: 32 tablas y 27 migrations registradas.
- Verificación local: las 27 migrations crean 31 tablas de aplicación/framework; Laravel crea además la tabla técnica `migrations`, completando las 32.

Convenciones:

- **VERIFICADO**: existe evidencia directa en código, rutas, migrations o pruebas.
- **INFERENCIA TÉCNICA**: interpretación razonable del código; requiere validar el uso real.
- **PENDIENTE DE DECISIÓN DE NEGOCIO**: el repositorio no permite decidir la regla correcta.

## 2. Arquitectura encontrada

### 2.1 Plataforma

- Monolito Laravel 9 sobre PHP `^8.0`.
- Interfaz server-rendered con Blade, jQuery/AJAX y Livewire 2.12.
- Persistencia Eloquent sobre MySQL en el despliegue heredado; tests aislados sobre SQLite en memoria.
- Autenticación propia por sesión en `AuthController`.
- Roles y permisos mediante `spatie/laravel-permission` 6.25.
- Sanctum está instalado y expone `GET /api/user`, pero no se encontró un flujo propio de emisión de tokens.
- Assets base con Laravel Mix; gran parte del tema visual y los scripts de dominio vive ya compilada bajo `public/`.

### 2.2 Capas efectivas

| Capa | Evidencia | Situación AS-IS |
|---|---|---|
| Rutas | `routes/web.php`, `admision.php`, `recepcion.php`, `administrador.php`, `api.php` | Separación nominal por rol, con carga duplicada de los tres archivos web desde `web.php` y `RouteServiceProvider`. |
| Controladores | `app/Http/Controllers` | CRUD y reglas de negocio mezclados. `ScheduleController` tiene 401 líneas y `AppointmentController` 306. |
| Componentes | `CashierShifts`, `CashMovements`, `Sales` | Los flujos financieros están concentrados en Livewire; `Sales` tiene 679 líneas. |
| Servicios | `ReniecService`, `SunatService` | Adaptadores HTTP mínimos. `SunatService` solo consulta RUC; no emite a SUNAT. |
| Dominio | 23 modelos Eloquent | Modelos mayormente anémicos; cálculos puntuales en `CashierShift` y `Voucher`. |
| Presentación | Blade + JS en `public/js` | Vistas de administrador/recepción reutilizan parciales y JS de admisión; esto expone controles que el backend provisional rechaza. |
| Persistencia | 27 migrations | Esquema reconstruible tras la corrección documentada de `hora_llamado`; compatibilidad/concurrencia MySQL no validada aún. |
| Procesos asíncronos | `failed_jobs`, configuración de queue | No existen Jobs de aplicación, workers requeridos ni tareas programadas. |
| Pruebas | `tests/Feature/Baseline` y `tests/Feature/Security` | Suite inicial de caracterización y seguridad: 61 tests, 162 aserciones al cierre de Fase 1. |

### 2.3 Autenticación y autorización actuales

La Fase 1 agregó throttle de login, regeneración/invalidez de sesión, middleware Spatie y protección de API internas. La matriz es provisional:

| Dominio | ADMISION | RECEPCION | ADMINISTRADOR | COMERCIAL |
|---|---|---|---|---|
| Pacientes, responsables, citas y horarios | lectura/escritura | lectura | lectura | lectura |
| Caja, ventas, movimientos e impresión | no | sí | no | no |
| Usuarios, roles, permisos y maestros | no | no | sí | no |

`User::roleUser()` reconoce además `CAJA` y `FACTURACION`, pero estos roles no tienen rutas funcionales propias ni acceso al dashboard provisional. `COMERCIAL` se usa en rutas/sidebar, pero no está contemplado por ese método. No existen Policies ni Gates de dominio.

## 3. Inventario funcional AS-IS

### Identidad y seguridad

- Login/logout por email y contraseña: `routes/web.php`, `AuthController`.
- Usuarios: alta, edición, cambio de contraseña y asignación de un rol: `UserController`.
- Roles/permisos: listado de roles, creación de permisos, sincronización y eliminación de roles: `RoleController`.
- No se encontró recuperación de contraseña, verificación de correo operativa, suspensión de usuario, segundo factor ni auditoría de sesiones.
- No existen seeders de roles/permisos; el despliegue depende de datos preexistentes.

### Pacientes y responsables

- Alta/actualización/inactivación y listado mensual de pacientes.
- Búsqueda local por documento; si no existe, consulta DNI externa.
- Número de historia clínica calculado como “último paciente + 1”.
- Responsable opcional creado con `updateOrCreate` por `patient_id`, aunque el modelo declara `hasMany`.
- Catálogos de canal y medio de interacción asociados al paciente.
- Geografía (`departments`, `provinces`, `districts`) existe en esquema/modelos, pero no se encontró un flujo de uso activo ni rutas en `api.php`.

### Profesionales, especialidades, servicios y tarifas

- CRUD administrativo de especialidades, médicos, servicios, tarifas adicionales, canales y medios.
- `Doctor` contiene nombre, especialidad, CMP y RNE, sin vínculo con usuario o trabajador.
- `DoctorService` asocia médico-servicio, precios de primera consulta/reconsulta y días de reconsulta.
- No existe entidad de personal/trabajador, contratos, sedes, consultorios o disponibilidad por recurso físico.
- `services` representa servicios clínicos; `items` vuelve a representar productos y servicios vendibles.

### Agenda, disponibilidad y citas

- Horarios médicos por fecha, rango horario y duración de slot.
- El esquema admite `dia_semana` y `fecha_cita` nullable; el CRUD actual fuerza fecha concreta y `dia_semana = 1`.
- Calendario de citas y generación en memoria de slots disponibles.
- Alta de cita con paciente, profesional, servicio, tarifa, fecha/hora, precio y posible adelanto.
- Estados declarados: `PROGRAMADO`, `CONFIRMADO`, `EN_ESPERA`, `LLAMANDO`, `EN_ATENCION`, `ATENDIDO`, `CANCELADO`, `NO_ASISTIO`.
- El código consulta `REEVALUACION`, pero ese valor no existe en el enum de la migration.
- Reconsulta se calcula como tarifa por ventana de días; no existe una entidad ni ciclo de vida independiente.

### Llamador y atención

- `appointments` contiene `hora_llegada`, `hora_llamado`, `hora_atencion` y `hora_atendido`, además de estados compatibles con espera/llamado/atención.
- Existe `caller/visor/VisorController.php`, pero está vacío y no tiene ruta.
- No se encontraron endpoints que actualicen esas cuatro marcas de tiempo, pantalla de visor, eventos/broadcasting ni integración demostrable con el repositorio del llamador.
- Estado: solo preparación de datos. La dependencia real con `LLAMADOR-PACIENTE-CEO` debe verificarse fuera de este repositorio.

### Caja, ventas, comprobantes y pagos

- Apertura/cierre de caja física por usuario mediante `CashierShifts`.
- Ingresos/egresos manuales mediante `CashMovements`.
- Venta de filas provenientes de `items`, `services` o `appointments` mediante `Sales`.
- Creación transaccional de `vouchers`, `voucher_items` y `payments`.
- Correlativo protegido con transacción y `lockForUpdate()` sobre `voucher_series`.
- Adelanto en cita crea un voucher `TICKET`, una línea polimórfica hacia la cita y un pago.
- Una venta posterior puede crear BOLETA/FACTURA desde el ticket y enlazarla con `parent_voucher_id`.
- Impresión HTML en `SaleController::show` y `receptionist/sale/imprimir.blade.php`; la identidad visible de la empresa está codificada directamente en la plantilla.
- No existe CRUD administrativo de cajas, series o ítems; dependen de datos/seeders o manipulación externa.

### Catálogo e inventario

- Tabla/modelo `items` con producto/servicio, categoría, precios, afectación IGV, códigos SUNAT y stock.
- `ItemsSeeder` contiene 570 filas importadas: 237 productos y 333 servicios; las 570 tienen `stock_actual = null` y 66 no son vendibles.
- `DatabaseSeeder` no llama a `ItemsSeeder`.
- La venta descuenta stock únicamente cuando `tipo = PRODUCTO`, sin impedir saldo negativo ni registrar kardex.
- No existen almacenes, lotes, vencimientos, compras, proveedores, transferencias, ajustes ni conteos.

### Integraciones y comunicaciones

- DNI/RENIEC: `ReniecService` consume AQPFACT mediante HTTP Bearer.
- RUC: `SunatService` consume el endpoint RUC de AQPFACT. El nombre induce a confundir consulta tributaria con emisión electrónica.
- OCR: Tesseract.js por CDN procesa en navegador una imagen de operación y completa `numero_operacion`.
- Correo: existe `MailAppointment` y plantilla; el envío está comentado en el alta de cita. El remitente está codificado en la clase.
- SMS: quedaron archivos de configuración para HTTPSMS y TextBee, pero el endpoint de prueba inseguro fue retirado en Fase 1. No hay flujo SMS activo.
- SUNAT: hay campos preparatorios en items/vouchers y estados SUNAT. No existen generación XML/UBL, firma, envío, ticket/CDR, reintentos, consulta de estado, notas integradas ni certificados.
- Frontend: FullCalendar, Flatpickr y Tesseract se cargan desde CDN en varias vistas.

### Dashboard, reportes, auditoría y operación

- Dashboard: listas de citas/reevaluaciones del día y ocupación; no contiene indicadores consolidados de ERP.
- Reportes: no se encontraron módulos de reportes financieros, clínicos, inventario o gerencia.
- Auditoría: solo timestamps estándar. No existen bitácora de cambios, actor/motivo de anulaciones ni historial de estados.
- Logging: Handler estándar. No hay contexto/correlación de negocio; en Fase 1 se eliminó logging de PII de RENIEC.
- Scheduler: `Console\Kernel::schedule()` vacío.
- Queue: infraestructura Laravel y `failed_jobs`, pero sin Jobs propios ni `ShouldQueue` aplicado al correo.
- Cron esperado: ninguno está definido en código. Recordatorios, envío SUNAT y reintentos solo podrían incorporarse tras definir reglas.

## 4. Flujos actuales

### 4.1 Paciente

1. El usuario abre `/admissionist/patient` o una vista equivalente por rol.
2. El JS busca el documento en `/api/patient/show`.
3. Si no hay registro local, `ReniecService::consultar()` solicita datos externos.
4. `PatientController::store()` valida parcialmente el formulario.
5. Busca el mayor `id`, toma su `historia_clinica` y suma uno.
6. Si el documento ya existe, el mismo método actualiza el paciente; si no, lo crea.
7. Si se marcó acompañante, hace `Responsible::updateOrCreate(['patient_id' => ...])`.

Riesgos: carrera en historia clínica, alta y actualización mezcladas, PII devuelta completa, responsable singular de facto frente a relación plural y ausencia de transacción paciente/responsable.

### 4.2 Cita, precio y adelanto

1. Se seleccionan paciente, especialidad y médico.
2. `/api/appointment/service/doctor` devuelve filas `doctor_services`.
3. `/api/appointment/calculated` intenta decidir primera consulta/reconsulta y suma tarifa adicional.
4. `/api/appointment/schedule/available-hours` devuelve horario y citas ocupadas.
5. El navegador envía también precio, pagado y saldo.
6. `AppointmentController::store()` genera `CIT-` + timestamp, busca horario y toma su duración.
7. Crea la cita dentro de una transacción.
8. Si hay adelanto, exige turno abierto, bloquea serie TICKET, crea voucher/item/pago.
9. El envío de correo permanece comentado.

Riesgos: el servidor confía en importes enviados por cliente, IDs no usan reglas `exists`, se puede desreferenciar horario/doctor-service inexistente, el número de cita puede colisionar, la detección de reconsulta compara `appointments.service_id` con el id de `doctor_services`, no hay reserva atómica del slot y la cita mantiene una segunda fuente de verdad financiera.

### 4.3 Atención y llamador

Flujo sugerido por enum/campos: `PROGRAMADO → EN_ESPERA → LLAMANDO → EN_ATENCION → ATENDIDO`, con marcas de llegada, llamado, inicio y fin. **No se encontró código que ejecute esta transición completa.** Tampoco se encontró vista del llamador. Es una intención de esquema, no un flujo implementado.

### 4.4 Venta

1. RECEPCION abre turno.
2. Busca paciente que se atiende y, opcionalmente, quien paga/RUC.
3. Busca ítems y servicios, o agrega una cita/ticket pendiente.
4. El estado público de Livewire mantiene descripción, precio, cantidad, impuestos y comisión de cada línea.
5. El componente calcula base/IGV/total y recibe pagos por efectivo, tarjeta, Yape o Plin.
6. En transacción bloquea una serie, crea voucher, líneas y pagos; descuenta stock de productos.
7. Si liquida ticket, marca el padre `PAGADO` y crea un documento hijo.
8. Emite evento de navegador para imprimir el voucher.

Riesgos: datos económicos de línea no se reobtienen del servidor al guardar, stock sin lock ni límite, validación de pago duplicada, posible registro del efectivo entregado incluyendo vuelto, liquidación de ticket con total/documento hijo ambiguo, consultas por cita con N+1 y ausencia de actualización coherente de los campos financieros de `appointments`.

### 4.5 Caja

1. Lista cajas activas sin turno abierto.
2. Verifica turno de usuario y ocupación de caja con dos consultas `exists()`.
3. Crea turno abierto con monto inicial.
4. Ventas/pagos y movimientos se asocian al turno.
5. Calcula efectivo teórico: apertura + pagos en efectivo + ingresos - egresos.
6. Cierre guarda contado, sistema, diferencia y observación.

Riesgos: apertura no está protegida por transacción/constraint contra concurrencia; después de cerrar consulta `estado = 'activo'` en minúscula; movimientos de un turno abierto pueden editarse/eliminarse sin bitácora; no hay arqueo por medio de pago ni reapertura/anulación formal.

### 4.6 Documento e “integración SUNAT”

1. BOLETA/FACTURA se almacena con serie, correlativo, cliente, totales y `estado_sunat = PENDIENTE`.
2. Se genera una representación HTML imprimible con etiquetas de “electrónica”.
3. El flujo termina allí.

No se genera, firma ni envía un comprobante electrónico. La impresión actual **no equivale a facturación electrónica** y las etiquetas de la vista pueden inducir a error operativo.

## 5. Modelo de datos: 32 tablas actuales

### Identidad y seguridad

| Tabla | Propósito/relaciones | Deuda o duplicación relevante |
|---|---|---|
| `users` | Credencial y actor que registra pacientes, citas, turnos, vouchers, pagos y movimientos. | No representa persona/trabajador; sin estado, sede ni datos laborales. |
| `password_resets` | Infraestructura Laravel. | No hay rutas funcionales de recuperación. |
| `personal_access_tokens` | Tokens Sanctum. | No se encontró emisión/uso propio. |
| `roles` | Roles Spatie. | Nombres dependen de datos productivos; sin seeder. |
| `permissions` | Permisos Spatie. | Se administran, pero las rutas operativas usan roles, no permisos finos. |
| `model_has_roles` | Asignación usuario-rol. | La UI sincroniza un solo rol aunque Spatie admite varios. |
| `model_has_permissions` | Permisos directos a modelos. | No se encontró uso funcional. |
| `role_has_permissions` | Matriz rol-permiso. | No gobierna aún la mayoría de endpoints. |

### Pacientes, captación y geografía

| Tabla | Propósito/relaciones | Deuda o duplicación relevante |
|---|---|---|
| `patients` | Datos personales, historia, canal, medio y usuario registrador. | `historia_clinica_nueva` sin uso; historia correlativa insegura; mezcla identidad, contacto y captación. |
| `responsibles` | Responsable vinculado a paciente. | Modelo plural pero alta sobrescribe por paciente; sin unicidad, vigencia ni rol legal. |
| `channels` | Canal/origen comercial. | Relación `Channel::appointments()` no coincide con FK real en `patients`. |
| `interaction_media` | Medio de interacción/captación. | Relación del modelo apunta a citas sin columna correspondiente. |
| `departments` | Catálogo geográfico. | Sin relaciones Eloquent ni flujo activo demostrado. |
| `provinces` | Provincia de un departamento. | Sin relación Eloquent definida. |
| `districts` | Distrito con provincia y departamento. | Denormaliza `department_id`; sin uso activo demostrado. |

### Personal médico y catálogo clínico

| Tabla | Propósito/relaciones | Deuda o duplicación relevante |
|---|---|---|
| `specialties` | Especialidades de médicos y servicios. | Nombres no únicos; cascadas físicas desde maestros. |
| `doctors` | Profesional con especialidad, CMP/RNE. | No es trabajador/persona/usuario; una sola especialidad directa. |
| `services` | Servicio clínico de una especialidad. | Se solapa con `items.tipo = SERVICIO`; sin código fiscal propio. |
| `doctor_services` | Oferta, precio y regla de reconsulta por médico-servicio. | Sin FKs ni unique en migration; precios/regla acoplados en la misma relación. |
| `additional_rates` | Ajuste fijo/porcentaje por vigencia. | `tipo_tarifa` es texto libre; alta no asigna `estado` aunque la columna no tiene default. |

### Agenda y atención

| Tabla | Propósito/relaciones | Deuda o duplicación relevante |
|---|---|---|
| `doctor_schedules` | Ventana de atención por médico, día/fecha y duración. | Mezcla plantilla semanal y excepción fechada; CRUD fuerza lunes/fecha; sin exclusión de solapes. |
| `appointments` | Cita, agenda, estado de atención, marcas del llamador y campos financieros temporales. | Demasiadas responsabilidades; no existe `REEVALUACION` en enum; no hay vínculo directo formal con voucher; adelanto se duplica en `payments`. |

### Comercial, caja y documentos

| Tabla | Propósito/relaciones | Deuda o duplicación relevante |
|---|---|---|
| `items` | Catálogo vendible de productos y servicios, precio, IGV y stock. | Se solapa con `services`; inventario es solo un saldo nullable. |
| `cashiers` | Caja física. | Sin CRUD, sede o punto de venta. |
| `cashier_shifts` | Turno por caja/usuario y arqueo. | Sin constraint de un único abierto por caja/usuario ni historial de reapertura. |
| `cash_movements` | Ingreso/egreso manual del turno. | Editable/eliminable; sin motivo estructurado, aprobación ni reverso. |
| `voucher_series` | Serie/correlativo por tipo y caja opcional. | Selección toma la primera serie activa sin filtrar por caja/sede. |
| `vouchers` | Documento interno/fiscal, cliente, totales, estado, padre y campos SUNAT. | Mezcla ticket interno, venta, documento fiscal y notas; cascada desde turno; campos SUNAT sin proceso. |
| `voucher_items` | Snapshot de línea y referencia polimórfica a cita/servicio/item. | Polimorfismo sin integridad FK para origen; precio/comisión confiados al estado Livewire. |
| `payments` | Aplicación de cobro a voucher y turno. | Sin moneda, estado, reverso, fecha efectiva ni conciliación; pagos de cita se duplican en appointment. |

### Infraestructura

| Tabla | Propósito/relaciones | Deuda o duplicación relevante |
|---|---|---|
| `failed_jobs` | Fallos de queue de Laravel. | No existen Jobs propios ni worker definido. |
| `migrations` | Ledger técnico de migrations de Laravel. | Explica la tabla 32; no es tabla de negocio. |

## 6. Inconsistencias y riesgos transversales

1. **Doble fuente financiera:** `appointments.total_pagado/saldo_pendiente/metodo_pago` frente a `vouchers/payments`.
2. **Agenda no atómica:** no hay bloqueo/constraint que evite doble reserva del mismo slot.
3. **Identidades mezcladas:** usuario, médico, paciente y pagador carecen de una raíz común de persona; no existe trabajador.
4. **Catálogo duplicado:** `services` y `items` contienen servicios comercializables con precios/impuestos distintos.
5. **Estados incompatibles:** código usa `REEVALUACION`, esquema no; llegada/llamado/atención no tienen comandos.
6. **Datos del cliente como fuente:** cita y venta reciben/importan valores económicos manipulables desde navegador.
7. **Correlativos parcialmente sólidos:** hay `lockForUpdate()`, pero la selección de serie y el comportamiento concurrente solo se probaron en SQLite, no MySQL aislado.
8. **Cascadas peligrosas:** eliminar físicamente usuario/paciente/maestros podría eliminar historia operativa; no hay soft delete general.
9. **Validación irregular:** abundan IDs sin `exists`, enums como texto libre y `find()` seguido de dereferencia.
10. **UI reutilizada contra permisos:** ADMINISTRADOR/RECEPCION/COMERCIAL ven formularios de escritura de ADMISION que reciben 403.
11. **Rutas duplicadas:** archivos cargados dos veces; aumenta riesgo de nombres/orden/middleware divergentes.
12. **Consultas costosas:** listados sin paginación y consultas por elemento en búsqueda de citas/tickets; accessors de voucher ejecutan sumas.
13. **Código temporal:** `dd()` comentados, comentarios de “cron”, variables de prueba, controlador vacío y chat/template de tema comentado.
14. **Errores silenciosos/500:** no hay manejo homogéneo de proveedor HTTP, `firstOrFail()` o ausencia de series/horarios.
15. **Ausencia de auditoría:** cambios sensibles y borrados lógicos no registran actor, causa, antes/después.

## 7. Piezas técnicamente aprovechables, con reservas

- Laravel/Eloquent/Blade/Livewire ofrecen una base conocida para evolución incremental.
- Spatie Permission es reutilizable, pero la matriz definitiva debe definirse antes de modelar permisos finos.
- Relaciones centrales paciente-cita-médico-servicio y turno-voucher-pago expresan parte del dominio real.
- `lockForUpdate()` y unique de voucher aportan una base para correlativos, pendiente de alcance por sede/caja/tipo y prueba MySQL.
- El snapshot de `voucher_items` es un patrón útil para preservar descripción/precio histórico, aunque su origen polimórfico requiere reglas.
- Los catálogos actuales y las 570 filas del seeder son material de migración/depuración, no verdad maestra confirmada.
- La suite aislada protege el comportamiento conocido y la contención de seguridad; aún no cubre reglas complejas, concurrencia ni navegador.

## 8. Funcionalidad no demostrada

Se considera ausente o solo preparada: personal/RRHH, sedes, consultorios, historia clínica asistencial, laboratorio operativo, farmacia regulada, compras, proveedores, almacenes/kardex, cuentas por cobrar/pagar, conciliación, auditoría, reportes gerenciales, notificaciones activas, llamador ejecutable y facturación electrónica SUNAT.

Que un módulo sea típico de salud no confirma que CEO Salud lo necesite. Todos pasan a **CANDIDATOS A EVALUAR** en el Blueprint TO-BE.

## 9. Verificaciones pendientes fuera del código

- Reglas reales de atención, pago, reconsulta, anulaciones y cierres.
- Asignaciones de roles y permisos efectivas en producción.
- Contenido/calidad de las 32 tablas productivas y diferencias respecto de migrations, sin copiar PII.
- Series/correlativos por caja, sede y tipo; concurrencia en MySQL aislado.
- Contrato con `LLAMADOR-PACIENTE-CEO` y necesidad real de CORS/API.
- Validez fiscal de reglas IGV, detracciones, notas y documentos requeridos.
- Proveedores vigentes de DNI/RUC/SMS/correo y sus sandboxes/SLAs.
- Procesos de Hostinger necesarios para queue worker y cron, si el TO-BE los adopta.
