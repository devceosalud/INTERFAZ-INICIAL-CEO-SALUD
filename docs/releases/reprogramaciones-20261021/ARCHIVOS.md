# Archivos y alcance exacto

12 archivos de ejecución (11 modificados y un CSS nuevo). Se preparan como un conjunto coherente.

- `app/Http/Controllers/Scheduling/AdmissionBoardController.php`
- `app/Http/Controllers/Scheduling/AgendaFeedController.php`
- `app/Services/Scheduling/AdmissionBoardService.php`
- `app/Services/Scheduling/RescheduleHistory.php`
- `public/css/scheduling/agenda-reschedule-history.css`
- `public/js/scheduling/admission-board.js`
- `public/js/scheduling/agenda-day-grid.js`
- `public/js/scheduling/agenda-reschedule-history.js`
- `public/js/scheduling/agenda.js`
- `resources/views/scheduling/admission/index.blade.php`
- `resources/views/scheduling/agenda/index.blade.php`
- `routes/scheduling.php`

6 archivos de pruebas/fixtures y 2 herramientas para QA/verificación. No se incluyen en el artefacto de ejecución de producción.

- `tests/Concerns/BuildsRescheduleVisualData.php`
- `tests/Feature/Scheduling/RescheduleVisualHistoryTest.php`
- `tests/JavaScript/reschedule-visual-history.test.js`
- `tests/Support/RescheduleVisualQa.php`
- `tests/Support/reschedule-visual-browser-router.php`
- `tests/Support/reschedule-visual-browser-seed.php`
- `tools/qa/browser-reschedule-visual.cjs`
- `tools/release/reschedule-preflight.php`

Documentación, fingerprints y capturas: este directorio. La relación exhaustiva de archivos, estado Git y SHA-256 está en `MANIFIESTO.json` del ZIP. El diff completo incluye también pruebas/documentación; `diff-aplicacion.patch` contiene exclusivamente los 12 archivos anteriores.

Sin cambios de base de datos, configuración, dependencias, permisos ni código financiero. La clase `RescheduleAppointmentService` permanece idéntica al commit base.
