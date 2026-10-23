# Archivos modificados en esta ronda del PR #70

Comparación contra `d255da5b4403e854a1e7275120821a39480b1605`. No incluye archivos privados de DEMO70, SQL ni cambios del checkout original.

## Backend: autoría, visibilidad, foco y etapas

- `app/Http/Controllers/Patients/OperationalPatientController.php`
- `app/Http/Controllers/Patients/OperationalPatientMutationController.php`
- `app/Http/Controllers/Scheduling/AdmissionBoardController.php`
- `app/Http/Controllers/Scheduling/AgendaDetailsController.php`
- `app/Http/Controllers/Scheduling/OperationalRegistrationController.php`
- `app/Http/Controllers/admin/appointment/AppointmentController.php`
- `app/Http/Controllers/admissionist/appointment/AppointmentController.php`
- `app/Http/Controllers/admissionist/patient/PatientController.php`
- `app/Http/Controllers/receptionist/appointment/AppointmentController.php`
- `app/Services/Scheduling/AdmissionBoardService.php`
- `app/Services/Scheduling/AppointmentManagement.php`
- `app/Services/Scheduling/AppointmentReevaluationService.php`
- `app/Services/Scheduling/OperationalRegistrationService.php`
- `app/Services/Scheduling/RescheduleHistory.php`
- `app/Support/Patients/PatientWriteAccess.php`

## Interfaz: espacio, tipografía, alertas, formularios y navegación

- `public/css/scheduling/admission-board.css`
- `public/css/scheduling/agenda-indicators.css`
- `public/css/scheduling/agenda-layout.css`
- `public/js/patients/operational.js`
- `public/js/scheduling/admission-board.js`
- `public/js/scheduling/agenda-manage-appointment.js`
- `public/js/scheduling/agenda-operational-workspace.js`
- `public/js/scheduling/agenda-reschedule-history.js`
- `public/js/scheduling/agenda.js`
- `public/js/scheduling/operational-reevaluations.js`
- `resources/views/scheduling/admission/index.blade.php`
- `resources/views/scheduling/agenda/partials/context.blade.php`
- `resources/views/templates/nav/administrador.blade.php`
- `resources/views/templates/nav/admision.blade.php`
- `resources/views/templates/nav/comercial.blade.php`
- `resources/views/templates/nav/mvp.blade.php`
- `resources/views/templates/nav/recepcion.blade.php`

## Pruebas de regresión y navegador

- `tests/Feature/Baseline/ApplicationAndAuthenticationSmokeTest.php`
- `tests/Feature/Legacy/ParentDeletionCharacterizationTest.php`
- `tests/Feature/Patients/AgendaPatientInlineEditTest.php`
- `tests/Feature/Scheduling/AgendaDetailsTest.php`
- `tests/Feature/Scheduling/AgendaSafeQuickEditTest.php`
- `tests/Feature/Scheduling/AgendaV2CompatibilityTest.php`
- `tests/Feature/Scheduling/AgendaV2PaymentsTest.php`
- `tests/Feature/Scheduling/AgendaV2Round2Test.php`
- `tests/Feature/Scheduling/AgendaV2Round4Test.php`
- `tests/Feature/Scheduling/AppointmentReaderPrivacyTest.php`
- `tests/Feature/Scheduling/FinalAdjustments70Test.php`
- `tests/Feature/Scheduling/JulioAdmissionReevaluationTest.php`
- `tests/Feature/Scheduling/MvpAllRolesFlowTest.php`
- `tests/Feature/Scheduling/OperationalDateTest.php`
- `tests/Feature/ShellNavigationTest.php`
- `tests/JavaScript/final-adjustments70.test.js`
- `tests/Support/admission-colors-browser.cjs`
- `tests/Support/agenda-density70-browser.cjs`
- `tests/Support/agenda-registration-layout70-browser.cjs`
- `tests/Support/final-adjustments70-browser.cjs`

## Documentación y evidencias ficticias

- `docs/recuperacion-cinco/AJUSTES-FINALES-PR70.md`
- `docs/recuperacion-cinco/INTEGRACION-MAIN-68.md`
- `docs/recuperacion-cinco/OBSERVACIONES-PR70.md`

Las capturas, matrices y logs seleccionados están en `qa/ajustes-finales70/`; su índice explica cada evidencia y los fallos intermedios conservados. Los tests heredados modificados reflejan la redirección de Seguimiento y el bloqueo por autoría; las verificaciones positivas, privacidad e integridad permanecen.
