# Archivos de la integración y justificación

La lista concreta de archivos y motivos de las cinco correcciones manuales posteriores está en [OBSERVACIONES-PR70.md](OBSERVACIONES-PR70.md#archivos-de-esta-corrección).

Respecto de main `a57b2d04`. Los scripts temporales de investigación y los datos/credenciales locales de QA no se publican.

| Archivo | Justificación |
|---|---|
| `app/Http/Controllers/Scheduling/AdmissionBoardController.php` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `app/Http/Controllers/Scheduling/AgendaFeedController.php` | Historial de reprogramación rojo, visible y paginado; sin ocupar capacidad. |
| `app/Http/Controllers/Scheduling/AppointmentEconomicEditController.php` | Corrección económica revisada y auditada; proteger caja, pagos y documento fiscal concurrentes. |
| `app/Http/Controllers/admin/master/role/RoleController.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Http/Controllers/admin/master/user/UserController.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Http/Controllers/admin/master/user/UserDoctorLinkController.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Http/Livewire/CashierShifts.php` | Corrección económica revisada y auditada; proteger caja, pagos y documento fiscal concurrentes. |
| `app/Http/Livewire/Sales.php` | Corrección económica revisada y auditada; proteger caja, pagos y documento fiscal concurrentes. |
| `app/Models/Doctor.php` | Dependencia interna indispensable de las cinco recuperaciones. |
| `app/Models/User.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Services/Billing/AppointmentEconomicPosition.php` | Corrección económica revisada y auditada; proteger caja, pagos y documento fiscal concurrentes. |
| `app/Services/Scheduling/AdditionalRateCharge.php` | Corrección económica revisada y auditada; proteger caja, pagos y documento fiscal concurrentes. |
| `app/Services/Scheduling/AdmissionBoardService.php` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `app/Services/Scheduling/AdmissionCareService.php` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `app/Services/Scheduling/AdmissionExonerationConfirmation.php` | Confirmación excepcional sin adelanto, separada de pago y exoneración. |
| `app/Services/Scheduling/AppointmentEconomicEdit.php` | Corrección económica revisada y auditada; proteger caja, pagos y documento fiscal concurrentes. |
| `app/Services/Scheduling/AppointmentManagement.php` | Dependencia interna indispensable de las cinco recuperaciones. |
| `app/Services/Scheduling/AppointmentReevaluationService.php` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `app/Services/Scheduling/EconomicCorrectionSnapshot.php` | Corrección económica revisada y auditada; proteger caja, pagos y documento fiscal concurrentes. |
| `app/Services/Scheduling/ExistingAppointmentPricing.php` | Corrección económica revisada y auditada; proteger caja, pagos y documento fiscal concurrentes. |
| `app/Services/Scheduling/LegacyAppointmentMutation.php` | Cerrar atajo de edición heredada que podía simular inicio/finalización clínica. |
| `app/Services/Scheduling/ManualReevaluationService.php` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `app/Services/Scheduling/OperationalAgendaAppointmentService.php` | Dependencia interna indispensable de las cinco recuperaciones. |
| `app/Services/Scheduling/OperationalProcedureService.php` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `app/Services/Scheduling/RescheduleHistory.php` | Historial de reprogramación rojo, visible y paginado; sin ocupar capacidad. |
| `app/Services/Scheduling/ReservationPaymentService.php` | Confirmación excepcional sin adelanto, separada de pago y exoneración. |
| `app/Services/Scheduling/UnlinkedDoctorAdmissionFallback.php` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `app/Services/UserDoctorLink.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Support/Authorization/PermissionDisplay.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Support/Scheduling/AdmissionAccess.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Support/Scheduling/AgendaAppointmentPresentation.php` | Dependencia interna indispensable de las cinco recuperaciones. |
| `app/Support/Scheduling/AgendaLegend.php` | Dependencia interna indispensable de las cinco recuperaciones. |
| `app/Support/Scheduling/ClinicalAccess.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Support/Scheduling/ClinicalCapability.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Support/Scheduling/MvpPermissionBootstrap.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `app/Support/Scheduling/SchedulingCapability.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `config/scheduling.php` | Contingencia administrativa explícita para médico sin vínculo; deshabilitada por defecto. |
| `database/migrations/2026_10_21_200000_define_direct_appointment_economic_permissions.php` | Conservar migración histórica aplicada; fuente idéntica al #69, sin tabla nueva. |
| `database/migrations/2026_10_21_210000_link_users_to_doctors.php` | Conservar migración histórica aplicada; fuente idéntica al #69, sin tabla nueva. |
| `database/migrations/2026_10_21_210100_define_medical_role_and_permissions.php` | Conservar migración histórica aplicada; fuente idéntica al #69, sin tabla nueva. |
| `docs/recuperacion-cinco/ARCHIVOS-INTEGRACION.md` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/INTEGRACION-MAIN-68.md` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/RESULTADOS-INTEGRACION.md` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/colors/browser-color-matrix.json` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/colors/fixed-current-css-desktop.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/colors/fixed-current-css-mobile.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/colors/fixed-current-css-paid-desktop.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/colors/fixed-current-css-paid-mobile.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/economy-integration/02-revision-economica.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/economy-integration/03-auditoria-economica.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/economy-integration/04-horario-original.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/economy-integration/05-cita-vigente.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/economy-integration/06-vinculacion-en-editor.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/economy-integration/browser-results.json` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/browser-flow.json` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/data-integrity.json` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/declined-mobile.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/episode.log` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/javascript.log` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/journey.log` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/mariadb.log` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/migration-source.json` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/ready-desktop.png` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/regression.log` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/results.json` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/sqlite.log` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `docs/recuperacion-cinco/qa/integration/targeted.log` | Matriz, evidencia ficticia reproducible, resultados o plan operativo. |
| `phpunit.agenda-v2-mariadb.xml` | Dependencia interna indispensable de las cinco recuperaciones. |
| `public/css/scheduling/admission-board.css` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `public/css/scheduling/agenda-reschedule-history.css` | Historial de reprogramación rojo, visible y paginado; sin ocupar capacidad. |
| `public/js/admin/master/user/doctor-link.js` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `public/js/scheduling/admission-board.js` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `public/js/scheduling/agenda-appointment-indicators.js` | Integrar controles y presentación recuperados en la Agenda/editor existentes. |
| `public/js/scheduling/agenda-day-grid.js` | Integrar controles y presentación recuperados en la Agenda/editor existentes. |
| `public/js/scheduling/agenda-manage-appointment.js` | Integrar controles y presentación recuperados en la Agenda/editor existentes. |
| `public/js/scheduling/agenda-reschedule-history.js` | Historial de reprogramación rojo, visible y paginado; sin ocupar capacidad. |
| `public/js/scheduling/agenda.js` | Integrar controles y presentación recuperados en la Agenda/editor existentes. |
| `resources/views/admin/user/edit.blade.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `resources/views/admin/user/index.blade.php` | Vínculo y autorización médica verificables en editor existente; conservar grants históricos. |
| `resources/views/scheduling/admission/index.blade.php` | Integrar las etapas del #68, roles mínimos, historial y fondo completo en Admisión. |
| `resources/views/scheduling/agenda/index.blade.php` | Integrar controles y presentación recuperados en la Agenda/editor existentes. |
| `resources/views/templates/sidebar.blade.php` | Integrar controles y presentación recuperados en la Agenda/editor existentes. |
| `routes/administrador.php` | Conservar rutas #68; añadir acciones recuperadas dentro de pantallas existentes y sus guardas. |
| `routes/scheduling.php` | Conservar rutas #68; añadir acciones recuperadas dentro de pantallas existentes y sus guardas. |
| `tests/Concerns/BuildsAdmissionColorData.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Concerns/BuildsRescheduleVisualData.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Admin/DoctorCatalogIdentityCompatibilityTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Authorization/UserDoctorLinkTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Baseline/ApplicationAndAuthenticationSmokeTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/AdmissionBoardTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/AdmissionColorCompatibilityTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/AdmissionWithoutDepositTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/AgendaV2ProceduresTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/AppointmentEconomicEditTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/IntegratedAdmissionJourneyTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/JulioAdmissionReevaluationTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/MvpGuardNormalizationTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/MvpPermissionBootstrapTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/RescheduleVisualHistoryTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/SchedulingMvpFoundationTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Feature/Scheduling/UnifiedReevaluationTest.php` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Integration/MvpEconomyDirect/AdmissionEpisodeConcurrencyTest.php` | Regresión de concurrencia con conexiones MariaDB independientes y BD propia. |
| `tests/Integration/MvpEconomyDirect/EconomicEditConcurrencyTest.php` | Regresión de concurrencia con conexiones MariaDB independientes y BD propia. |
| `tests/JavaScript/admission-row-colors.test.js` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/JavaScript/agenda-quick-management.test.js` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/JavaScript/appointment-economic-edit.test.js` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/JavaScript/doctor-link-native-select.test.js` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/JavaScript/reschedule-visual-history.test.js` | Regresión o actualización de fixture al esquema y contrato de roles vigente; sin eliminar pruebas de main. |
| `tests/Support/IsolatedAgendaV2Qa.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/RescheduleVisualQa.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/admission-colors-browser-seed.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/admission-colors-browser.cjs` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/admission-episode-worker.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/direct-economic-worker.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/integrated-admission-browser-seed.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/integrated-admission-browser.cjs` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/integration-browser-verify.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/integration-economy-browser-seed.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/integration-economy-browser.cjs` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/reschedule-visual-browser-router.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
| `tests/Support/reschedule-visual-browser-seed.php` | Soporte de QA aislado; rutas locales, identidad de BD y cohortes ficticias. |
