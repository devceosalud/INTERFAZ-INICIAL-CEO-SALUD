# Inventario de modificaciones — PR #46

Baseline main `9c3b93a5086172d30fbf59fc9c4c2c40df84a645`; ronda2 continúa desde58c5f92 en `feat/agenda-operativa-flujo-reevaluaciones-v2`, worktreeagenda-v2. No incluye archivos del checkout original ni otros worktrees.

## Backend y modelos

- `app/Http/Controllers/Scheduling/AdmissionBoardController.php`
- `app/Http/Controllers/Scheduling/AdmissionTaskController.php`
- `app/Http/Controllers/Scheduling/AgendaFeedController.php`
- `app/Http/Controllers/Scheduling/OperationalProcedureController.php`
- `app/Http/Controllers/admissionist/appointment/AppointmentController.php`
- `app/Http/Requests/Scheduling/OperationalRegistrationRequest.php`
- `app/Models/Appointment.php`
- `app/Models/AppointmentOperationalReview.php`
- `app/Services/Scheduling/AdmissionBoardService.php`
- `app/Services/Scheduling/AdmissionCareService.php`
- `app/Services/Scheduling/AdmissionTaskService.php`
- `app/Services/Scheduling/AgendaBoardPresenter.php`
- `app/Services/Scheduling/LegacyAppointmentRegistration.php`
- `app/Services/Scheduling/OperationalAgendaAppointmentService.php`
- `app/Services/Scheduling/OperationalProcedureService.php`
- `app/Services/Scheduling/OperationalRegistrationService.php`
- `app/Services/Scheduling/ReservationPaymentService.php`
- `app/Support/Scheduling/AgendaAppointmentPresentation.php`
- `app/Support/Scheduling/AgendaLegend.php`
- `app/Support/Scheduling/OperationalText.php`

## Esquema y rutas

- `database/migrations/2026_10_16_020000_create_appointment_operational_procedures.php`
- `routes/scheduling.php`

## Interfaz

- `public/css/scheduling/admission-board.css`
- `public/css/scheduling/agenda.css`
- `public/css/scheduling/operational-reevaluations.css`
- `public/js/admin/master/doctor-service/doctor-service.js`
- `public/js/admissionist/appointment/appointment.js`
- `public/js/scheduling/admission-board.js`
- `public/js/scheduling/agenda-guidance.js`
- `public/js/scheduling/agenda-operational-workspace.js`
- `public/js/scheduling/agenda-refresh.js`
- `public/js/scheduling/agenda.js`
- `public/js/scheduling/operational-reevaluations.js`
- `resources/views/admin/appointment/index.blade.php`
- `resources/views/admissionist/appointment/crud/create.blade.php`
- `resources/views/admissionist/appointment/index.blade.php`
- `resources/views/components/utils/operational-reevaluations.blade.php`
- `resources/views/components/utils/reevaluations.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/receptionist/appointment/index.blade.php`
- `resources/views/scheduling/admission/index.blade.php`
- `resources/views/scheduling/agenda/index.blade.php`
- `resources/views/scheduling/agenda/partials/operational-registration.blade.php`
- `resources/views/scheduling/agenda/partials/patient-modal.blade.php`
- `resources/views/scheduling/agenda/partials/quick-registration.blade.php`
- `resources/views/templates/nav/administrador.blade.php`
- `resources/views/templates/nav/admision.blade.php`
- `resources/views/templates/nav/comercial.blade.php`
- `resources/views/templates/nav/mvp.blade.php`
- `resources/views/templates/nav/recepcion.blade.php`

## Pruebas y arneses

- `phpunit.agenda-v2-mariadb.xml`
- `tests/Concerns/BuildsBaselineData.php`
- `tests/CreatesApplication.php`
- `tests/Feature/Auth/WorkerAccessTest.php`
- `tests/Feature/Baseline/ApplicationAndAuthenticationSmokeTest.php`
- `tests/Feature/Baseline/PatientAndAppointmentSmokeTest.php`
- `tests/Feature/Catalog/ActiveDoctorServiceCatalogTest.php`
- `tests/Feature/Catalog/CommercialReevaluationTest.php`
- `tests/Feature/Catalog/ServicePriceCatalogTest.php`
- `tests/Feature/Sales/BankPaymentIntegrityTest.php`
- `tests/Feature/Scheduling/AdmissionBoardTest.php`
- `tests/Feature/Scheduling/AdmissionTaskTest.php`
- `tests/Feature/Scheduling/AgendaAppointmentWriteTest.php`
- `tests/Feature/Scheduling/AgendaBoardTest.php`
- `tests/Feature/Scheduling/AgendaDoubleDurationTest.php`
- `tests/Feature/Scheduling/AgendaRefinementTest.php`
- `tests/Feature/Scheduling/AgendaSemaphorizationTest.php`
- `tests/Feature/Scheduling/AgendaStabilizationTest.php`
- `tests/Feature/Scheduling/AgendaV2CompatibilityTest.php`
- `tests/Feature/Scheduling/AgendaV2PaymentsTest.php`
- `tests/Feature/Scheduling/AgendaV2ProceduresTest.php`
- `tests/Feature/Scheduling/AgendaV2ReversalTest.php`
- `tests/Feature/Scheduling/AgendaV2Round2Test.php`
- `tests/Feature/Scheduling/AppointmentDiscountRegistrationTest.php`
- `tests/Feature/Scheduling/AppointmentPaymentProofsTest.php`
- `tests/Feature/Scheduling/AppointmentReaderPrivacyTest.php`
- `tests/Feature/Scheduling/AppointmentWithdrawalTest.php`
- `tests/Feature/Scheduling/LegacyRegistrationParityTest.php`
- `tests/Feature/Scheduling/MvpAllRolesFlowTest.php`
- `tests/Feature/Scheduling/OperationalRegistrationTest.php`
- `tests/Feature/Scheduling/PilotAppointmentOperationsTest.php`
- `tests/Feature/Scheduling/PilotAppointmentPaymentTest.php`
- `tests/Feature/Scheduling/ReevaluationIndicationTest.php`
- `tests/Feature/Scheduling/RegularReservationExclusivityTest.php`
- `tests/Integration/AgendaV2/AgendaV2ConcurrencyTest.php`
- `tests/JavaScript/admission-tasks-ui.test.js`
- `tests/JavaScript/agenda-guidance.test.js`
- `tests/JavaScript/agenda-modal-close.test.js`
- `tests/JavaScript/agenda-permission-flow.test.js`
- `tests/JavaScript/agenda-quote.test.js`
- `tests/JavaScript/agenda-v2-refresh.test.js`
- `tests/JavaScript/doctor-service-compatibility.test.js`
- `tests/JavaScript/operational-reevaluations.test.js`
- `tests/Support/AgendaV2TestCase.php`
- `tests/Support/IsolatedAgendaV2Qa.php`
- `tests/Support/agenda-v2-browser-router.php`
- `tests/Support/agenda-v2-browser-seed.php`
- `tests/Support/agenda-v2-init.php`
- `tests/Support/agenda-v2-request.php`
- `tests/Support/agenda-v2-round2-seed.php`
- `tests/TestCase.php`
- `tools/qa/browser-agenda-v2-round2.cjs`
- `tools/qa/browser-agenda-v2.cjs`
- `tools/qa/browser-catalog.cjs`
- `tools/qa/run-agenda-v2-browser.ps1`

## CI

- `.github/workflows/agenda-v2-checks.yml`

## Documentación y evidencia

Los ocho documentos de `docs/agenda-v2/` describen análisis, diagnóstico reproducido, flujos, permisos, QA, esquema, despliegue y rollback. `evidence/round2/` contiene capturas antes/después, estados sintéticos y fallo demostrado antes del guard. `RESUMEN.json` registra suites completadas; `FUENTES_SHA256.json` identifica fuentes normalizadas LF. Logs/JUnit, secretos, fixtures y datos del servidor quedan ignorados localmente.

## Datos e impacto

No hay migración adicional en ronda2. Se conservan las tres tablas aditivas de la primera ronda y todas las auditorías. Motivo requerido solo en nuevas creaciones; no se toca historia. Observación permite un carácter significativo y protección TEXT16000. Todos los roles reciben403 en creación heredada; consultas/ediciones permanecen autorizadas.

Los tests positivos se adaptaron al motivo requerido. Los casos que antes creaban por legado ahora comprueban403 y continúan el mismo flujo económico por el endpoint operativo. Se conservan aserciones de importes, indicaciones, días, adjuntos, duplicados, privacidad y permisos; no se eliminan ni omiten casos. Los helpers DOM simulan el nuevo campo compartido entre formulario y modal.
