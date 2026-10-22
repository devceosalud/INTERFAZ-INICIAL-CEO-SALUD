# Agenda de Admisión integrada — candidata local

Rama: codex/admision-flujo-integrado-20261022. Base PR64: 3e1ca22b8e7e07bb2ddf18221cd8ff70499242a7. Producción reportada/verificada por el responsable: d245918793014621066c8f35822dfca51b9ec296. main remoto continúa 1f840e90912d2ce8c55ce7502a22a1d582492bc3: NO usar main como base productiva ni destino de instalación por su nombre.

## Diagnóstico y cambio mínimo

La llegada COMERCIAL ya estaba implementada correctamente. Los escritores clínicos también existían, pero la navegación obligaba a salir de Admisión. Se conectaron los servicios existentes al detalle y controles existentes; no se construyó otra agenda ni un segundo módulo de procedimientos. El antiguo GET médico redirige a Admisión tras verificar identidad. Las API clínicas históricas conservan sus autorizaciones para compatibilidad, sin servir otra pantalla operativa.

La lectura de Admisión admite ahora cuentas exclusivamente clínicas, con citas confirmadas propias, filtros acotados y proyección sin importes/contactos/documentos económicos. No se entregan permisos genéricos de Agenda. ADMINISTRADOR + MEDICO verificado conserva su acceso administrativo, y actúa clínicamente solo sobre su médico vinculado. La preferencia de ingreso ya existente de la cuenta verificada se conserva. No se modificaron cuentas reales.

COMERCIAL puede verificar procedimientos seleccionados, autorizar el retorno parcial con el motivo/confirmación ya existentes, marcar retorno sin procedimientos ante indicación médica activa, y registrar salida mediante endpoint acotado. No obtiene appointment.update ni capacidad de solicitar/retirar indicaciones médicas. Se conservan reevaluaciones manuales históricas de ATENDIDO.

La exoneración no ocultaba una cita confirmada: seguía siendo una reserva privada pendiente. Se expone confirmación explícita de una exoneración previamente autorizada con motivo, reutilizando ReservationPaymentService. Revalida revisión y disponibilidad bajo transacción. Solo después pasa a confirmada y puede verla Comercial. No se registra pago de cero; no se concede acceso a reservas privadas ajenas. Un administrador que solo ve una reserva por excepción de lectura no adquiere por ello permiso de escritura sobre una reserva ajena: se mantiene el alcance del escritor existente.

## Matriz ANTES / DESPUÉS y permisos

| Operación | Antes (PR64) | Después | Validación |
|---|---|---|---|
| Llegada COMERCIAL/ADMISION | Ya funcionaba | Se conserva; endpoint COMERCIAL acotado | CommercialArrivalTest + IntegratedAdmissionCareTest |
| Inicio/fin médico | Pantalla médica separada | Panel actual Admisión; MEDICO activo vinculado, permisos clínicos, cita propia | VerifiedMedicalCareTest, IntegratedAdmissionCareTest |
| ADMINISTRADOR + MEDICO | Acceso clínico propio y administrativo permitido | Conservado; sin quitar roles/vínculo | MedicalAccessCompatibilityTest y navegador ficticio |
| Otro médico / personal no clínico | Clínico denegado | 403 conservado; reserva ajena privada 404 | Nuevas pruebas y suite de identidad |
| Médico sin cuenta | Contingencia explícita existente | Se conserva con motivo/auditoría, no firma médica | Suite completa UnlinkedDoctorAdmissionFallback |
| Reevaluación pendiente | Existía y bloqueaba fin | Botón REEVALUACIÓN dentro de Admisión; mismo bloqueo/backend y chip secundario | Nuevas pruebas + colores iguales antes/después |
| Solicitud de procedimientos | Médico desde otra pantalla; admin existente | Médico propio desde editor existente; administración ordinaria conservada | IntegratedAdmissionCareTest y regresión |
| Verificación procedimientos | ADMISION/ADMINISTRADOR | También COMERCIAL autorizado; sin modificar solicitud | 2/3 y 3/3, autores y contadores verificados |
| Listo automático | No vacío y todos realizados | Sin duplicar reglas; naranja según prioridad existente | Feature + navegador escritorio/móvil |
| Retorno manual | Histórico y abierto restringido | Histórico intacto; COMERCIAL autorizado también para indicación abierta sin procedimientos | UnifiedReevaluationTest, nuevas pruebas |
| Retorno parcial | Motivo, confirmación, al menos uno realizado | También COMERCIAL autorizado, sin falsificar pendientes | Nuevas pruebas |
| Finalización/salida | Separadas; salida ADMISION/ADMIN | Separadas; salida COMERCIAL acotada tras ATENDIDO, idempotente | Nuevas pruebas + navegador + inspección DB |
| Confirmar exoneración | Servicio ya existía; sin acción en detalle Admisión | Confirmación expresa con revisión y validación de slot, sin pago | Nuevas pruebas, incluido conflicto |
| Reprogramación histórica | Ya auditada | Sin cambios de lógica/CSS/marcas; regresión completa | RescheduleVisual + suites Agenda/Admission |
| Reservas, horarios, slots y pagos | Contratos vigentes | Escritores ordinarios sin cambios | Suites completas SQLite/MariaDB/JS |
| Usuarios, roles, IDs y catálogo | Vigentes | Sin migraciones, asignaciones, cambios maestros o IDs | Suites completas de compatibilidad |

## Archivos y funciones principales

- AdmissionAccess::handle/clinicalOnly: gate exclusivo de lectura de esta pantalla, sin abrir endpoints generales.
- AdmissionBoardController::index/commercialDeparture/confirmExoneration: catálogo acotado, salida específica y confirmación expresa.
- AdmissionBoardService::board/detail/protect/careActions: misma proyección/lista/filtros; añade acciones clínicas; limita datos de cuentas exclusivamente médicas.
- MedicalBoardController::index: redirección autenticada; care/procedures existentes se reutilizan sin sustituir validaciones clínicas.
- AdmissionCareService::commercialDeparture/transition: extensión acotada del escritor/auditoría existentes para salida, conservando llegada/legacy/fallback.
- OperationalProcedureService::canComplete/canSend y ManualReevaluationService::eligible: participación operativa COMERCIAL solicitada, preservando reglas de realización/manual.
- AdmissionExonerationConfirmation::confirm: envoltura transaccional del escritor de reservas existente; sin cobros ficticios, sin ampliación de privacidad.
- AuthController y RedirectIfAuthenticated: destino médico único Admisión; la preferencia dirigida previamente verificada y demás destinos no cambian.
- sidebar.blade.php: retira menú Atención médica, conserva el administrativo y enlaza Admisión para médicos.
- admission/index.blade.php y admission-board.js: mismas pestañas, lista, filtros, resúmenes y panel; botones clínicos/confirmaciones en el detalle existente. Las cuentas únicamente médicas no reciben pestañas operativas/económicas no autorizadas.
- routes/scheduling.php: solo lectura compartida y endpoints acotados; middleware general de Agenda/economía permanece intacto.
- Tests existentes: se actualizan únicamente expectativas sustituidas por el requerimiento (destino único y verificación COMERCIAL). No se elimina cobertura.

Lista exacta: ARCHIVOS.txt. Diff incremental y contra producción: archivos .diff junto a esta entrega (se generan después del commit fuera del conjunto revisado).

## Resultados verificables

- SQLite: 1274 pruebas, 11404 aserciones, 0 errores/fallos/omitidas.
- MariaDB 11.8.9 aislada: 1295 pruebas, 11604 aserciones, 0 errores/fallos/omitidas; incluye carreras existentes de identidad/revocación y operaciones.
- JavaScript: 356/356, sin omitidas.
- Navegador Chromium real: siete comprobaciones del recorrido único, escritorio 1440x1000 y móvil 390x844, sin errores JS; llegada → inicio → solicitud de tres procedimientos → pendiente → 2/3 → 3/3 listo → fin → salida.
- Base del navegador: una cita, un pago S/60, precio S/120, saldo S/60, tres procedimientos solicitados por médico y verificados por Comercial; tres eventos clínicos y timestamps separados. browser-database.json.
- Cachés de rutas/vistas generados y limpiados en QA con código 0. Sintaxis PHP/JS y diff --check verificados.

Los primeros ensayos detectaron tres expectativas antiguas de solo lectura COMERCIAL; se actualizaron al cambio solicitado. Otra prueba negativa permitió corregir el mapeo del conflicto de slot a HTTP 409 en el nuevo endpoint. Las ejecuciones finales adjuntas pasan completas. La automatización de navegador se ajustó a la confirmación/reconstrucción real de los checkboxes y al texto existente Registrar salida, sin cambiar ese comportamiento de producto para satisfacer la prueba.

QA no usa .env, paciente real ni cuenta productiva. SQLite en memoria y runtime MariaDB local propio en 127.0.0.1:33331, comprobación estricta de datadir/versión/cuenta; export privado adapta solo guardas de identidad y smoke de conexión, sin debilitar las guardas del repositorio. En CI se conserva el runtime original. Capturas 01..06 y JSON/JUnit en qa/. Las capturas antes/después muestran estados del recorrido ficticio, no una comparación fabricada de código productivo.

## Base de datos, riesgos y publicación

Sin nuevas migraciones, dependencias ni cambios .env/config/workflows respecto a d245918. Se reutilizan appointment_events, appointment_operations y estructuras existentes de procedimientos/revisión. No se cambian doctors.id ni appointments.doctor_id.

Riesgos: mezcla de rutas/JS si se actualiza public_html con peticiones activas; OPcache web; archivos ignorados/personalizaciones del servidor no inspeccionados; asignaciones reales/permisos que deben comprobarse por el administrador. No afirmar validación de producción: las pruebas son locales ficticias. DESPLIEGUE.md contiene preflight, respaldo, mantenimiento breve, drenaje, instalación y rollback sin borrar auditoría. No se ejecutaron comandos productivos.

PR de referencia: https://github.com/devceosalud/INTERFAZ-INICIAL-CEO-SALUD/pull/64 . Esta candidata se conserva local para revisión; no se ha actualizado ese PR ni publicado otra rama. Publicación/CI remoto de este nuevo SHA pendientes de autorización específica. No merge, auto-merge, migraciones productivas ni despliegues.
