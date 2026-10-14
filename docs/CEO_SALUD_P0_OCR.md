# CEO Salud — P0-B: OCR y comprobantes de pagos
14/10/2026. Desarrollo sobre PR #9, rama codex/mvp-pagos-ocr. Sin merge, despliegue, cambios productivos ni modificación de Clarity.

## Solución elegida y alternativas

|Opción|Viabilidad y dependencias|Privacidad / coste / precisión|
|---|---|---|
|Tesseract.js 7 en navegador, recursos propios — implementada y probada|Worker + WASM + modelo español publicados como archivos estáticos. No necesita Tesseract nativo, proc_open web, Node ni instalación de paquetes en Hostinger.|El motor procesa el archivo local; no lo envía a un proveedor OCR. Sin tarifa por consulta; utiliza CPU del dispositivo y transferencia de recursos. Recursos completos ~28 MB, se carga la variante necesaria. Precisión depende de imagen y dispositivo; revisión obligatoria.|
|Ejecutable nativo en hosting — no utilizado|Producción confirmó tesseract ausente de PATH. Binario Linux compatible, librerías, permisos y proc_open web siguen sin demostrarse. No se instala nada.|Procesaría en servidor, con controles de procesos y recursos. No probado en Hostinger; no es dependencia del MVP elegido.|
|API externa — no integrada ni probada|Necesitaría cuenta, claves, presupuesto, red y autorización de tratamiento. No se enviaron comprobantes reales ni ficticios a OCR SaaS.|Añade otro destinatario de datos. Por ejemplo, Azure documenta almacenamiento temporal de entrada/resultados durante 24 horas; requiere evaluación específica. No se presume mayor precisión ni coste aprobado.|

Fuentes primarias: [instalación local Tesseract.js](https://github.com/naptha/tesseract.js/blob/master/docs/local-installation.md), [datos lingüísticos](https://github.com/naptha/tessdata), [requisitos nativos](https://tesseract-ocr.github.io/tessdoc/Installation.html), [tratamiento de datos Azure](https://learn.microsoft.com/en-us/azure/foundry/responsible-ai/document-intelligence/data-privacy-security). Se inspeccionó también el loader real 7.0.0: selecciona entre seis variantes core; se distribuyen todas. Licencias, procedencia y SHA-256 quedan junto a los recursos.

**La ausencia de envío a un proveedor OCR no demuestra privacidad del ERP completo. Clarity continúa instalado e intacto; su alcance sobre datos sensibles sigue pendiente, sin autorización para limitarlo. El masking por sí solo no resuelve esa revisión.**

## Comportamiento real

- OCR sugiere monto, operación etiquetada, entidad y, cuando reconoce el formato, fecha/hora. No muestra ni registra texto bruto. No usa DNI/teléfono/fecha como operación de respaldo.
- Sugerencias editables; copiarlas al pago requiere acción expresa y no registra dinero. La revisión humana final se desmarca al editar los datos. Cancelar OCR conserva archivos y permite completar manualmente; PDF se revisa manualmente.
- Cada transferencia conserva importe y operación propios. Hasta cinco capturas pueden respaldar el mismo pago (8 MB por archivo, 20 MB total); otras transferencias se registran por separado. La cita puede acumular varios pagos.
- Ambos formularios usan los servicios financieros existentes. Los adjuntos se guardan privados, ligados a cita y payment_id, con responsable, momento y fuente de revisión. bank_validated permanece false: una imagen no prueba recepción de dinero.
- Hash SHA-256 único global para comprobantes financieros nuevos, incluso ocultos; se conserva también la unicidad de operación bancaria existente. Reintento con misma UUID no vuelve a cobrar. Un duplicado no revela dueño/cita.
- Transacción cubre dinero, asociaciones y auditoría; errores eliminan archivos del intento fallido. Deadlocks tienen tres intentos limitados con la misma UUID y limpieza por intento; agotarlos devuelve 409 genérico. La carrera con cajas/médicos/operadores distintos queda cubierta.
- Etiquetar u ocultar evidencia financiera deja auditoría; ocultar conserva archivo, hash y pago. Las evidencias médicas siguen separadas: no reciben deduplicación financiera.
- Formularios heredados conservan rutas/campos. Se corrigieron el archivo no enviado, dependencia de validación ausente, selector TRANSFERENCIA y sincronización visual del método. Se retiró el OCR bruto/log de resultado del flujo sustituido.

## Cambios y datos

AppointmentPaymentProofs centraliza reglas; AppointmentProofStorage y los servicios de registro/pagos comparten almacenamiento y transacción. AppointmentDocument/Controller agregan asociación, revisión y auditoría. El lector/revisor JavaScript se reutiliza en Agenda y las seis pantallas heredadas; no hay módulo financiero paralelo.

Migración aditiva: 2026_10_14_080000_link_reviewed_payment_proofs. Añade payment_id, payment_proof_hash único nullable, reviewed_by_user_id, reviewed_at y receipt_review a appointment_documents. FKs con restricción de borrado. No backfill, seeders, permisos nuevos ni actualización de importes/estados históricos. Las imágenes históricas sin hash no se atribuyen ni se presentan como deduplicadas automáticamente.

## Evidencia ejecutada

- PHP 8.3.35, SQLite aislado: **809 pruebas / 6.222 aserciones**, aprobadas.
- PHP 8.3.35 / MariaDB 11.8.9 aislada: **299 pruebas / 3.438 aserciones**, aprobadas, incluyendo carrera real de comprobantes y mínimo/privacidad/idempotencia previos.
- JavaScript: **239/239**, aprobadas. Revisión, invalidación tras editar, cancelación/respuesta obsoleta, PDF manual y dependencias de seis consumidores.
- npm ci --ignore-scripts y reconstrucción local del bundle; **13 recursos verificados SHA-256**; npm audit: **0 vulnerabilidades comunicadas** al ejecutar.
- Motor real en navegador, imágenes sintéticas: Yape S/75 + operación/entidad/fecha/hora; BCP S/250; importes/operaciones ambiguos quedan vacíos; imagen ilegible devuelve campos vacíos. Lecturas locales ~0,2–0,9 s en este equipo; no es benchmark de Hostinger ni móviles.
- Smoke Agenda: OCR no cambió el importe hasta usar sugerencias; sin revisión se bloqueó; guardó reserva con S/75 y dos capturas del mismo pago sin confirmación automática.
- Smoke heredado: BCP S/50/operación/método visibles; intento S/49,99 rechazado; con S/50 y revisión confirmó. SELECT QA antes/después: pasó de 1 pago S/75 y 2 documentos a 2 pagos S/125 y 3 documentos. Cita histórica exacta sin cambios; todos los archivos privados existen, vinculados al pago y al responsable.

Los primeros intentos detectaron errores de integración y un deadlock: están corregidos y las suites finales anteriores pasaron. La instancia MariaDB se recuperó sin reinicializar su datadir tras una interrupción; el bootstrap verificó versión, puerto y datadir antes de pruebas destructivas. Los mocks de reintento MariaDB se ejecutan sin una transacción artificial exterior para reproducir HTTP. Logs/readbacks/screenshot quedan ignorados en storage/app/qa-rules. No se declaran pruebas productivas.

## Publicación, smoke y reversión — solo con autorización

1. Revisar cadena #5 → #6 → #7 → #8 → #9 → PR OCR. Mantener Draft mientras falten condiciones. Confirmar SHA/autodespliegue; respaldo consistente de BD, archivos y adjuntos privados, con restauración aislada comprobada.
2. Revisar lista de migraciones pendientes y aplicar únicamente el plan autorizado; este diff añade la migración citada. No ejecutar migrate:fresh ni seeders QA en producción.
3. Desplegar recursos estáticos completos; node tools/appointment-ocr/vendor-assets.mjs --verify comprueba integridad en entorno con Node. En Hostinger no se exige Node: comparar los checksums y probar descarga/ejecución en staging.
4. Verificar en PHP web upload_max_filesize, post_max_size y max_file_uploads: deben permitir el tamaño anunciado (8 MB individual, 20 MB total más formulario). Comprobar worker, gzip/modelo, WASM y CSP en navegador. No modificar configuración sin autorización.
5. Staging equivalente/ficticio: Yape/BCP, ambiguo, ilegible, PDF/manual, interrupción y dos archivos del mismo pago; adelanto <50 sin confirmación, confirmar con 50, UUID repetida, duplicado de archivo/operación, usuario sin permiso, descarga privada y cita histórica. Cerrar sesiones/pestañas antiguas y cargar activos actualizados.
6. Rollback: detener nuevas escrituras, volver al SHA previo compatible y conservar BD/adjuntos. No ejecutar down/rollback de esta migración tras registrar evidencia; no restaurar una BD antigua sobre pagos posteriores. Conciliar operaciones y documentos creados durante la ventana antes de reabrir.

Pendientes reales: smoke Hostinger, configuración web/CSP, calidad con más fixtures similares a capturas reales y dispositivos del personal, permisos/guards/modo piloto aprobados, respaldo/restauración y privacidad de Clarity. No se garantiza autenticidad de capturas ni detección de toda imagen editada. R20 permanece PARCIAL en la matriz hasta cerrar validación del entorno; no se declara listo para datos reales.
