# Inventario de modificaciones — PR Draft #46 integrado

Baseline GitHub `dcb9b2b7c9dafe75d79be95992fdbf0b955a9ace`; checkpoint v2 `38a0446e6c6c30e0a9ef1a9e03bf5a9563ea1849`. Se integra en el worktree agenda-v2 y la misma rama feat/agenda-operativa-flujo-reevaluaciones-v2. No incluye cambios del checkout original ni otros worktrees.

El inventario siguiente refleja el diff final contra el main actualizado, no solo los conflictos textuales. Las fuentes se identifican por blobs Git en evidence/FUENTES_SHA256.json. Los archivos ya idénticos a main no se cuentan como cambios del PR.

## Backend, permisos y presentación

- `app/Http/Controllers/Scheduling/AdmissionBoardController.php`
- `app/Http/Controllers/Scheduling/AdmissionTaskController.php`
- `app/Http/Controllers/Scheduling/AgendaFeedController.php`
- `app/Http/Controllers/Scheduling/AppointmentReevaluationHistoryController.php`
- `app/Http/Controllers/Scheduling/OperationalProcedureController.php`
- `app/Http/Controllers/admissionist/appointment/AppointmentController.php`
- `app/Http/Requests/Scheduling/OperationalRegistrationRequest.php`
- `app/Models/Appointment.php`
- `app/Models/AppointmentOperationalReview.php`
- `app/Services/Scheduling/AdmissionBoardService.php`
- `app/Services/Scheduling/AdmissionCareService.php`
- `app/Services/Scheduling/AdmissionTaskService.php`
- `app/Services/Scheduling/AgendaBoardPresenter.php`
- `app/Services/Scheduling/AppointmentReevaluationService.php`
- `app/Services/Scheduling/LegacyAppointmentRegistration.php`
- `app/Services/Scheduling/ManualReevaluationService.php`
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
- `tests/Feature/Scheduling/ManualReevaluationTest.php`
- `tests/Feature/Scheduling/MvpAllRolesFlowTest.php`
- `tests/Feature/Scheduling/OperationalRegistrationTest.php`
- `tests/Feature/Scheduling/PilotAppointmentOperationsTest.php`
- `tests/Feature/Scheduling/PilotAppointmentPaymentTest.php`
- `tests/Feature/Scheduling/ReevaluationIndicationTest.php`
- `tests/Feature/Scheduling/RegularReservationExclusivityTest.php`
- `tests/Feature/Scheduling/UnifiedReevaluationTest.php`
- `tests/Integration/AgendaV2/AgendaV2ConcurrencyTest.php`
- `tests/JavaScript/admission-booking-indicator.test.js`
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
- `tests/Support/agenda-v2-integration-seed.php`
- `tests/Support/agenda-v2-request.php`
- `tests/Support/agenda-v2-round2-seed.php`
- `tests/TestCase.php`
- `tools/qa/browser-agenda-v2-round2.cjs`
- `tools/qa/browser-agenda-v2.cjs`
- `tools/qa/browser-catalog.cjs`
- `tools/qa/run-agenda-v2-browser.ps1`

## CI

- `.github/workflows/agenda-v2-checks.yml`

## Componentes temporales retirados

- `.github/workflows/admission-manual-reevaluation-checks.yml`
- `app/Support/Scheduling/AdmissionAppointmentPresentation.php`
- `tests/Support/IsolatedManualReevaluationQa.php`
- `tests/Support/manual-reevaluation-qa-router.php`
- `tests/Support/manual-reevaluation-qa-seed.php`
- `tests/Support/manual-reevaluation-qa-verify.php`

Los reemplazan el lector unificado, la presentación compartida, las suites completas y el runtime QA v2. Se conservan todos los tests funcionales de hotfix y sus eventos productivos; no se borran datos ni SQLite/artefactos locales. El controlador PATCH y el ledger directo compatibles permanecen.

## Documentación, evidencia y datos

Los nueve documentos de docs/agenda-v2 incluyen diagnóstico original, resolución de los cinco conflictos, permisos, QA, inventario, arquitectura, esquema, liberación y rollback. docs/hotfix-admision se conserva como registro histórico con un enlace al procedimiento vigente. evidence/integration contiene capturas de los tres roles, cuatro resoluciones, auditoría paginada y JSON sanitizado; las capturas y diagnósticos de rondas anteriores se conservan.

No hay migración nueva por integrar #47–#49. Las tres tablas aditivas de procedimientos de v2 siguen pendientes de una futura liberación autorizada desde main. No se altera motivo histórico, dinero, estados clínicos ni ledgers al leer marcas v1. El nuevo endpoint paginado es privado y de lectura.

Los tests conservan importes, duplicados, indicaciones, días clínicos, adjuntos, privacidad, permisos y auditoría. ATENDIDO cambia su expectativa visual a la paleta aprobada sin atribuir salida ni reevaluación implícitas. El chip LEGADO deja de consumir espacio; el dato y su etiqueta interna permanecen. Los nuevos casos demuestran que retirar un origen no elimina otro y que la auditoría completa puede paginarse sin perder eventos.
