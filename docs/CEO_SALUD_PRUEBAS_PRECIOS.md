# Evidencia ejecutada — precios y Agenda

13/10/2026. Base PR #5 fc4f278; QA sin pacientes reales.

- PHP 8.3.35 / SQLite aislado: **711 tests, 5298 assertions, OK**, suite completa.
- Node JavaScript: **208 tests, 208 pass, 0 fail/skip**, suite completa.
- Casos nuevos de descuentos: **15 tests, 134 assertions, OK** en SQLite y MariaDB 11.8.9, antes de suite completa.
- MariaDB 11.8.9 / QA33320 suite completa: EN EJECUCIÓN; no se declara aprobada.
- Navegador con controles nuevos: PENDIENTE.

Fallos iniciales corregidos: variable Blade canCreate inexistente (nombre real canCreateAppointments) y fixture autorizado de exoneración sin motivo. Suite PHP completa repetida después de corrección; resultado anterior no se presenta como aprobado. Logs privados locales storage/app/qa-rules; no contienen datos productivos.
