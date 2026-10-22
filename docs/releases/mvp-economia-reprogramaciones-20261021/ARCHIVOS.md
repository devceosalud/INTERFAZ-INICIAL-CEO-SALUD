# Cambios incrementales respecto a 6719af5

El manifiesto del ZIP enumera todos los archivos y sus SHA-256; los dos diffs incluyen también las evidencias binarias. Esta lista explica la necesidad de los cambios de código. Los documentos y capturas heredados de reprogramaciones no se modifican.

| Archivo | Cambio y necesidad |
|---|---|
| `app/Http/Controllers/Scheduling/AppointmentEconomicEditController.php` | Nuevo adaptador HTTP de lectura, revisión y guardado; respuestas privadas sin caché. |
| `app/Services/Scheduling/AppointmentEconomicEdit.php` | Edición directa con autorización, visibilidad, comparación firmada, validación financiera, transacción, bloqueos, idempotencia y auditoría existente. |
| `app/Services/Scheduling/ExistingAppointmentPricing.php` | Precio contractual/descuento/recargo de una cita existente; reutiliza AppointmentDiscount sin intervenir en nuevas cotizaciones. |
| `app/Services/Scheduling/AdditionalRateCharge.php` | Reutilización selectiva de aritmética de catálogo y vigencia; solo la invoca la edición de citas existentes. |
| `app/Services/Scheduling/EconomicCorrectionSnapshot.php` | Reutilización de instantáneas mínimas y hashes sin datos clínicos, privados o fiscales innecesarios. |
| `app/Services/Scheduling/AppointmentManagement.php` | Dos flags de autorización efectiva para habilitar las acciones de gestión. No cambia el resto del payload. |
| `app/Support/Scheduling/SchedulingCapability.php` | Dos capacidades de edición directa en el catálogo existente. |
| `app/Support/Authorization/PermissionDisplay.php` | Etiquetas/descripciones en el editor Roles actual. |
| `routes/scheduling.php` | Tres endpoints autenticados, con guardias MVP/Agenda existentes y límites de frecuencia; no modifica rutas visuales previas. |
| `public/js/scheduling/agenda-manage-appointment.js` | Conserva las cinco acciones anteriores, habilita Corregir adelanto según permiso y añade Modificar economía; revisión, cancelación, confirmación, recálculo y auditoría en el diálogo existente. No toca el formulario de creación. |
| `database/migrations/2026_10_21_200000_define_direct_appointment_economic_permissions.php` | Define dos permisos sin concederlos; sin cambios de esquema. `down` conserva datos. |

## Intervenciones compartidas imprescindibles

| Archivo | Razón y alcance | Comprobación |
|---|---|---|
| `app/Http/Livewire/CashierShifts.php` | El cierre congelaba un total leído antes del bloqueo. Ahora bloquea el turno propio abierto y recalcula usando exactamente la fórmula existente antes de cerrar. No cambia qué integra Caja. | Dos carreras reales cierre/edición en ambos órdenes; suite de Caja. |
| `app/Services/Billing/AppointmentEconomicPosition.php` | Una lectura consistente antigua de MariaDB no basta al validar/aplicar. Añade parámetro opcional `lock=false`; solo las correcciones solicitan lecturas actuales bloqueadas. Los reportes/lectores actuales usan el valor por defecto anterior. | Concurrencia real MariaDB, saldo/reportes y suite completa. |
| `app/Http/Livewire/Sales.php` | Ventas leía el carrito antes de esperar los bloqueos. Compara precio/abonado/saldo contra las filas bloqueadas y pide recargar si cambiaron. Convierte el rechazo de instantánea1020 de MariaDB en validación concreta. No cambia cálculos, emisión ni llamadas fiscales. | Dos carreras con lectura antigua y corrección posterior, pruebas de ventas/privacidad/medios bancarios y suite completa. |

## Pruebas y herramientas

- `tests/Feature/Scheduling/AppointmentEconomicEditTest.php`: 28 casos económicos, permisos, migración/editor y regresión de dinero.
- `tests/Integration/MvpEconomyDirect/EconomicEditConcurrencyTest.php` y `tests/Support/direct-economic-worker.php`: seis carreras con procesos/sesiones independientes, solo harness MariaDB desechable.
- `tests/JavaScript/appointment-economic-edit.test.js`: cinco casos de comparación, cancelación, bloqueo y protocolo de guardado.
- `tests/JavaScript/agenda-quick-management.test.js`: actualiza únicamente la lista esperada al añadir la sexta acción.
- `tests/Feature/Scheduling/SchedulingMvpFoundationTest.php`: catálogo esperado con dos nuevas capacidades; mantiene comprobación de no concesiones.
- `tests/Feature/Scheduling/MvpGuardNormalizationTest.php`: fixture anterior sin las dos definiciones nuevas; no modifica la normalización de producción.
- `tests/Support/mvp-economic-browser-seed.php`: cohorte sintética y administrador; permisos inicialmente sin conceder.
- `tools/qa/browser-mvp-economic.cjs`: siete escenarios con endpoints, Roles, autenticación/CSRF y controles de móvil reales.
- `docs/releases/mvp-economia-reprogramaciones-20261021/`: entrega, resultados, instalación/rollback, fingerprints y evidencias de esta ejecución.

La lista exacta de todo el cambio visual heredado está en el diff completo desde `1f840e9` y en `docs/releases/reprogramaciones-20261021/ARCHIVOS.md`. No se incorpora ninguna clase, modelo ni tabla de solicitudes económicas de la otra rama.
