# Integraciones críticas — estado actual y dirección TO-BE

## 1. Propósito y criterios

Este documento separa consulta de identidad, consulta tributaria, emisión electrónica y llamador. No ejecuta servicios externos ni valida credenciales productivas.

Niveles de certeza:

- **CONFIRMADO EN CÓDIGO:** observado directamente en archivos del repositorio.
- **CONFIRMADO POR NEGOCIO:** decisión comunicada por Rodrigo.
- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** regla funcional vigente, modificable solo mediante una decisión posterior trazada.
- **PROPUESTA TO-BE:** comportamiento funcional recomendado para preservar la capacidad y reducir riesgo.
- **PENDIENTE DE NEGOCIO:** regla que requiere definición empresarial.
- **PENDIENTE DE VALIDACIÓN PRODUCTIVA:** no puede afirmarse solo con el repositorio.
- **REFERENCIA EXTERNA:** fuente conceptual, no implementación propia.

## 2. RENIEC / consulta de DNI

### 2.1 Estado actual

**Clasificación: IMPLEMENTADO EN CÓDIGO, PENDIENTE DE VALIDACIÓN PRODUCTIVA.**

#### Flujo confirmado en código

1. `public/js/admissionist/patient/patient.js` escucha cambios en el DNI del formulario de creación.
2. Envía `POST /api/patient/show`.
3. `routes/api.php` protege la ruta con `internal-api` y roles ERP.
4. `App\Http\Controllers\Api\patient\PatientController::show()` busca primero por `patients.numero_identidad`.
5. Si no existe localmente, llama `App\Services\ReniecService::consultar()`.
6. El servicio usa HTTP Bearer con URL/token obtenidos de `config/apidatosperu.php`.
7. La respuesta normalizada se devuelve al formulario para autocompletar; el guardado del paciente ocurre después en un flujo distinto.

La misma ruta se invoca desde `public/js/admissionist/appointment/appointment.js`, pero ese flujo solo utiliza explícitamente el resultado cuando el paciente ya existe localmente. Una respuesta `encontrado_reniec` no crea al paciente ni completa por sí sola una cita.

#### Datos devueltos por el adaptador

- nombres;
- apellido paterno y materno;
- fecha de nacimiento normalizada;
- género normalizado cuando coincide con los valores contemplados;
- estado civil;
- dirección;
- número de identidad;
- tipo de identificación DNI;
- campos locales vacíos para ocupación, instrucción, teléfono, correo, canal y medio de interacción.

#### Configuración y dependencias

- `config/apidatosperu.php`: claves de entorno para URL DNI, URL RUC y token del proveedor AQPFact; también conserva configuración alternativa de APIsPeru para RUC.
- `ReniecService`: facade HTTP de Laravel y Carbon.
- No se identificó un secreto literal activo dentro del servicio.

#### Manejo de errores y riesgos confirmados

- Devuelve `null` cuando la respuesta HTTP no es exitosa o `success` es falso.
- El controlador traduce ese resultado a `404` con “no encontrado”.
- **CONFIRMADO EN CÓDIGO:** no hay validación explícita de formato/longitud de DNI en `PatientController::show()` antes de consultar.
- **CONFIRMADO EN CÓDIGO:** no hay timeout, reintento controlado ni captura de excepciones de transporte.
- **CONFIRMADO EN CÓDIGO:** el mapeo accede directamente a campos y convierte la fecha; una respuesta incompleta o inesperada puede provocar error.
- **CONFIRMADO EN CÓDIGO:** el JavaScript consulta al evento `input` sin longitud mínima ni debounce, por lo que puede generar solicitudes innecesarias.
- **CONFIRMADO EN CÓDIGO:** el JavaScript escribe respuestas con datos personales en la consola del navegador.
- El formulario de creación sigue siendo editable, por lo que existe una vía operativa de ingreso manual; debe preservarse y aclararse ante fallos.

#### Tests encontrados

- `tests/Feature/Baseline/AccessAndApiSmokeTest.php`: intercepta con `Http::fake()` una consulta fallida y comprueba el comportamiento 404 sin salida real.
- `tests/Feature/Security/ExternalEffectsAndLoggingTest.php`: comprueba normalización básica de una respuesta simulada, ausencia de logs `info/debug` desde el servicio y ausencia de patrones de credenciales literales.
- `tests/Feature/Security/InternalApiAuthorizationTest.php`: comprueba que un visitante no acceda a la ruta.
- No se encontraron pruebas de timeout, excepción de red, payload incompleto, formato inválido, límites del proveedor ni flujo manual visible al usuario.

### 2.2 Dirección TO-BE

- **CONFIRMADO POR NEGOCIO:** conservar la entrada de DNI y recuperación de datos para agilizar el registro.
- **PROPUESTA TO-BE:** validar el DNI antes de consultar y diferenciar “no encontrado”, “proveedor no disponible” y “respuesta inválida”.
- **PROPUESTA TO-BE:** limitar solicitudes, controlar timeout/reintento y evitar consultas por cada pulsación.
- **PROPUESTA TO-BE:** mostrar los datos recuperados para revisión humana antes de guardarlos.
- **PROPUESTA TO-BE:** no registrar respuestas completas ni datos personales innecesarios en logs o consola.
- **PROPUESTA TO-BE:** mantener siempre ingreso manual controlado.

### 2.3 Pendientes

- **PENDIENTE DE VALIDACIÓN PRODUCTIVA:** proveedor realmente utilizado, disponibilidad, formato vigente, límites, calidad de datos y funcionamiento con la configuración de producción.
- **PENDIENTE DE NEGOCIO:** política ante discrepancias entre dato existente, dato declarado y dato devuelto por el proveedor.
- **PENDIENTE DE NEGOCIO:** qué campos externos pueden actualizar un paciente ya registrado.

## 3. Consulta de RUC

### 3.1 Estado actual

**Clasificación: IMPLEMENTADO EN CÓDIGO, PENDIENTE DE VALIDACIÓN PRODUCTIVA.**

- `App\Services\SunatService` consulta información de RUC; su nombre no implica emisión electrónica.
- `App\Http\Livewire\Sales::buscarPorRuc()` lo usa desde la venta cuando se selecciona FACTURA.
- Valida únicamente que la cadena tenga 11 caracteres.
- Completa tipo de documento `6`, RUC, razón social y dirección del cliente.
- Advierte si el estado no es ACTIVO, pero continúa cargando los datos.
- El adaptador también normaliza nombre comercial, estado, condición, ubicación y banderas de agente de retención/buen contribuyente, aunque el flujo mostrado no utiliza todos esos campos.
- La configuración activa usa URL/token AQPFact; permanece comentado un adaptador anterior de APIsPeru.

Riesgos observados:

- no se valida que los 11 caracteres sean numéricos ni la validez del RUC;
- no hay timeout, reintento ni captura de excepciones de transporte;
- se accede directamente a campos del payload;
- no se diferencia indisponibilidad de “empresa no encontrada”;
- no se encontraron tests específicos de `SunatService` o `buscarPorRuc()`.

### 3.2 Dirección TO-BE

- Mantener consulta de RUC como servicio independiente de la facturación electrónica.
- Validar entrada y respuesta, permitir ingreso/revisión manual y conservar solo los datos necesarios.
- No bloquear indebidamente ventas por indisponibilidad del proveedor; las reglas para contribuyentes no activos/no habidos deben validarse con contabilidad.
- Aislar el proveedor para poder sustituirlo sin cambiar ventas ni documentos tributarios.

### 3.3 Pendientes

- **PENDIENTE DE VALIDACIÓN PRODUCTIVA:** proveedor activo, credenciales, esquema real, límites, disponibilidad y exactitud.
- **PENDIENTE DE NEGOCIO:** tratamiento de estado/condición no válidos y campos que pueden editarse manualmente.

## 4. Comprobante interno y preparación tributaria

### 4.1 Implementado

- Venta transaccional en `App\Http\Livewire\Sales::guardarVenta()`.
- `vouchers` con tipos FACTURA, BOLETA, TICKET, NOTA_CREDITO y NOTA_DEBITO.
- `voucher_items` con descripción, cantidad, precio histórico, afectación/IGV, código y unidad SUNAT.
- `payments` vinculados al voucher y turno de caja.
- `voucher_series` con serie y correlativo; incremento bajo transacción y `lockForUpdate()` en ventas.
- Relación de documento padre y sustento preparatorio para notas.
- Representación HTML imprimible mediante `SaleController::show()` y `resources/views/receptionist/sale/imprimir.blade.php`.
- Smoke test de venta, línea, pago, stock y correlativo en `CashierSalesAndPaymentsSmokeTest`.

Esto constituye **generación de comprobante interno**, no demuestra emisión tributaria electrónica.

### 4.2 Preparado parcialmente

- `vouchers.requiere_sunat`.
- Estados `NO_APLICA`, `PENDIENTE`, `ENVIADO`, `ACEPTADO`, `RECHAZADO`, `OBSERVADO`.
- Campos para hash, respuesta, ruta XML, ruta CDR y fecha de envío.
- Totales gravado, exonerado e inafecto; IGV y detracción.
- Tipos de nota y relación con documento padre.
- Series/correlativos y datos fiscales básicos por línea.

Estos campos muestran intención, pero no una integración operativa.

### 4.3 No implementado

- generación UBL/XML tributario;
- firma digital;
- envío a SUNAT u OSE;
- autenticación fiscal;
- recepción, validación y conservación efectiva del CDR;
- consulta de estado, reintentos e idempotencia de envío;
- comunicación de baja/anulación y ciclo completo de notas;
- contingencia;
- jobs/colas específicos y monitoreo de emisión;
- proveedor, OSE o librería de emisión;
- pruebas de emisión electrónica.

No se encontró dependencia especializada de facturación electrónica en `composer.json`.

### 4.4 Riesgo documental actual

**CONFIRMADO EN CÓDIGO:** la vista imprimible denomina “BOLETA ELECTRÓNICA” o “FACTURA ELECTRÓNICA” al documento y afirma que puede verificarse en SUNAT, aunque la emisión no existe. Un comentario de la misma vista reconoce que la conexión y el QR real están pendientes.

Otros riesgos que deben preservarse como hallazgos para una fase de corrección:

- la identidad, dirección y contacto del emisor están escritos directamente en la vista de impresión;
- la migración define `observaciones`, pero la vista consulta `observacion`, por lo que esa información no tiene un contrato consistente;
- la migración de pagos define entidad de origen/destino y `Sales` intenta registrarlas, pero `Payment::$fillable` no incluye esos campos;
- no se encontraron pruebas que demuestren notas de crédito/débito, estados SUNAT, impresión fiscal válida o consulta RUC.

**PROPUESTA TO-BE:** hasta implementar y validar el ciclo fiscal, toda representación debe indicar inequívocamente su carácter interno/no emitido y su estado real.

## 5. Facturación electrónica TO-BE

**CONFIRMADO POR NEGOCIO:** el rediseño no debe destruir ni dificultar la futura emisión real.

```text
VENTA CONFIRMADA
    → DOCUMENTO TRIBUTARIO
    → SERIE / CORRELATIVO
    → GENERACIÓN ELECTRÓNICA
    → FIRMA
    → ENVÍO SUNAT/OSE
    → RESPUESTA
    → CDR
    → ACEPTADO / RECHAZADO
    → CONSULTA / REINTENTO / ANULACIÓN
```

Principios TO-BE:

- separar venta, pago, comprobante interno y documento tributario;
- preservar líneas, precios, impuestos, identidad fiscal y numeración histórica;
- hacer idempotentes generación, envío y procesamiento de respuesta;
- conservar XML, CDR, respuesta, intentos y estado real sin exponer secretos;
- no considerar emitido un documento por imprimirlo o marcar localmente un estado;
- permitir reintentos controlados sin duplicar numeración ni documentos;
- mantener proveedor/OSE desacoplado del núcleo comercial.

**PENDIENTE DE NEGOCIO:** validación contable del alcance definitivo, reglas detalladas de notas/anulación, contingencia, puntos de emisión y responsabilidades operativas.

**PENDIENTE DE DECISIÓN TÉCNICA POSTERIOR:** proveedor, OSE, librería, formato de almacenamiento y contrato de integración.

### 5.1 Alcance funcional provisional confirmado

- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** preparar BOLETA, FACTURA y mecanismos de corrección/anulación.
- El diseño debe contemplar la evolución hacia notas de crédito, otros documentos correctivos, bajas, rechazos y contingencia.
- Rechazos, notas, anulaciones y acciones fiscales sensibles requieren permisos financieros/administrativos específicos.
- Un usuario que opera caja no adquiere automáticamente esas capacidades.
- **PENDIENTE DE NEGOCIO:** validación del alcance tributario definitivo y responsabilidades con el área contable/administrativa antes de implementar SUNAT.

## 6. Llamador de pacientes

### 6.1 Estado conocido

- **CONFIRMADO POR NEGOCIO:** existe el repositorio `devceosalud/LLAMADOR-PACIENTE-CEO` y se pretende integrarlo al flujo asistencial.
- **CONFIRMADO EN CÓDIGO DEL ERP:** `appointments` contiene estados/marcas temporales asociados al flujo de llamado, pero el ERP actual no implementa el contrato completo.
- **PENDIENTE DE AUDITORÍA:** código, arquitectura, datos y comportamiento real del repositorio del llamador.

### 6.2 Dirección TO-BE

```text
CITA CONFIRMADA
    → LLEGADA
    → EN ESPERA
    → PROFESIONAL DISPONIBLE
    → LLAMADO A CONSULTORIO
    → EN CONSULTA
    → ATENCIÓN CLÍNICA
    → FINALIZADA
```

- El ERP mantiene el estado canónico de la atención.
- El llamador participa mediante un contrato posterior y no mantiene un flujo paralelo independiente.
- La pantalla pública muestra la información mínima necesaria.
- Una indisponibilidad del llamador no debe impedir registrar ni finalizar la atención en el ERP.
- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** el llamador no obtiene por su función permisos para modificar contenido clínico ni ejecutar accesos de emergencia.

### 6.3 Pendientes

- auditoría del repositorio externo;
- estados y eventos compatibles;
- propiedad de cada transición;
- identificación de consultorio;
- actualización en tiempo real, reintentos y recuperación;
- datos permitidos en pantalla pública;
- operación manual de contingencia.

## 7. Elementos que deben preservarse en futuros refactors

1. Búsqueda local antes de consultar DNI externamente.
2. Normalización central de respuestas DNI/RUC detrás de servicios sustituibles.
3. Ingreso manual cuando los proveedores no estén disponibles.
4. Separación entre consulta RUC y emisión SUNAT.
5. Snapshot de cliente, líneas, cantidades, precios, afectación e impuestos.
6. Unicidad de tipo/serie/correlativo y asignación concurrente segura.
7. Relación entre voucher, líneas, pagos, caja, usuario y documento padre.
8. Campos preparatorios SUNAT, revisados dentro del futuro modelo fiscal en vez de eliminados sin análisis.
9. Estado canónico de atención en el ERP y aislamiento del llamador.
10. Fakes de HTTP y prohibición de llamadas externas reales en pruebas.

## 8. Integraciones clínicas evolutivas

- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** la primera entrega de HCE contempla órdenes, resultados e informes como capacidades funcionales, sin exigir todavía integración profunda con laboratorio o imágenes.
- **PROPUESTA TÉCNICA:** una integración futura debe conservar origen, estado, paciente, atención, autor y fecha/hora de cada orden/resultado sin sobrescribir el registro clínico firmado.
- **PENDIENTE DE NEGOCIO:** estudios prioritarios, sistemas externos, responsables de validación y reglas de incorporación a la historia.
- **PENDIENTE DE VALIDACIÓN PRODUCTIVA:** procesos actuales de laboratorio, radiología, documentos y entrega de resultados.

## 9. Evidencia de pagos y servicios externos

- **PROPUESTA FUNCIONAL APROBADA PROVISIONALMENTE:** el pago mantiene monto, método, número de operación, evidencia, verificador, fecha/hora y estado de verificación.
- La evidencia no debe asumirse como confirmación automática de una pasarela o banco; actualmente existe revisión humana.
- Verificar un pago no autoriza por sí mismo una excepción al adelanto.
- **PENDIENTE DE NEGOCIO:** conservación de evidencias, criterios de aceptación y eventual integración con pasarelas o conciliación.
