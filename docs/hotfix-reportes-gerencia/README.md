# Hotfix: reportes CSV gerenciales de Agenda operativa

**Base:** main 1ead9abc (19/10/2026). **Rama independiente:** hotfix/gerencia-export-csv-20261019.
No incluye Agenda 2.0 (PR #46), ni migra la BD ni modifica pagos.

## Alcance y contrato

Página: /scheduling-mvp/agenda/reportes — solo ADMINISTRADOR activo con appointment.mvp.access, appointment.view y appointment.audit.view (validar permisos efectivos).
Tres descargas por rango de hasta 31 días, sede y profesional:
- **Citas**: una fila por cita, incluye atención, pacientes y motivos, metadatos, montos y la proyección económica existente.
- **Pagos**: registros individuales atribuibles a citas; **no** se inventa una transacción si solo existe un snapshot heredado. Si un comprobante agrupa varias citas o no se puede atribuir el pago con seguridad, se marca para conciliación, sin duplicar dinero.
- **Slots**: disponibilidad derivada de DoctorAvailabilityService, una fila por intervalo, incluidos los libres y los días sin horario. No equivale a un reporte de pagos.

Formato: CSV UTF-8 BOM, delimitador «;»; abre en Excel configurado para Perú. Texto que empiece como fórmula se neutraliza. Un CSV no soporta múltiples hojas, formatos o gráficos nativos: importar y guardar como XLSX si gerencia necesita presentación avanzada.

Protección: scope vigente de visibilidad de citas (reservas pendientes ajenas excluidas), permisos de servidor, descargas sin caché, registro de auditoría por actor/filtros/tipo sin datos médicos, no se escriben CSV a disco, consultas por lotes. No enlazar estos endpoints desde API pública ni otorgarlos a COMERCIAL/ADMISION automáticamente.

## Verificación pendiente antes de publicar

1. Revisar SHA de main actual y estado Hostinger; comprobar si «Redistribuir» es manual.
2. PHP syntax check de todos los archivos tocados; PHPUnit y pruebas de permisos (ADMIN con auditoría, ADMIN sin auditoría, COMERCIAL, ADMISION y anónimo = denegados).
3. QA aislada: rango vacío, >31 días, múltiples médicos y sedes, reservadas privadas, canceladas, retiros, adicionales, fuera de horario y múltiples slots.
4. QA de economía: pago real, pago parcial, snapshot heredado, comprobantes anulados, ticket/boleta, pago ambiguo en ticket compartido, notas/créditos/reembolsos.
5. Comparar conteos con agenda del mismo periodo, comprobar importes y formato al abrir los tres CSV en Excel/LibreOffice; probar paciente con valor =HYPERLINK en motivo, nombre, etc.
6. Revisar límites de tiempo/memoria Hostinger en un periodo de 31 días con todos los médicos. Disminuir máximo si el volumen es excesivo.
7. Respaldos RECIENTES de archivos y BD (solo por política de reversión; sin cambios de esquema), planificar despliegue controlado y prueba restringida de descargar el archivo.
8. En rollback revertir solo archivos del hotfix o volver al artefacto previo verificado, **sin restaurar la base de datos**.

## Archivos

- app/Http/Controllers/Scheduling/ManagementExportController.php
- app/Support/Scheduling/ManagementCsv.php
- routes/scheduling.php
- resources/views/scheduling/agenda/index.blade.php
- resources/views/scheduling/agenda/management-report.blade.php
- tests/Unit/Scheduling/ManagementCsvTest.php

## Riesgos conocidos

El export contiene identificadores, contacto, motivos de consulta y finanzas; gestionar el CSV como **dato personal y médico**, con canales corporativos controlados, acceso mínimo y retención acordada. La vista detallada de dinero es una proyección calculada, no una conciliación bancaria final. No exponer claves ni PDF médicos. Falta prueba integrada en base de datos de QA y revisión humana previa a merge/deploy.
