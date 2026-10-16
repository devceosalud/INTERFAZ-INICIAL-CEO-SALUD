# Plan de despliegue y rollback — NO ejecutado

Esta entrega es un PR Draft para revisión. No autoriza ni ejecuta publicación productiva, merge, SSH, cambios en Hostinger, acceso a su BD, migraciones ni sincronización de archivos productivos. Solo se envía la rama de trabajo para crear el Draft solicitado. Los workflows inspeccionados realizan comprobaciones de PR; el workflow nuevo también es exclusivamente de QA.

## Revisión antes de autorizar una liberación

1. Revisar ANALISIS_FUNCIONAL, MATRIZ_PERMISOS, ARCHIVOS_MODIFICADOS, la migración y el diff del Draft.
2. Confirmar baseline productivo por un procedimiento autorizado independiente. El reporte del usuario y origin/main no lo prueban.
3. Verificar CI del SHA candidato, pruebas locales y aceptación de los ocho flujos en fixtures, con ambos tamaños de pantalla.
4. Acordar una ventana de mantenimiento breve, responsables de validación y de reversión. Los pagos nuevos se confirman automáticamente; no existe una conversión masiva.
5. Comprobar backup productivo completo de BD y archivos privados, integridad y restauración conforme al plan operativo de la clínica. Los respaldos sintéticos de QA no sustituyen este paso.
6. Revisar privilegios administrativos y decidir si algún ADMINISTRADOR recibirá appointment.operational_reevaluation.supervise. No conceder a todos por defecto. ADMISION/COMERCIAL deberán crear por Agenda operativa.
7. Comprobar mecanismos externos de publicación/webhooks de Hostinger por un operador autorizado: no están acreditados solo por inspeccionar workflows Git. No activar despliegue automático de esta rama.

## Orden futuro seguro

- Registrar SHA de código y versión de esquema actuales. Pausar escrituras durante la publicación autorizada.
- Aplicar únicamente la migración aditiva nueva, tras verificar que no existen tablas homónimas de otra implementación y que todas las migraciones del baseline ya están aplicadas.
- Verificar claves/índices y ausencia de modificaciones a filas históricas; no ejecutar migrate:fresh, seeders de QA ni scripts bajo tests/Support en producción.
- Publicar el SHA aprobado con PHP/dependencias compatibles y archivos JS/Blade juntos. Mantener URLs versionadas por contenido y limpiar/reconstruir cachés conforme al procedimiento existente.
- Validar sesiones de ADMINISTRADOR, ADMISION y COMERCIAL: reserva sin pago; nuevo adelanto con confirmación única; selección confirmada; realizado parcial/manual; todos realizados naranja en ambas agendas; cola ordenada; reversión no final; HTTP directo prohibido.
- Probar Maestro Profesional / Servicio con un profesional MFR ficticio: cambiar desde Traumatología, seleccionar Consulta MFR, reabrir conservando el servicio, activar/retirar la excepción y comprobar el bloqueo de Traumatología. Confirmar que Caja, OCR/documentos privados, exoneraciones, adicionales, fuera de horario, precios y acreditaciones clínicas mantienen sus reglas. Verificar que Clarity no se carga en páginas excluidas.
- Reanudar escrituras solo tras aprobación funcional. Monitorear 403/409/422 y errores sin incluir datos clínicos/financieros en analítica.

## Reversión sin pérdida

Preferir rollback **solo de código** al baseline aprobado, manteniendo las tres tablas nuevas y todos sus datos/eventos. El baseline ignora esas tablas. La cola dejará de mostrarse con el código anterior, pero conservará trazabilidad para recuperar la funcionalidad. Las citas ya confirmadas por pagos v2 permanecerán confirmadas: no desconfirmar, devolver ni duplicar cobros automáticamente.

No ejecutar migrate:rollback sobre tablas pobladas: down lo bloquea expresamente. No borrar solicitudes, auditoría, citas ni pagos para forzar la reversión. No restaurar indiscriminadamente un backup que elimine operaciones posteriores; cualquier reparación requerirá conciliación específica y autorización independiente. Si otra versión cambia el esquema, comprobar su compatibilidad antes de revertir código.

Si se detecta un fallo financiero, suspender nuevas operaciones afectadas y preservar evidencia; la cola operativa puede retirarse de la interfaz sin tocar cobros. Una inconsistencia clínica no autoriza reabrir ATENDIDO ni modificar indicaciones. Registrar incidentes y validación final del operador responsable.
