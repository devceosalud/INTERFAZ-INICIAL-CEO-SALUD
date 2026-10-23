# Archivos modificados

Comparación contra `main` `a4e2a9b0f9e517c55be10232321391b1dc970e73`. No se cambian los CSS, Blade o JavaScript de Agenda operativa. Las modificaciones compartidas de procedimientos se limitan a identidad, seguimiento y permisos solicitados; su representación en Operativa permanece igual.

## Autorización y backend

- [`app/Http/Controllers/Scheduling/AppointmentDocumentController.php`](../../app/Http/Controllers/Scheduling/AppointmentDocumentController.php)
- [`app/Http/Controllers/Scheduling/MedicalProcedureFollowupController.php`](../../app/Http/Controllers/Scheduling/MedicalProcedureFollowupController.php)
- [`app/Http/Controllers/Scheduling/OperationalProcedureController.php`](../../app/Http/Controllers/Scheduling/OperationalProcedureController.php)
- [`app/Http/Controllers/Scheduling/PatientOperationalAlertController.php`](../../app/Http/Controllers/Scheduling/PatientOperationalAlertController.php)
- [`app/Services/Scheduling/AdmissionBoardService.php`](../../app/Services/Scheduling/AdmissionBoardService.php)
- [`app/Services/Scheduling/ManualReevaluationService.php`](../../app/Services/Scheduling/ManualReevaluationService.php)
- [`app/Services/Scheduling/MedicalProcedureFollowupService.php`](../../app/Services/Scheduling/MedicalProcedureFollowupService.php)
- [`app/Services/Scheduling/OperationalProcedureService.php`](../../app/Services/Scheduling/OperationalProcedureService.php)
- [`app/Services/Scheduling/PatientOperationalAlertService.php`](../../app/Services/Scheduling/PatientOperationalAlertService.php)
- [`app/Support/Scheduling/AdmissionRolePolicy.php`](../../app/Support/Scheduling/AdmissionRolePolicy.php)
- [`app/Support/Scheduling/ProcedureCatalog.php`](../../app/Support/Scheduling/ProcedureCatalog.php)

## Catálogo explícito

- [`config/operational_procedures.php`](../../config/operational_procedures.php)

## Esquema aditivo

- [`database/migrations/2026_10_23_010000_add_procedure_request_identity.php`](../../database/migrations/2026_10_23_010000_add_procedure_request_identity.php)
- [`database/migrations/2026_10_23_020000_create_patient_operational_alerts.php`](../../database/migrations/2026_10_23_020000_create_patient_operational_alerts.php)
- [`database/migrations/2026_10_23_030000_create_medical_procedure_followups.php`](../../database/migrations/2026_10_23_030000_create_medical_procedure_followups.php)

## Presentación de Admisión y seguimiento

- [`public/css/scheduling/admission-clinical.css`](../../public/css/scheduling/admission-clinical.css)
- [`public/css/scheduling/medical-followups.css`](../../public/css/scheduling/medical-followups.css)
- [`public/js/scheduling/admission-board.js`](../../public/js/scheduling/admission-board.js)
- [`public/js/scheduling/medical-followups.js`](../../public/js/scheduling/medical-followups.js)
- [`public/js/scheduling/patient-alerts.js`](../../public/js/scheduling/patient-alerts.js)
- [`public/js/scheduling/procedure-picker.js`](../../public/js/scheduling/procedure-picker.js)

## Vistas y rutas

- [`resources/views/scheduling/admission/index.blade.php`](../../resources/views/scheduling/admission/index.blade.php)
- [`resources/views/scheduling/followups/index.blade.php`](../../resources/views/scheduling/followups/index.blade.php)

## Rutas

- [`routes/scheduling.php`](../../routes/scheduling.php)

## Regresiones y herramientas de QA

- [`tests/Feature/Scheduling/AdmissionBoardTest.php`](../../tests/Feature/Scheduling/AdmissionBoardTest.php)
- [`tests/Feature/Scheduling/AgendaV2CompatibilityTest.php`](../../tests/Feature/Scheduling/AgendaV2CompatibilityTest.php)
- [`tests/Feature/Scheduling/AgendaV2ProceduresTest.php`](../../tests/Feature/Scheduling/AgendaV2ProceduresTest.php)
- [`tests/Feature/Scheduling/ClinicalEnterpriseTest.php`](../../tests/Feature/Scheduling/ClinicalEnterpriseTest.php)
- [`tests/Feature/Scheduling/ManualReevaluationTest.php`](../../tests/Feature/Scheduling/ManualReevaluationTest.php)
- [`tests/Feature/Scheduling/OperationalDocumentsTest.php`](../../tests/Feature/Scheduling/OperationalDocumentsTest.php)
- [`tests/Integration/AgendaV2/ClinicalEnterpriseConcurrencyTest.php`](../../tests/Integration/AgendaV2/ClinicalEnterpriseConcurrencyTest.php)
- [`tests/JavaScript/admission-row-colors-main.test.js`](../../tests/JavaScript/admission-row-colors-main.test.js)
- [`tests/JavaScript/admission-row-colors.test.js`](../../tests/JavaScript/admission-row-colors.test.js)
- [`tests/JavaScript/clinical-enterprise.test.js`](../../tests/JavaScript/clinical-enterprise.test.js)
- [`tests/Support/admission-colors-browser.cjs`](../../tests/Support/admission-colors-browser.cjs)
- [`tests/Support/clinical-enterprise-browser.cjs`](../../tests/Support/clinical-enterprise-browser.cjs)
- [`tests/Support/clinical-enterprise-followup-filters.cjs`](../../tests/Support/clinical-enterprise-followup-filters.cjs)
- [`tests/Support/clinical-enterprise-visual.cjs`](../../tests/Support/clinical-enterprise-visual.cjs)

## Documentación y evidencia

- `INVESTIGACION.md`: matriz previa y riesgos.
- `IMPLEMENTACION.md`: decisiones, roles efectivos y separación de ciclos.
- `DESPLIEGUE-Y-ROLLBACK.md`: tres migraciones, conservación de auditoría y publicación futura sujeta a autorización.
- `qa/README.md`, informes y capturas: resultados reales sobre bases aisladas.

Los scripts de inicio, parada y restauración, la guía de credenciales y las copias SQL de la demo están en `storage/app/demo-enterprise`, excluidos de Git. No se publican contraseñas ni configuraciones locales.
