# Inventario vigente — cuarta ronda local

Base integrada: `76e6ad21d69372d429698de35142689dfd6bd873`. Inicio de cuarta ronda: `c8b91fa8c4096345c9d5a7e59a523e0ab81a1236`. Recuperación: `0ba1c23bcf8e52da0e641bda155f39c860a1c843`; checkpoint `9c78b3b`; merge reciente `c598129`; fuente funcional final `d93f4841d68bb864c2aa2545284f27d81df88d1c`. Rama feat/agenda-operativa-flujo-reevaluaciones-v2, worktree agenda-v2. PR46 cerrado, Draft y sin fusionar. Sin push ni despliegue.

[Inventario completo de cuarta ronda y continuación](evidence/round4/ARCHIVOS_RONDA4.json), [fuentes exactas SHA256](evidence/FUENTES_SHA256.json), [acta local y guía](CONTINUACION_20261020.md), [matriz de rondas](MATRIZ_CUMPLIMIENTO_RONDAS.md), [QA/capturas](QA_Y_EVIDENCIAS.md).

La continuación modifica RegularCapacityService, AgendaLegend, el renderizador/estilos/indicadores de Agenda, el gestor, el enlace de gerencia, fixtures y pruebas de capacidad/reportes, y el soporte de replay/consola QA. Los nueve archivos importados de main corresponden a PR53–55 y se distinguen en el JSON; PR51 ya estaba integrado. No hay una nueva migración en esta continuación; la cuarta ronda conserva la extensión aditiva del19/10.

El JSON detalla el diff desde c8 y el acumulado respecto de main76e6ad2, incluidos documentos, pruebas, evidencias y eliminaciones previas del PR. Las fuentes se calculan desde blobs del índice de Git, respetando los bytes de PDF.js. El archivo ajeno dad}sad}sa y storage/app/qa-v2 (datos/contraseñas/procesos privados de QA) permanecen fuera de los commits.

# Inventario histórico de tercera ronda

Baseline remoto `dcb9b2b7c9dafe75d79be95992fdbf0b955a9ace`; inicio de esta ronda `840522eca91638d16a58ed1b40907c8ce6f749ea`. Rama `feat/agenda-operativa-flujo-reevaluaciones-v2`, worktree agenda-v2. Sin cambios en otros worktrees.

[Detalle de esta ronda](evidence/round3/ARCHIVOS_RONDA3.json), [fuentes y hashes](evidence/FUENTES_SHA256.json), [diagnóstico y guía](RONDA_3_DIAGNOSTICO_Y_GUIA.md). El inventario siguiente muestra el diff acumulado del PR contra main; las evidencias anteriores se conservan.

## Backend y esquema

- `app/Http/Controllers/Patients/OperationalPatientController.php`
- `app/Http/Controllers/Scheduling/AdmissionBoardController.php`
- `app/Http/Controllers/Scheduling/AdmissionTaskController.php`
- `app/Http/Controllers/Scheduling/AgendaFeedController.php`
- `app/Http/Controllers/Scheduling/AppointmentDocumentController.php`
- `app/Http/Controllers/Scheduling/AppointmentReevaluationHistoryController.php`
- `app/Http/Controllers/Scheduling/OperationalProcedureController.php`
- `app/Http/Controllers/Scheduling/OperationalRegistrationController.php`
- `app/Http/Controllers/admissionist/appointment/AppointmentController.php`
- `app/Http/Requests/Scheduling/OperationalRegistrationRequest.php`
- `app/Models/Appointment.php`
- `app/Models/AppointmentDocument.php`
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
- `app/Services/Scheduling/PatientAppointmentDocumentUpload.php`
- `app/Services/Scheduling/ReevaluationIndicationService.php`
- `app/Services/Scheduling/ReplaceAppointmentDocument.php`
- `app/Services/Scheduling/ReservationPaymentService.php`
- `app/Support/Scheduling/AgendaAppointmentPresentation.php`
- `app/Support/Scheduling/AgendaLegend.php`
- `app/Support/Scheduling/AppointmentDocumentAccess.php`
- `app/Support/Scheduling/AppointmentVisibility.php`
- `app/Support/Scheduling/OperationalText.php`
- `database/migrations/2026_10_16_020000_create_appointment_operational_procedures.php`
- `routes/scheduling.php`

## Interfaz y assets

- `public/assets/vendor/pdfjs/LICENSE`
- `public/assets/vendor/pdfjs/PROVENANCE.json`
- `public/assets/vendor/pdfjs/README.md`
- `public/assets/vendor/pdfjs/pdf.min.js`
- `public/assets/vendor/pdfjs/pdf.worker.min.js`
- `public/css/scheduling/admission-board.css`
- `public/css/scheduling/agenda.css`
- `public/css/scheduling/appointment-patient-documents.css`
- `public/css/scheduling/operational-reevaluations.css`
- `public/js/admin/master/doctor-service/doctor-service.js`
- `public/js/admissionist/appointment/appointment.js`
- `public/js/scheduling/admission-board.js`
- `public/js/scheduling/agenda-guidance.js`
- `public/js/scheduling/agenda-operational-workspace.js`
- `public/js/scheduling/agenda-refresh.js`
- `public/js/scheduling/agenda.js`
- `public/js/scheduling/appointment-patient-documents.js`
- `public/js/scheduling/operational-reevaluations.js`
- `resources/views/admin/appointment/index.blade.php`
- `resources/views/admissionist/appointment/crud/create.blade.php`
- `resources/views/admissionist/appointment/index.blade.php`
- `resources/views/components/utils/operational-reevaluations.blade.php`
- `resources/views/components/utils/reevaluations.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/patients/operational.blade.php`
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

## Pruebas y herramientas

- `.gitattributes`
- `.github/workflows/agenda-v2-checks.yml`
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
- `tests/Feature/Scheduling/AdmissionDepartureTest.php`
- `tests/Feature/Scheduling/AdmissionTaskTest.php`
- `tests/Feature/Scheduling/AgendaAppointmentWriteTest.php`
- `tests/Feature/Scheduling/AgendaBoardTest.php`
- `tests/Feature/Scheduling/AgendaCreatorPrivacyTest.php`
- `tests/Feature/Scheduling/AgendaDoubleDurationTest.php`
- `tests/Feature/Scheduling/AgendaRefinementTest.php`
- `tests/Feature/Scheduling/AgendaSemaphorizationTest.php`
- `tests/Feature/Scheduling/AgendaStabilizationTest.php`
- `tests/Feature/Scheduling/AgendaV2CompatibilityTest.php`
- `tests/Feature/Scheduling/AgendaV2PaymentsTest.php`
- `tests/Feature/Scheduling/AgendaV2ProceduresTest.php`
- `tests/Feature/Scheduling/AgendaV2ReversalTest.php`
- `tests/Feature/Scheduling/AgendaV2Round2Test.php`
- `tests/Feature/Scheduling/AgendaV2Round3Test.php`
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
- `tests/JavaScript/admission-row-selection.test.js`
- `tests/JavaScript/admission-tasks-ui.test.js`
- `tests/JavaScript/agenda-guidance.test.js`
- `tests/JavaScript/agenda-modal-close.test.js`
- `tests/JavaScript/agenda-payment-ocr-placement.test.js`
- `tests/JavaScript/agenda-permission-flow.test.js`
- `tests/JavaScript/agenda-quote.test.js`
- `tests/JavaScript/agenda-v2-refresh.test.js`
- `tests/JavaScript/doctor-service-compatibility.test.js`
- `tests/JavaScript/operational-reevaluations.test.js`
- `tests/Support/AgendaV2TestCase.php`
- `tests/Support/IsolatedAgendaV2Qa.php`
- `tests/Support/agenda-v2-browser-router.php`
- `tests/Support/agenda-v2-browser-seed.php`
- `tests/Support/agenda-v2-health.php`
- `tests/Support/agenda-v2-init.php`
- `tests/Support/agenda-v2-integration-seed.php`
- `tests/Support/agenda-v2-request.php`
- `tests/Support/agenda-v2-round2-seed.php`
- `tests/Support/agenda-v2-round3-seed.php`
- `tests/TestCase.php`
- `tools/qa/browser-agenda-v2-round2.cjs`
- `tools/qa/browser-agenda-v2-round3-functional.cjs`
- `tools/qa/browser-agenda-v2-round3.cjs`
- `tools/qa/browser-agenda-v2.cjs`
- `tools/qa/browser-catalog.cjs`
- `tools/qa/run-agenda-v2-browser.ps1`
- `tools/qa/start-agenda-v2-manual.ps1`

## Documentación y evidencia

- `docs/agenda-v2/ANALISIS_FUNCIONAL.md`
- `docs/agenda-v2/ARCHIVOS_MODIFICADOS.md`
- `docs/agenda-v2/ARQUITECTURA_Y_FLUJOS.md`
- `docs/agenda-v2/INTEGRACION_MAIN_Y_HOTFIXES.md`
- `docs/agenda-v2/MATRIZ_PERMISOS.md`
- `docs/agenda-v2/MIGRACIONES_Y_COMPATIBILIDAD.md`
- `docs/agenda-v2/PLAN_DESPLIEGUE_Y_ROLLBACK.md`
- `docs/agenda-v2/QA_Y_EVIDENCIAS.md`
- `docs/agenda-v2/RONDA_2_DIAGNOSTICO_Y_GUIA.md`
- `docs/agenda-v2/RONDA_3_DIAGNOSTICO_Y_GUIA.md`
- `docs/agenda-v2/evidence/.gitignore`
- `docs/agenda-v2/evidence/FUENTES_SHA256.json`
- `docs/agenda-v2/evidence/RESUMEN.json`
- `docs/agenda-v2/evidence/admin-procedures-desktop.png`
- `docs/agenda-v2/evidence/admin-procedures-mobile.png`
- `docs/agenda-v2/evidence/admission-desktop.png`
- `docs/agenda-v2/evidence/admission-mobile.png`
- `docs/agenda-v2/evidence/agenda-mobile.png`
- `docs/agenda-v2/evidence/integration/after-1366.png`
- `docs/agenda-v2/evidence/integration/after-1440.png`
- `docs/agenda-v2/evidence/integration/after-1920.png`
- `docs/agenda-v2/evidence/integration/after-390.png`
- `docs/agenda-v2/evidence/integration/after-mobile-list.png`
- `docs/agenda-v2/evidence/integration/after-queue.png`
- `docs/agenda-v2/evidence/integration/after-results.json`
- `docs/agenda-v2/evidence/integration/unified-1366.png`
- `docs/agenda-v2/evidence/integration/unified-1440.png`
- `docs/agenda-v2/evidence/integration/unified-1920.png`
- `docs/agenda-v2/evidence/integration/unified-390.png`
- `docs/agenda-v2/evidence/integration/unified-admin-1366.png`
- `docs/agenda-v2/evidence/integration/unified-admin-1440.png`
- `docs/agenda-v2/evidence/integration/unified-admin-1920.png`
- `docs/agenda-v2/evidence/integration/unified-admin-390.png`
- `docs/agenda-v2/evidence/integration/unified-audit-paged.png`
- `docs/agenda-v2/evidence/integration/unified-audit.png`
- `docs/agenda-v2/evidence/integration/unified-preview-results.json`
- `docs/agenda-v2/evidence/master-mfr-compatible.png`
- `docs/agenda-v2/evidence/nicol-desktop.png`
- `docs/agenda-v2/evidence/round2/after-1366.png`
- `docs/agenda-v2/evidence/round2/after-1440.png`
- `docs/agenda-v2/evidence/round2/after-1920.png`
- `docs/agenda-v2/evidence/round2/after-390.png`
- `docs/agenda-v2/evidence/round2/after-mobile-list.png`
- `docs/agenda-v2/evidence/round2/after-queue.png`
- `docs/agenda-v2/evidence/round2/after-results.json`
- `docs/agenda-v2/evidence/round2/before-1366.png`
- `docs/agenda-v2/evidence/round2/before-mobile.png`
- `docs/agenda-v2/evidence/round2/before-results.json`
- `docs/agenda-v2/evidence/round2/original-manual-audit.tsv`
- `docs/agenda-v2/evidence/round2/stale-ready-before.txt`
- `docs/agenda-v2/evidence/round3/PERFORMANCE.json`
- `docs/agenda-v2/evidence/round3/RESULTADOS.json`
- `docs/agenda-v2/evidence/round3/after-1366.png`
- `docs/agenda-v2/evidence/round3/after-1440.png`
- `docs/agenda-v2/evidence/round3/after-1920.png`
- `docs/agenda-v2/evidence/round3/after-390.png`
- `docs/agenda-v2/evidence/round3/after-results.json`
- `docs/agenda-v2/evidence/round3/before-1366.png`
- `docs/agenda-v2/evidence/round3/before-1440.png`
- `docs/agenda-v2/evidence/round3/before-1920.png`
- `docs/agenda-v2/evidence/round3/before-390.png`
- `docs/agenda-v2/evidence/round3/before-results.json`
- `docs/agenda-v2/evidence/round3/documents-admin.png`
- `docs/agenda-v2/evidence/round3/documents-operative.png`
- `docs/agenda-v2/evidence/round3/documents-patient-history.png`
- `docs/agenda-v2/evidence/round3/documents-version-history.png`
- `docs/agenda-v2/evidence/round3/functional-browser.txt`
- `docs/agenda-v2/evidence/round3/functional-results.json`
- `docs/agenda-v2/evidence/round3/initial-state.json`
- `docs/agenda-v2/evidence/round3/javascript.txt`
- `docs/agenda-v2/evidence/round3/mariadb.txt`
- `docs/agenda-v2/evidence/round3/mariadb.xml`
- `docs/agenda-v2/evidence/round3/queue-1366.png`
- `docs/agenda-v2/evidence/round3/red.txt`
- `docs/agenda-v2/evidence/round3/red.xml`
- `docs/agenda-v2/evidence/round3/regression-browser.txt`
- `docs/agenda-v2/evidence/round3/regression-results.json`
- `docs/agenda-v2/evidence/round3/replay-results.json`
- `docs/agenda-v2/evidence/round3/rows-1366.png`
- `docs/agenda-v2/evidence/round3/rows-1440.png`
- `docs/agenda-v2/evidence/round3/rows-1920.png`
- `docs/agenda-v2/evidence/round3/rows-390.png`
- `docs/agenda-v2/evidence/round3/sql-departure-profile.json`
- `docs/agenda-v2/evidence/round3/sqlite.txt`
- `docs/agenda-v2/evidence/round3/sqlite.xml`
- `docs/agenda-v2/evidence/round3/targeted-final.txt`
- `docs/hotfix-admision/QA.md`
- `docs/hotfix-admision/README.md`

## Componentes retirados en la integración anterior

- `.github/workflows/admission-manual-reevaluation-checks.yml`
- `app/Support/Scheduling/AdmissionAppointmentPresentation.php`
- `tests/Support/IsolatedManualReevaluationQa.php`
- `tests/Support/manual-reevaluation-qa-router.php`
- `tests/Support/manual-reevaluation-qa-seed.php`
- `tests/Support/manual-reevaluation-qa-verify.php`

La tercera ronda no elimina pruebas, tablas ni eventos. Los seis archivos retirados pertenecen a los mecanismos temporales ya sustituidos en840522e. Se conservan los eventos v1 y el diagnóstico del parcial con autorización manual anterior.

El visor PDF incorpora únicamente módulo/worker/licencia oficiales fijados en6.4.299, con integridad de paquete y archivos verificable. No se añade otro almacén, sistema de documentos, autenticación ni framework visual. El historial documental reutiliza los modelos y endpoints existentes; no requiere migración adicional. No se versionan credenciales, BD ficticias ni archivos médicos de prueba: únicamente capturas y reportes de QA seleccionados.
