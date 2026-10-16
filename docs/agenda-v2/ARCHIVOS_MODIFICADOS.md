# Archivos modificados

Baseline: 9c3b93a5086172d30fbf59fc9c4c2c40df84a645. Trabajo aislado en feat/agenda-operativa-flujo-reevaluaciones-v2.

## Tablas

Nuevas: appointment_operational_reviews, appointment_requested_procedures, appointment_procedure_events.

Reutilizadas en operaciones nuevas: appointments, appointment_operations, appointment_events, payments, vouchers y relaciones financieras/privadas existentes. No se ejecutan cambios ni backfills históricos; appointment_admission_tasks y appointment_admission_task_events se conservan.

## Backend, visibilidad y presentación

- `app/Http/Controllers/Scheduling/AdmissionBoardController.php`
- `app/Http/Controllers/Scheduling/AgendaFeedController.php`
- `app/Http/Controllers/Scheduling/OperationalProcedureController.php`
- `app/Http/Controllers/admissionist/appointment/AppointmentController.php`
- `app/Models/Appointment.php`
- `app/Models/AppointmentOperationalReview.php`
- `app/Services/Scheduling/AdmissionBoardService.php`
- `app/Services/Scheduling/AdmissionCareService.php`
- `app/Services/Scheduling/AgendaBoardPresenter.php`
- `app/Services/Scheduling/LegacyAppointmentRegistration.php`
- `app/Services/Scheduling/OperationalAgendaAppointmentService.php`
- `app/Services/Scheduling/OperationalProcedureService.php`
- `app/Services/Scheduling/OperationalRegistrationService.php`
- `app/Services/Scheduling/ReservationPaymentService.php`
- `app/Support/Scheduling/AgendaAppointmentPresentation.php`
- `app/Support/Scheduling/AgendaLegend.php`

## Esquema y rutas

- `database/migrations/2026_10_16_020000_create_appointment_operational_procedures.php`
- `routes/scheduling.php`

## Interfaz y componentes

- `public/css/scheduling/admission-board.css`
- `public/js/admin/master/doctor-service/doctor-service.js`
- `public/js/admissionist/appointment/appointment.js`
- `public/js/scheduling/admission-board.js`
- `public/js/scheduling/agenda-guidance.js`
- `public/js/scheduling/agenda-operational-workspace.js`
- `public/js/scheduling/agenda-refresh.js`
- `public/js/scheduling/agenda.js`
- `public/js/scheduling/operational-reevaluations.js`
- `resources/views/admissionist/appointment/crud/create.blade.php`
- `resources/views/admissionist/appointment/index.blade.php`
- `resources/views/components/utils/operational-reevaluations.blade.php`
- `resources/views/components/utils/reevaluations.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/scheduling/admission/index.blade.php`
- `resources/views/scheduling/agenda/index.blade.php`
- `resources/views/scheduling/agenda/partials/operational-registration.blade.php`
- `resources/views/scheduling/agenda/partials/patient-modal.blade.php`
- `resources/views/scheduling/agenda/partials/quick-registration.blade.php`
- `resources/views/templates/nav/admision.blade.php`
- `resources/views/templates/nav/comercial.blade.php`
- `resources/views/templates/nav/mvp.blade.php`

## Pruebas y arneses

- `phpunit.agenda-v2-mariadb.xml`
- `tests/CreatesApplication.php`
- `tests/Feature/Baseline/ApplicationAndAuthenticationSmokeTest.php`
- `tests/Feature/Baseline/PatientAndAppointmentSmokeTest.php`
- `tests/Feature/Catalog/CommercialReevaluationTest.php`
- `tests/Feature/Catalog/ServicePriceCatalogTest.php`
- `tests/Feature/Scheduling/AdmissionBoardTest.php`
- `tests/Feature/Scheduling/AgendaBoardTest.php`
- `tests/Feature/Scheduling/AgendaDoubleDurationTest.php`
- `tests/Feature/Scheduling/AgendaSemaphorizationTest.php`
- `tests/Feature/Scheduling/AgendaStabilizationTest.php`
- `tests/Feature/Scheduling/AgendaV2CompatibilityTest.php`
- `tests/Feature/Scheduling/AgendaV2PaymentsTest.php`
- `tests/Feature/Scheduling/AgendaV2ProceduresTest.php`
- `tests/Feature/Scheduling/AgendaV2ReversalTest.php`
- `tests/Feature/Scheduling/AppointmentDiscountRegistrationTest.php`
- `tests/Feature/Scheduling/AppointmentPaymentProofsTest.php`
- `tests/Feature/Scheduling/AppointmentReaderPrivacyTest.php`
- `tests/Feature/Scheduling/LegacyRegistrationParityTest.php`
- `tests/Feature/Scheduling/MvpAllRolesFlowTest.php`
- `tests/Feature/Scheduling/OperationalRegistrationTest.php`
- `tests/Feature/Scheduling/ReevaluationIndicationTest.php`
- `tests/Feature/Scheduling/RegularReservationExclusivityTest.php`
- `tests/Integration/AgendaV2/AgendaV2ConcurrencyTest.php`
- `tests/JavaScript/agenda-guidance.test.js`
- `tests/JavaScript/agenda-v2-refresh.test.js`
- `tests/JavaScript/doctor-service-compatibility.test.js`
- `tests/Support/AgendaV2TestCase.php`
- `tests/Support/IsolatedAgendaV2Qa.php`
- `tests/Support/agenda-v2-browser-router.php`
- `tests/Support/agenda-v2-browser-seed.php`
- `tests/Support/agenda-v2-init.php`
- `tests/Support/agenda-v2-request.php`
- `tests/TestCase.php`
- `tools/qa/browser-agenda-v2.cjs`
- `tools/qa/browser-catalog.cjs`
- `tools/qa/run-agenda-v2-browser.ps1`

## CI

- `.github/workflows/agenda-v2-checks.yml`

## Documentación y evidencias

Los siete documentos en docs/agenda-v2 explican diagnóstico, flujos, permisos, migración, QA, archivos y despliegue/rollback. evidence/RESUMEN.json y FUENTES_SHA256.json registran resultados sanitizados y hashes de fuentes. Las capturas PNG contienen exclusivamente fixtures ficticios de QA, incluido Maestro Profesional / Servicio con MFR seleccionable y Traumatología bloqueada. Logs/JUnit y secretos locales quedan ignorados.

## Motivo de los ajustes en pruebas heredadas

Las pruebas de creación por el adaptador heredado se ejecutan con autorización ADMINISTRADOR. ADMISION/COMERCIAL tienen nuevos asserts 403 y continúan pruebas económicas por Agenda operativa. Las expectativas de pago positivo ahora exigen CONFIRMADA; los casos de privacidad de reservas pagadas anteriores conservan sus aserciones mediante fixtures históricos explícitos. No se eliminan pruebas de importes, duplicados, Caja, indicaciones, documentos o estados finales. El arnés MariaDB incorpora su detector DDL ya existente y fakes de URLs locales, sin modificar producción. Los textos y el arnés de navegador del catálogo se alinearon con la nueva confirmación automática.

La corrección adicional modifica doctor-service.js y añade doctor-service-compatibility.test.js, junto con el escenario Chrome y fixtures del arnés v2. Conserva la validación de especialidad y refresca únicamente la proyección visual después del cálculo de compatibilidad.
