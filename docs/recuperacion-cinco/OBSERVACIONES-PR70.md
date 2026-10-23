# Corrección de las cinco observaciones manuales del PR #70

> Continuación: [ajustes finales y densidad del PR #70](AJUSTES-FINALES-PR70.md), conservando esta evidencia histórica.

Continuación de `e80d50e23ce233da63fdc9d03a3e8a9a6de5994c` sobre la misma rama `codex/integracion-cinco-main-20261022`. Main y base del PR verificados: `a57b2d04e8302279be1c775c7e8bc913a170f209`. Se conservan #67, #68, la integración #70, los otros worktrees y los cinco archivos modificados del checkout original. No se hace merge ni despliegue.

## Reproducción y solución

| Observación | Causa reproducida | Corrección y resultado comprobado |
|---|---|---|
| 1. Origen ausente del horario de Admisión | El feed exponía el historial en un bloque independiente, pero el renderer solo recorría citas vigentes | Proyección cronológica paginada de citas y referencias `RescheduleHistory`. E aparece a las 10:20 entre 10:00 y 10:40, con fondo rojo, nombre permitido, origen/destino y enlace a su cita vigente de las 14:00. La referencia carece de acciones de cita, no ocupa horario y no entra en KPI ni importes |
| 2. Adelanto aparente de cero | El resumen mostraba el nuevo importe del formulario como «Adelanto ingresado», mientras el panel de pago mostraba el acumulado | Se distinguen precio, total pagado anteriormente, nuevo adelanto, saldo actual y saldo resultante. F: 120 / 60 / 0 / 60 / 60; nuevo10 cambia únicamente la previsión a50. Los snapshots se etiquetan como históricos sin acreditar un pago; la atribución incierta dice «Por conciliar» |
| 3. Descuentos inaccesibles | Los controles de cotización se bloqueaban correctamente para una cita existente, pero el acceso al editor autorizado estaba dentro de Gestionar cita | Acceso visible «Modificar economía de la cita» desde el formulario principal, dirigido al mismo editor y servicio. `admin@demo.ceosalud.invalid` y Julio tienen `appointment.pricing.adjust.edit`; no se añadieron grants para solucionar la interfaz. Descuento20 confirmado: precio100, pagado60, saldo40. Se revisaron recargo válido del catálogo ficticio, porcentaje y retirada de descuento, sin guardar esas propuestas |
| 4. Falta crear y confirmar sin adelanto | La autorización solo estaba disponible después de guardar la reserva | Botón explícito ADMISION en registro rápido y alta de paciente. Motivo obligatorio y confirmación; creación del paciente/cita y llamada a `ReservationPaymentService` dentro de la misma transacción. Una clave derivada estable distingue la confirmación de la operación de registro. Doble envío no duplica citas ni eventos; un conflicto revierte también el nuevo paciente. Ambas entradas dejaron CONFIRMADA, pagado0 y saldo120, sin pago, ticket ni exoneración |
| 5. Reevaluaciones y filtros vacíos | `pending()` excluía `prePending()`, los KPI se calculaban después del filtro rápido, y los grupos rápidos podían conservar filtros incompatibles. La cola secundaria clasificaba PRE_PENDING como historial | Pendientes reúne las dos etapas pendientes, con desglose y selector «Por confirmar» / «Confirmadas, pendientes»; los listos permanecen separados. KPI y resumen económico representan el conjunto de fecha, profesional, sede, estado y búsqueda antes de filtros rápidos. Categorías se alternan, cambian y retiran sin recargar. Estado activo explícito; lista vacía con «Sin citas para este filtro» y acción para retirarlo. La cola de hoy distingue también las tres etapas |

Los filtros rápidos solo seleccionan el listado. Buscar paciente, cambiar rango/profesional/sede o un estado explícito cambia el conjunto de trabajo y, por tanto, sus indicadores. La cola «Reevaluaciones de hoy» identifica su alcance de jornada, mientras el listado puede consultar otro rango. «Otros procedimientos» conserva las solicitudes sin indicación de retorno: siguen visibles y operables, pero no inflan el contador de reevaluaciones pendientes. Se verificó en el navegador la coincidencia KPI/cola antes y después de confirmar C. La lista vacía permite restablecer también la búsqueda sin recargar. Las referencias históricas participan en la paginación visual (`timeline_total`), pero `total`, `kpis` e importes solo cuentan citas reales. Se limita la consulta cronológica a 2.000 referencias; un exceso pide reducir rango o filtros en vez de omitir datos silenciosamente. La paginación anterior del bloque de historial mantiene su cursor independiente.

## Autorización y protección de datos

| Acción | ADMINISTRADOR autorizado | ADMISION | COMERCIAL | MEDICO vinculado |
|---|---|---|---|---|
| Corregir tarifa, descuento o recargo | Mismo editor: revisión firmada, motivo y confirmación; guardas financieras vigentes | No | No | Solo si además posee ADMINISTRADOR y permiso económico; su rol clínico no lo concede |
| Crear y confirmar sin adelanto | No por ADMINISTRADOR solo | Sí, CREATE/VIEW efectivos, cuenta activa, confirmación y motivo | No | No por MEDICO solo |
| Registrar llegada | Sin ampliación de facultades existentes | Sí | Sí, ruta limitada sin UPDATE general | Sin ampliación |
| Confirmar indicación / autorizar retorno / rechazo | Sin ampliación | Según acciones existentes | Según acciones existentes | Sin firma operativa por su rol clínico |
| Iniciar atención, segunda revisión y finalizar | Requiere además identidad médica verificada propia | No firma clínica | No firma clínica | Médico propio, llegada y etapas previas obligatorias |

Se conservan pagos y saldos acreditados, contrato y servicio de la cita, restricciones de caja cerrada, documentos fiscales, devoluciones, créditos y conciliación. La edición económica reutiliza su revisión, token, auditoría e idempotencia. No se habilitan inputs de creación para saltarse ese mecanismo. Las reservas privadas ajenas siguen ocultas, incluido su historial. La identidad permitida de una referencia cronológica solo se añade después de aplicar visibilidad y filtros al historial; el feed genérico de notificaciones sigue sin identidad del paciente.

No hay nuevas tablas, migraciones, módulos, pantallas, roles, categorías de permisos ni estados de MySQL. Se conservan las tres migraciones históricas y `users.doctor_id`, la relación `appointments.doctor_id`, el vínculo de Julio y su redirección exclusiva de usuario2. Pilar continúa pendiente de validación clínica completa con cuenta propia verificada. No se alteró `.env`, Hostinger, main ni datos de pacientes reales.

## Archivos de esta corrección

| Archivos | Motivo |
|---|---|
| `app/Services/Scheduling/AdmissionBoardService.php` | KPI del conjunto base, etapas y paginación cronológica independiente del número de citas |
| `app/Services/Scheduling/RescheduleHistory.php` | Identidad opcional autorizada para referencias inline; exploración acotada y cursor existente |
| `app/Http/Controllers/Scheduling/AdmissionBoardController.php` | Validación del selector de etapa |
| `app/Services/Scheduling/AppointmentReevaluationService.php` | Proyección de etapa confirmada pendiente en cola sin exigir procedimientos ficticios |
| `app/Http/Controllers/Scheduling/OperationalProcedureController.php` | Grupo operativo Por confirmar separado de pendientes/listos/historial |
| `public/js/scheduling/admission-board.js` | Filas históricas, navegación, etiquetas por etapa, filtros excluyentes, estado activo y retirada sin recargar |
| `public/css/scheduling/admission-board.css` | Fondo completo de referencias históricas y distribución de controles; paleta #67 conservada |
| `resources/views/scheduling/admission/index.blade.php` | Desglose, selector y explicación del alcance de indicadores |
| `public/js/scheduling/operational-reevaluations.js`, `resources/views/components/utils/operational-reevaluations.blade.php` | Cola de hoy con indicación por confirmar diferenciada |
| `app/Http/Requests/Scheduling/OperationalRegistrationRequest.php` | Campos validados de autorización explícita al crear |
| `app/Services/Scheduling/OperationalRegistrationService.php` | Creación y confirmación sin adelanto atómicas sobre operaciones existentes |
| `app/Services/Scheduling/ReservationPaymentService.php` | Reutilización de autorización con validación apropiada antes de existir la cita |
| `public/js/scheduling/agenda-operational-form.js` | Proyección exacta de importes separando pago anterior, nuevo pago y saldo |
| `public/js/scheduling/agenda-operational-workspace.js` | Presentación económica, acceso según permisos, motivo/confirmación sin adelanto y bloqueo de doble clic accidental |
| `public/js/scheduling/agenda.js` | Conexión de acciones del formulario y del alta, resumen y resultado explícitos |
| `public/js/scheduling/agenda-manage-appointment.js` | Apertura directa del editor existente y etiquetas económicas coherentes |
| `resources/views/scheduling/agenda/partials/quick-registration.blade.php` | Resumen completo y accesos visibles según rol |
| `resources/views/scheduling/agenda/partials/operational-registration.blade.php` | Saldo actual frente a previsto, etiquetas y orientación al editor autorizado |
| `resources/views/scheduling/agenda/partials/patient-modal.blade.php` | Acción explícita para alta y primera cita sin adelanto |
| `tests/Feature/Scheduling/ManualObservations70Test.php` | Atomicidad, roles, rollback, reintentos, privacidad, cronología/paginación y etapas HTTP |
| `tests/Integration/AgendaV2/AgendaV2ConcurrencyTest.php` | Doble creación simultánea y dos reservas que compiten por el mismo intervalo sin adelanto |
| `tests/JavaScript/manual-observations70.test.js` | Importes, centavos, créditos, snapshots inciertos y separación de etapas |
| `tests/Feature/Scheduling/AgendaV2Round2Test.php`, `AgendaV2ProceduresTest.php`, `ManualReevaluationTest.php`, `JulioAdmissionReevaluationTest.php` | Expectativas de KPI y agrupación adaptadas al alcance solicitado; conservan verificaciones de dinero, permisos y recorrido |
| `tests/JavaScript/operational-reevaluations.test.js` | Conserva procedimientos parciales en su categoría, sin presentarlos como retorno médico confirmado |
| `tests/JavaScript/agenda-modal-explicit-actions.test.js` | Contexto DOM del nuevo botón, sin eliminar la prueba de acciones explícitas |
| `tests/Support/admission-colors-browser.cjs` | Salida nueva de evidencias de colores, conservando las 208 verificaciones y evidencias anteriores |
| Documentación de recuperación y `qa/observaciones70/` | Resultados, incidencias, matriz y evidencias ficticias; credenciales, respaldos y scripts privados locales excluidos de Git |

## Pruebas y fallos conservados

| Verificación sobre esta corrección | Resultado |
|---|---|
| PHPUnit SQLite completo | 1.230 pruebas, 11.304 aserciones, sin fallos |
| PHPUnit MariaDB 11.8.9 completo | 1.257 pruebas, 11.545 aserciones, sin fallos; incluye las dos nuevas carreras de creación sin adelanto |
| JavaScript completo | 355 pruebas, sin fallos |
| Chromium, paleta completa | 208 verificaciones, cuatro perfiles, escritorio/móvil, filas normales/seleccionadas, sin errores JS |
| Chromium, observaciones | Fila E cronológica/enlace; pago60/nuevo10/saldo50; corrección F100/60/40; bloqueos ADMISION/COMERCIAL; Julio administrativo; etapas C y filtros; alta existente/nueva sin adelanto; revisión de recargo, porcentaje y retirada |
| Chromium, recorrido integrado completo repetido | 22 controles: siete cuentas, llegada, atención normal, tres checks, retorno manual, rechazo, segunda evaluación, salida, economía, confirmación e historial; sin errores JS |
| Chromium, comprobación final de coherencia | Cinco controles adicionales: KPI/cola por etapa, otros procedimientos, restablecer búsqueda sin navegar, referencia roja completa con enlace contenido en móvil y previsión económica60/10/50 |
| Auditoría de BD ficticia después de las observaciones | Dos pagos60 originales, dos tickets; nuevas citas sin pago/voucher con una autorización por cita; corrección F auditada; E sin duplicación; paciente nuevo único |

Se guardan los primeros logs fallidos. La primera tanda completa detectó dos expectativas anteriores de KPI/totales filtrados (100 frente a200; 31 frente a32): se adaptaron al nuevo alcance explícito, manteniendo el número de citas de la lista y la privacidad. No se eliminaron pruebas. Los primeros tests añadidos también detectaron fixtures sin revisión de concurrencia/horas no asignables, una proyección de identidad demasiado amplia y el límite de exploración aplicado a la consulta equivocada; se corrigieron fixtures y proyección y se repitió la suite. JavaScript detectó un contexto VM sin `document` para el nuevo botón; se completó ese contexto.

En navegador se detectó que el segundo clic podía cerrar el nuevo diálogo por clic fuera: ahora exige Cancelar o confirmar explícitamente. Se corrigieron esperas del harness para esperar a que la respuesta HTTP se refleje en el DOM y para pulsar Registrar paciente antes de esperar el alta. Ninguna espera cambia el producto ni simula una acción clínica. Las pruebas utilizan autenticación, sesión, CSRF, rutas y servicios reales con datos ficticios. No se toma un resultado verde como aprobación del usuario.

## Cómo volver a probar

Demo local: `http://127.0.0.1:8000`; guía privada de cuentas/escenarios: `/demo-guia`. Recargar con Ctrl+F5 y volver a iniciar sesión después de un reinicio de datos.

1. **E, Admisión:** ver referencia roja10:20 entre10:00 y10:40; seguir Ver cita reprogramada hacia14:00.
2. **F, Agenda operativa:** abrir Registrar adelanto, revisar120/60/0/60/60; escribir10 y observar saldo previsto50, sin guardar.
3. **F, Administrador:** usar el botón visible Modificar economía de la cita. Descuento fijo20, fundamento, motivo, Revisar cambios y confirmar:100/60/40. El recargo ficticio de catálogo está disponible en esta BD local para revisar su efecto; una restricción de caja/fiscal continúa bloqueando guardar cuando corresponda.
4. **Admisión, intervalo libre:** buscar paciente o Registrar paciente, elegir servicio/motivo y Guardar y confirmar sin adelanto. Completar el motivo y confirmar; comprobar amarillo, pago0, saldo completo y evento. G conserva su confirmación posterior como reserva guardada.
5. **C:** Julio indica Reevaluación; Admisión pulsa Pendientes y encuentra Por confirmar. Confirmar reevaluación pendiente cambia a Confirmada, pendiente. Completar checks hasta3/3 la mueve a Listos naranja. Alternar Pendientes/Listos, pulsar de nuevo para retirar y usar Restablecer, sin recargar. Julio debe iniciar realmente la segunda evaluación antes de finalizar.

El reinicio local existente solicita `REINICIAR DEMO PR70`, comprueba la identidad de `ceosalud_demo_pr70` en loopback33343 y respalda antes de restaurar escenarios. Los respaldos de la sesión y evidencias previas se conservan en la carpeta privada local. No se publican credenciales ni dumps. El despliegue/rollback de producción sigue siendo únicamente el plan de `INTEGRACION-MAIN-68.md`, sujeto a autorización posterior.
