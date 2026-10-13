# Precios y Agenda — entrega separada de PR #5

Base: fc4f278cd142824183ee6f99e8b8519a3f58098e, rama codex/entrega-urgente-1-controles-heredados. Rama: codex/mvp-precios-agenda.

Cambios: descuentos fijos/porcentaje al registrar, calculados y auditados en servidor en Agenda y Admisión; mínimo 50% neto; snapshots aditivos de catálogo/descuento/actor; postoperatorio por cita sin gratuidad; motivo de exoneración obligatorio con permiso existente. UI mantiene cuotas asíncronas vigentes y deshabilita cambios económicos en citas almacenadas. R25 posterior a cobro se conserva pendiente.

Migración nueva: 2026_10_13_180000_add_appointment_price_snapshot. Agrega columnas nullable y FK; no backfill ni modificación de históricos. Ejecutada exclusivamente en QA descartable. Publicación exige autorización de migración aditiva, backup y restauración probada. Rollback de código debe conservar columnas/datos; down rechaza si hay snapshots económicos o datos nuevos.

Pruebas reproducibles: PHP 8.3.35 con phpunit.xml (SQLite :memory:); phpunit.rules.xml con MariaDB 11.8.9 loopback33320/ceosalud_qa_rules y guard IsolatedAgendaQa (verifica puerto, database y datadir antes de operaciones de testing); node --test tests/JavaScript/*.test.js. No ejecutar fixtures ni RefreshDatabase contra otro DB.

Pendientes: nueva política R05 explícita aún no implementada en este PR; no publicar automáticamente la inferencia anterior como nueva regla aprobada. UI de navegador de los campos nuevos no certificada. Quiroz/sede requieren catálogo acotado. Clarity conservado; privacidad de datos sensibles continúa como gate. PR #5 no cambiado y no se realizaron operaciones productivas.
