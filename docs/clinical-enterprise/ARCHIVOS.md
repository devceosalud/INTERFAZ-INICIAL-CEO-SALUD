# Archivos modificados

Comparación contra `main` `a4e2a9b0f9e517c55be10232321391b1dc970e73`. La composición de Agenda operativa permanece igual. Las excepciones expresamente solicitadas en el refinamiento son el indicador amarillo EXONERADA compartido y la mejora global del selector de médico; no cambian sus vistas ni CSS propios. Las modificaciones compartidas de procedimientos se limitan a identidad, seguimiento y permisos solicitados; su representación en Operativa permanece igual.

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
- `DESPLIEGUE-Y-ROLLBACK.md`: cuatro migraciones, conservación de auditoría y publicación futura sujeta a autorización.
- `qa/README.md`, informes y capturas: resultados reales sobre bases aisladas.

Los scripts de inicio, parada y restauración, la guía de credenciales y las copias SQL de la demo están en `storage/app/demo-enterprise`, excluidos de Git. No se publican contraseñas ni configuraciones locales.

## Archivos añadidos o adaptados en el refinamiento

- `app/Support/Scheduling/ClinicalOrderSpecification.php`
- `app/Support/Scheduling/MedicalFollowupAccess.php`
- `config/medical_followups.php`
- `database/migrations/2026_10_23_040000_preserve_clinical_order_details.php`
- `public/js/scheduling/admission-layout.js`
- `public/js/scheduling/clinical-order-fields.js`
- `public/js/doctor-search.js`
- `public/css/doctor-search.css`
- `resources/views/layouts/app.blade.php`
- `app/Support/Scheduling/AgendaAppointmentPresentation.php`
- `app/Support/Scheduling/AgendaLegend.php`
- `public/js/scheduling/agenda-appointment-indicators.js`
- `tests/Feature/Scheduling/ClinicalEnterpriseRefinementTest.php`
- `tests/JavaScript/clinical-enterprise-refinement.test.js`
- `tests/Concerns/BuildsAdmissionColorData.php`
- `tests/Support/clinical-enterprise-refinement-browser.cjs`
- `tests/Support/clinical-enterprise-popup-responsive.cjs`

Los controladores/servicios de procedimientos conservan el endpoint y suman validación/persistencia opcional de atributos y cabecera privada. El controlador/servicio de seguimiento y AdmissionRolePolicy aplican la restricción personal solicitada. Las vistas/CSS existentes de Admisión y seguimiento incorporan los ajustes visuales; no hay pantallas adicionales en esta revisión. La comparación completa del commit describe también las evidencias actualizadas.
