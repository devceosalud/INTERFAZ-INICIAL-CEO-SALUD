# Matriz de módulos ERP CEO Salud

## Escala

- **A — Reutilizable casi completo:** cubre el propósito principal; necesita endurecimiento menor y validación real.
- **B — Reutilizable con correcciones:** base útil, pero tiene defectos/acoplamientos concretos.
- **C — Requiere rediseño importante:** corregir aisladamente no resuelve límites o fuentes de verdad.
- **D — Implementación parcial:** solo una parte del flujo está codificada.
- **E — Solo estructura/preparación:** esquema, configuración o clase sin flujo completo.
- **F — No existe:** no se encontró implementación.

La clasificación es técnica y provisional; no decide qué producto debe construir CEO Salud.

## Matriz ejecutiva

| Módulo | Estado | Motivo principal |
|---|---:|---|
| Autenticación/sesión | B | Login funcional y endurecido; faltan recuperación, suspensión, verificación y MFA. |
| Usuarios | B | CRUD y contraseña; `users` no representa trabajador ni ciclo laboral. |
| Roles/permisos | B | Spatie y contención backend; matriz provisional y permisos finos sin uso consistente. |
| Pacientes | B | CRUD/búsqueda aprovechables; correlativo de historia y límites de identidad requieren corrección. |
| Responsables | D | CRUD parcial; cardinalidad e identidad legal no están definidas. |
| Personal/trabajadores | F | No existe entidad ni flujo. |
| Médicos/profesionales | C | Maestro básico sin persona/trabajador/usuario ni múltiples especialidades/sedes. |
| Especialidades | B | Maestro simple reutilizable, pendiente de unicidad y reglas de baja. |
| Servicios clínicos | C | Se solapa con `items` y mezcla catálogo clínico/comercial. |
| Tarifas médico-servicio | C | Precio/reconsulta útiles, pero sin FKs/unique y con regla acoplada. |
| Tarifas adicionales | B | Ajustes básicos; tipo/estado/vigencia no están bien restringidos. |
| Horarios médicos | C | CRUD por fecha; modelo mezcla recurrencia/excepción y no impide solapes. |
| Disponibilidad/slots | C | Se calcula al vuelo; contratos diferentes y sin reserva atómica. |
| Agenda/calendario | B | Visualización y filtros existen; depende de estados/slots inconsistentes. |
| Citas | C | Flujo sustancial, pero mezcla agenda, cobro, ticket y atención. |
| Estados de cita/atención | C | Enum y código divergen; no hay máquina de estados ni auditoría. |
| Reconsultas/reevaluaciones | D | Tarifa calculada y consultas a estado inexistente; sin ciclo propio. |
| Llamador de pacientes | E | Campos y controlador vacío; sin rutas, eventos ni visor. |
| Apertura/cierre de caja | C | Flujo base; carreras, alcance de caja y arqueo incompletos. |
| Movimientos de caja | B | CRUD por turno; falta reverso/aprobación/auditoría. |
| Ventas | C | Flujo transaccional sustancial; fuente económica manipulable y límites confusos. |
| Ítems/productos | D | Modelo y catálogo importado; no hay mantenimiento funcional. |
| Stock/inventario | D | Solo saldo y decremento; no existe kardex ni control de disponibilidad. |
| Vouchers/comprobantes | C | Estructura amplia; mezcla ticket, venta, documento fiscal y notas. |
| Voucher items | B | Snapshot y vínculo polimórfico útiles; integridad/origen y cálculo requieren corrección. |
| Pagos | C | Registra múltiples medios; sin reversos, conciliación ni fuente única con citas. |
| Series/correlativos | B | Lock y unique son base útil; alcance y concurrencia MySQL pendientes. |
| Impresión | D | HTML imprimible; no garantiza formato ni validez fiscal. |
| DNI/RENIEC | B | Adaptador y consumo activos; faltan timeouts, errores, contrato y sandbox. |
| RUC | B | Consulta activa; nombre `SunatService` confunde consulta con emisión. |
| OCR de operación | D | Extracción en navegador; sin evidencia persistida ni validación. |
| SMS | E | Configuración residual; no hay flujo activo. |
| Correo | D | Mailable y vista; envío comentado y no encolado. |
| SUNAT/facturación electrónica | E | Solo campos/estados y etiquetas de impresión; no hay emisión. |
| Dashboard | D | Agenda diaria; no es dashboard gerencial. |
| Reportes | F | No se encontraron reportes de negocio. |
| Auditoría | F | No existe log funcional de cambios/eventos. |
| Geografía | E | Tablas/modelos sin uso activo demostrado. |
| API interna | B | Protegida en Fase 1; contratos/validación/versionado son débiles. |
| Scheduler/queue/cron | E | Infraestructura Laravel, sin tareas ni Jobs de aplicación. |
| Tests automatizados | B | Baseline y seguridad útiles; falta cobertura de reglas, MySQL, concurrencia y navegador. |

## Fichas por módulo

### 1. Identidad, autenticación y acceso

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Autenticar usuarios internos y restringir áreas por rol. |
| Rutas | `/`, `POST /admin/SingIn`, `POST /admin/logout`, `/dashboard`; administración bajo `/admin/user*`, `/admin/role*`, `/admin/permisos*`. |
| Controladores | `authenticator/auth/AuthController`, `admin/master/user/UserController`, `admin/master/role/RoleController`. |
| Livewire | No aplica. |
| Modelos/tablas | `User`; `users`, cinco tablas Spatie, `password_resets`, `personal_access_tokens`. |
| Vistas/JS | `authenticator/index.blade.php`, `admin/user/*`, `admin/role/*`, `public/js/admin/master/user/user.js`, sidebar por rol. |
| Relaciones | Usuario registra pacientes/citas y opera turnos/vouchers/pagos/movimientos. |
| Integraciones | Spatie Permission, Sanctum instalado. |
| Estado | Autenticación **B**; usuarios **B**; roles/permisos **B**. La autorización actual es contención provisional, no diseño final. |

### 2. Pacientes, responsables y captación

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Registrar identidad/contacto del paciente, responsable y origen comercial. |
| Rutas | `/admissionist/patient*`, `/admissionist/responsible*`; vistas equivalentes admin/recepción; `/api/patient/show*`, `/api/admin/responsible/search`. |
| Controladores | `admissionist/patient/PatientController`, `admissionist/responsible/ResponsibleController`, controladores de lectura por rol y API. |
| Livewire | No aplica. |
| Modelos/tablas | `Patient`, `Responsible`, `Channel`, `InteractionMedium`; `patients`, `responsibles`, `channels`, `interaction_media`. |
| Vistas/JS | `admissionist/patient/*`, `admissionist/rersponsible/*`, vistas wrapper de admin/recepción; `patient.js`, `responsible.js`. |
| Relaciones | Paciente → citas, responsables, vouchers como atendido/pagador; canal/medio → paciente. |
| Integraciones | RENIEC/DNI; Tesseract para comprobante de operación dentro de formularios de cita. |
| Estado | Pacientes **B**; responsables **D**; captación **B**. Historia clínica `último + 1`, alta/actualización mezcladas y cardinalidad del responsable pendientes. |

### 3. Geografía

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Catálogo de ubigeo peruano. |
| Rutas/controladores | Existe `Api/geographic/GeographicController`, pero está vacío y no hay rutas registradas. |
| Modelos/tablas | `Department`, `Province`, `District`; tablas homónimas en inglés. |
| Vistas/JS | No se encontró consumidor activo. |
| Relaciones | FKs provincia→departamento y distrito→provincia/departamento. |
| Integraciones | Ninguna. |
| Estado | **E**. Preparación sin flujo. |

### 4. Personal y profesionales

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Mantener médicos y credenciales profesionales. No existe objetivo codificado para personal general. |
| Rutas | `/master/admin/doctor*`, `/master/admin/doctor/services*`. |
| Controladores | `DoctorController`, `DoctorServiceController`. |
| Livewire | No aplica. |
| Modelos/tablas | `Doctor`, `Specialty`, `Service`, `DoctorService`; `doctors`, `specialties`, `services`, `doctor_services`. |
| Vistas/JS | `admin/master/doctor/*`, `doctor-service/*`, `doctor.js`, `doctor-service.js`. |
| Relaciones | Doctor → especialidad, citas, horarios, servicios, líneas de voucher. |
| Integraciones | Ninguna. |
| Estado | Personal **F**; médicos **C**; relación médico-servicio **C**. Médico no se vincula a persona, trabajador o usuario. |

### 5. Especialidades, servicios y tarifas

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Catálogos clínicos y formación del precio de consulta. |
| Rutas | `/master/admin/specialty*`, `/service*`, `/additional-rate*`, API de médico/servicio/precio. |
| Controladores | `SpecialtyController`, `ServiceController`, `AdditionalRateController`, `Api/appointment/AppointmentController`. |
| Modelos/tablas | `Specialty`, `Service`, `DoctorService`, `AdditionalRate`. |
| Vistas/JS | Maestros Blade/JS y formularios de cita. |
| Relaciones | Especialidad → médicos/servicios; médico-servicio → precio y ventana de reconsulta; tarifa adicional → cita. |
| Integraciones | Ninguna. |
| Estado | Especialidades **B**; servicios **C**; tarifas médico-servicio **C**; adicionales **B**. `services` compite con `items` y `AdditionalRate::store` no fija estado. |

### 6. Horarios, disponibilidad y agenda

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Definir jornadas médicas, calcular huecos y mostrarlos en calendario. |
| Rutas | `/admissionist/doctor-schedule*`, `/reservation/list-calendar`, `/available-schedule`; APIs `/appointment/schedule/available-hours` y `/doctor-schedule/search`. |
| Controladores | `admissionist/schedule/ScheduleController`, `Api/doctorSchedule/DoctorScheduleController`, wrappers admin/recepción y `AvailableSchedule`. |
| Modelos/tablas | `DoctorSchedule`, `Appointment`; `doctor_schedules`, `appointments`. |
| Vistas/JS | `schedule/*`, `available-schedule/*`, componentes `calendar`, `schedules`, `availableSchedule`; FullCalendar/Flatpickr y JS de calendario. |
| Relaciones | Depende de doctor, duración de cita, citas no canceladas y estado. |
| Integraciones | FullCalendar/Flatpickr por CDN. |
| Estado | Horarios **C**; disponibilidad **C**; agenda visual **B**. Cálculo al vuelo, recurrencia incoherente, sin exceptions/feriados/recursos ni reserva atómica. |

### 7. Citas, atención y reconsultas

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Programar consulta, cobrar adelanto y seguir estado de atención. |
| Rutas | `/admissionist/appointment*`, actualización desde schedule, páginas admin/recepción y APIs de selección/precio. |
| Controladores | `admissionist/appointment/AppointmentController`, `ScheduleController`, APIs de appointment/schedule. |
| Modelos/tablas | `Appointment` con paciente, doctor, service, tarifa y user; tabla `appointments`. |
| Vistas/JS | `appointment/*`, componentes de appointments/reevaluations, `appointment.js`, `editar-cita.js`, `update.js`. |
| Relaciones | Agenda, pacientes, profesionales, tarifa, caja, voucher TICKET y pagos. |
| Integraciones | RENIEC, OCR, correo preparado. |
| Estado | Citas **C**; estados **C**; reconsulta **D**. Precio y saldo llegan del cliente; `REEVALUACION` no existe en enum; atención carece de comandos/auditoría. |

### 8. Llamador de pacientes

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Marcar llegada, llamado, atención y finalización. |
| Rutas/controladores | `caller/visor/VisorController` vacío; sin ruta. |
| Modelos/tablas | Campos de timestamp y enum en `appointments`. |
| Vistas/JS | No encontrados. |
| Relaciones | Cita, médico y eventual aplicación externa. |
| Integraciones | Repositorio externo mencionado, contrato no inspeccionado en esta rama. Broadcasting Laravel configurado pero no usado. |
| Estado | **E**. No se puede afirmar que exista llamador funcional. |

### 9. Caja y movimientos

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Controlar turno físico, efectivo teórico y movimientos manuales. |
| Rutas | `/receptionist/cashier-shift`, `/receptionist/cash-movement`. |
| Controladores/Livewire | Wrappers `CashierShiftController`, `CashMovementController`; `CashierShifts`, `CashMovements`. |
| Modelos/tablas | `Cashier`, `CashierShift`, `CashMovement`; `cashiers`, `cashier_shifts`, `cash_movements`. |
| Vistas/JS | `receptionist/cashier-shift`, `cash-movement`, vistas Livewire. |
| Relaciones | Turno → caja, usuario, vouchers, payments, movements. |
| Integraciones | Ninguna. |
| Estado | Turnos **C**; movimientos **B**. Apertura con race condition y movimientos editables/eliminables sin reverso. |

### 10. Ventas y catálogo vendible

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Cobrar citas, servicios y productos mediante carrito. |
| Rutas | `/receptionist/sales`; acciones Livewire. |
| Controladores/Livewire | `SaleController`; `Sales`. |
| Modelos/tablas | `Item`, `Service`, `Appointment`, `Voucher`, `VoucherItem`, `Payment`; `items` y tablas financieras. |
| Vistas/JS | `receptionist/sale/index`, `livewire/sales`; evento JS de impresión. |
| Relaciones | Requiere turno, serie, paciente/pagador, doctor y origen de línea. |
| Integraciones | Consulta RUC; impresión del navegador. |
| Estado | Ventas **C**; catálogo **D**. Flujo amplio pero acoplado, con estado público como fuente de precio y sin mantenimiento de catálogo. |

### 11. Stock/inventario

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Mantener saldo simple por producto. |
| Rutas/controladores | No existe módulo propio. `Sales::guardarVenta()` decrementa. |
| Modelos/tablas | `Item`; `items.stock_actual`, `stock_minimo`. |
| Vistas/JS | Búsqueda dentro de ventas. |
| Relaciones | Venta/voucher item. |
| Integraciones | Seeder importado desde “smartsystem”. |
| Estado | **D**. No hay kardex, compras, almacenes, lotes, reservas ni control anti-negativo. |

### 12. Vouchers, líneas, pagos, series e impresión

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Representar ticket interno, boleta/factura/notas, detalle, cobros y numeración. |
| Rutas | Impresión `/receptionist/{voucher}/imprimir`; creación desde cita y Livewire Sales. |
| Controladores/Livewire | `AppointmentController::store`, `Sales::guardarVenta`, `SaleController::show`. |
| Modelos/tablas | `Voucher`, `VoucherItem`, `Payment`, `VoucherSerie`; tablas homónimas. |
| Vistas/JS | `sale/imprimir.blade.php`, `livewire/sales.blade.php`. |
| Relaciones | Voucher → turno, usuario, paciente/pagador, padre/hijos, líneas y pagos; línea → origen polimórfico/doctor. |
| Integraciones | Campos SUNAT sin emisor. |
| Estado | Vouchers **C**; líneas **B**; pagos **C**; series **B**; impresión **D**. Impresión rotulada electrónica no equivale a emisión y la identidad visible de la empresa está codificada en la plantilla. |

### 13. DNI, RUC, OCR, SMS y correo

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Autocompletar personas/empresas, capturar operación y notificar cita. |
| Entrada | APIs internas de paciente; `Sales::buscarPorRuc`; JS Tesseract; `MailAppointment`. |
| Servicios/config | `ReniecService`, `SunatService`, `config/apidatosperu.php`, `httpsms.php`, `textbee.php`, `mail.php`. |
| Vistas/JS | Formularios de paciente/venta, `tesseract.js`, `emails/appointment/record`. |
| Relaciones | Paciente, cliente de voucher, pago/cita. |
| Integraciones | AQPFACT; Tesseract CDN; mail Laravel; dos proveedores SMS solo configurados. |
| Estado | DNI **B**, RUC **B**, OCR **D**, SMS **E**, correo **D**. Sin contratos robustos, timeouts/reintentos ni activación segura. |

### 14. SUNAT/facturación electrónica

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Preparar comprobantes fiscales, detracción y estado SUNAT. |
| Rutas/servicios | No hay rutas ni servicio de emisión. `SunatService` es únicamente lookup de RUC. |
| Modelos/tablas | Campos fiscales en `items`, `voucher_items`, `vouchers`, tipos/series. |
| Vistas | Impresión etiqueta boleta/factura como “electrónica”. |
| Relaciones | Venta, cliente, series, líneas, pagos. |
| Integraciones | Ninguna con facturación SUNAT demostrada. |
| Estado | **E**. No hay XML, firma, envío, CDR, consulta, contingencia ni notas funcionales. |

### 15. Dashboard, reportes y auditoría

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Mostrar agenda diaria. |
| Ruta/controlador | `/dashboard`, `DashboardController::index`. |
| Modelos/tablas | Consultas a `appointments`. |
| Vistas/JS | `admin/dashboard/index`, componentes de citas/reevaluaciones, FullCalendar. |
| Relaciones | Agenda y estados. |
| Integraciones | Ninguna. |
| Estado | Dashboard **D**; reportes **F**; auditoría **F**. No hay KPIs ni bitácora. |

### 16. Configuración, scheduler, queue y operación

| Campo | Evidencia AS-IS |
|---|---|
| Objetivo aparente | Configurar conexiones Laravel y futuros efectos asíncronos. |
| Archivos | `config/*`, `Console/Kernel.php`, `routes/console.php`, `failed_jobs`. |
| Jobs/commands | Solo comando `inspire`; no existe `app/Jobs` ni comandos propios. |
| Cron/worker | No definido en repositorio. |
| Integraciones | Mail, HTTP providers, S3/Pusher configurables, pero no todos usados. |
| Estado | Configuración **B**; scheduler/queue/cron **E**. Entorno de tests sí está aislado y bloquea tráfico HTTP no simulado. |

## Deuda técnica transversal

- Responsabilidades de dominio dentro de controladores/Livewire grandes.
- Validación repetida con `Validator`, sin Form Requests ni objetos de comando.
- Nombres y rutas con errores (`SingIn`, `udpate`, `admissionit`, `rersponsible`, `additonal`).
- Estados y códigos como strings distribuidos entre PHP, JS, Blade y enums.
- Cargas sin paginación y JavaScript duplicado/reutilizado entre roles.
- Proveedores externos invocados sin política uniforme de timeout, retry o circuit breaker.
- Ausencia de idempotencia en altas/cobros, salvo unique final de voucher.
- Falta de trazabilidad de anulaciones, cambios de agenda y operaciones financieras.
