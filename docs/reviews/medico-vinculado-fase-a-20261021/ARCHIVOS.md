# Archivos exactos del incremento respecto a a8bd76d

Total: 97 archivos; aplicación/migraciones/interfaz 32, pruebas/herramientas 13, documentación/evidencias 52. Todos añadidos o modificados; no se eliminan archivos. La explicación funcional y las intervenciones compartidas están en ENTREGA.md.

## Aplicación, migraciones e interfaz

- `app/Http/Controllers/Scheduling/MedicalBoardController.php`
- `app/Http/Controllers/Scheduling/OperationalProcedureController.php`
- `app/Http/Controllers/admin/master/role/RoleController.php`
- `app/Http/Controllers/admin/master/user/UserController.php`
- `app/Http/Controllers/admin/master/user/UserDoctorLinkController.php`
- `app/Http/Controllers/authenticator/auth/AuthController.php`
- `app/Http/Middleware/RedirectIfAuthenticated.php`
- `app/Models/Doctor.php`
- `app/Models/User.php`
- `app/Services/Scheduling/AdmissionBoardService.php`
- `app/Services/Scheduling/AppointmentReevaluationService.php`
- `app/Services/Scheduling/ManualReevaluationService.php`
- `app/Services/Scheduling/MedicalBoardService.php`
- `app/Services/Scheduling/MedicalCareService.php`
- `app/Services/Scheduling/OperationalProcedureService.php`
- `app/Services/UserDoctorLink.php`
- `app/Support/Authorization/PermissionDisplay.php`
- `app/Support/Scheduling/ClinicalAccess.php`
- `app/Support/Scheduling/ClinicalCapability.php`
- `app/Support/Scheduling/MvpPermissionBootstrap.php`
- `database/migrations/2026_10_21_210000_link_users_to_doctors.php`
- `database/migrations/2026_10_21_210100_define_medical_role_and_permissions.php`
- `public/css/scheduling/medical-board.css`
- `public/js/admin/master/user/doctor-link.js`
- `public/js/scheduling/admission-board.js`
- `public/js/scheduling/medical-board.js`
- `resources/views/admin/user/doctor-link.blade.php`
- `resources/views/admin/user/index.blade.php`
- `resources/views/scheduling/medical/index.blade.php`
- `resources/views/templates/sidebar.blade.php`
- `routes/administrador.php`
- `routes/scheduling.php`

## Pruebas y herramienta de navegador

- `tests/Feature/Admin/DoctorCatalogIdentityCompatibilityTest.php`
- `tests/Feature/Authorization/MedicalAccessCompatibilityTest.php`
- `tests/Feature/Authorization/UserDoctorLinkTest.php`
- `tests/Feature/Scheduling/MvpGuardNormalizationTest.php`
- `tests/Feature/Scheduling/MvpPermissionBootstrapTest.php`
- `tests/Feature/Scheduling/SchedulingMvpFoundationTest.php`
- `tests/Feature/Scheduling/VerifiedMedicalCareTest.php`
- `tests/Integration/AgendaV2/MedicalIdentityConcurrencyTest.php`
- `tests/JavaScript/medical-actions.test.js`
- `tests/Support/medical-browser-inspect.php`
- `tests/Support/medical-browser-seed.php`
- `tests/Support/medical-care-worker.php`
- `tools/qa/browser-verified-medical-care.cjs`

## Documentación y evidencias

- `docs/reviews/medico-vinculado-fase-a-20261021/ARCHIVOS.md`
- `docs/reviews/medico-vinculado-fase-a-20261021/DESPLIEGUE_Y_ROLLBACK.md`
- `docs/reviews/medico-vinculado-fase-a-20261021/DIAGNOSTICO.md`
- `docs/reviews/medico-vinculado-fase-a-20261021/ENTREGA.md`
- `docs/reviews/medico-vinculado-fase-a-20261021/MATRIZ_ANTES_DESPUES.md`
- `docs/reviews/medico-vinculado-fase-a-20261021/RESULTADOS.md`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/README.md`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/REVISION_LOCAL.json`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-before-doctor-crud.txt`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-before-doctor-crud.xml`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-before-full.txt`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-before-full.xml`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-browser-final.txt`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-final-mariadb.txt`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-final-mariadb.xml`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-final-source-index.json`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-final-sqlite.txt`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-final-sqlite.xml`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-javascript-verified.txt`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-lint.json`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-protected-source-proof.json`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-regression-matrix.json`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medical-visual-regression.txt`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/01-antes-cuenta-existente.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/02-despues-vinculo-auditado.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/03-permisos-medicos-minimos.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/04-antes-llegada-medico.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/05-inicio-medico-desktop.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/05a-listo-manual-sin-procedimientos.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/05b-indicacion-retirada-historial.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/06-pendiente-medico-desktop.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/07-pendiente-medico-mobile.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/08-pendiente-admision-desktop.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/09-pendiente-admision-mobile.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/10-listo-admision-desktop.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/11-listo-admision-mobile.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/12-finalizacion-medica.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/13-medico-sin-vinculo-403.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/14-revocacion-historial.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/browser-results.json`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/medico/database-invariants.json`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/01-antes-agenda-desktop.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/02-despues-agenda-desktop.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/03-despues-agenda-mobile.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/04-admision-historial-desktop.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/05-admision-historial-mobile.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/06-admision-cita-vigente.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/07-semana.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/08-mes.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/09-intervalo-reutilizado.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/10-destino-vigente.png`
- `docs/reviews/medico-vinculado-fase-a-20261021/evidencias/reprogramaciones/browser-results.json`

No cambia .github/workflows, composer.json/lock, package.json/lock, configuración de pagos ni IDs/campos de citas. El diff binario exacto se genera después del commit fuera de los archivos versionados para evitar autorreferencia. El SHA definitivo acompaña la entrega.
