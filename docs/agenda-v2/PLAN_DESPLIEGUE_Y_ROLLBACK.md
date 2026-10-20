# Plan de despliegue y rollback — NO ejecutado

Estado vigente de la continuación: [entrega exclusivamente local del20/10/2026](CONTINUACION_20261020.md). PR46 cerrado y sin fusionar; no publicar ni reabrir sin autorización. Los resultados anteriores son antecedentes.

Estado vigente: [cuarta ronda, gestión de citas, procedimientos personalizados y teleconsulta](RONDA_4_DIAGNOSTICO_Y_GUIA.md). Los resultados y decisiones anteriores se conservan como antecedentes.

## Candidato integrado con dcb9b2b

PR #46 continúa Draft. El baseline verificado aquí es GitHub dcb9b2b; no se verificó el commit instalado en Hostinger. Antes de liberar, comprobar instalación, backup restaurable y autorización por un procedimiento independiente.

Aplicar primero la migración aditiva v2, luego publicar backend/Blade/JS/CSS del mismo SHA. No ejecutar seeds/runtimes de QA ni conversiones masivas. Aceptar visualmente ATENDIDO/turquesa y retorno/naranja, COMERCIAL sin UPDATE, histórico v1, unión de orígenes, retirada independiente y consulta paginada de auditoría.

Rollback de código a main dcb9b2b conserva las tablas v2 y todos los eventos. El hotfix puede seguir leyendo marcas directas nuevas con su contrato v1; la cola por procedimientos quedará temporalmente sin UI, con historia preservada. No borrar tablas ni reinterpretar decisiones al revertir y no alterar pagos confirmados. Los planes anteriores se mantienen como antecedentes; no elegir 38a0446 para rollback si se necesita conservar la operación directa del hotfix.

## Candidato de segunda ronda

Se actualiza el mismo PR #46 y se conserva Draft. Ninguna liberación se ha ejecutado. Además de los pasos posteriores, exigir revisión humana de las capturas 1366×768, 1440×900, 1920×1080 y móvil; aceptar motivo obligatorio y registro único para todos los roles. Validar 2/0, 2/1 sin envío, 2/1 con autorización, 2/2, 3/2, 3/3, 0/0 y desmarcar un realizado desde otra sesión. Comparar las dos agendas y Nicol.

Esta ronda no requiere migración adicional; una instalación desde main aún requiere la migración aditiva de la primera ronda. Rollback de esta ronda a 58c5f92 conserva esquema y datos, pero restaura motivo opcional, mínimo anterior de observaciones y creador heredado de administrador. Esa consecuencia debe aprobarse si se revierte; no habilitarlo silenciosamente. Mantener tablas/auditoría y no revertir pagos. Para volver a main, aplicar la estrategia de conservación de las tres tablas descrita abajo.

El entorno manual local sigue disponible en 127.0.0.1:8092. Los runners de ronda 2 no lo detienen ni reinicializan el esquema del navegador; las pruebas PHP usan únicamente su esquema ficticio separado.

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

## Condiciones adicionales de tercera ronda

No ejecutar despliegue sin aprobación. Entregar los dos módulos PDF.js de la misma versión junto con licencia/procedencia; verificar MIME JavaScript, almacenamiento privado y límites PHP/proxy para 8 MB (POST mayor). El código anterior a esta ronda no conserva PATIENT_PROVIDED al reemplazar: durante rollback bloquear escrituras documentales y mantener un lector/versionado compatible, preservando BD y archivos nuevos. No volver ciegamente a 840522e ni borrar propósitos, auditorías o versiones. El runtime local se corrigió de 2 a 8 MB; no se modificó la configuración de Hostinger.

## Cuarta ronda: controles para una futura liberación autorizada

PR46 está cerrado, Draft y sin fusionar. Esta entrega es exclusivamente local; no hay publicación autorizada. Añadir al ensayo la migración de modalidad, etiqueta personalizada y preferencias; verificar defaults y FK, y publicar backend/Blade/JS/CSS del mismo SHA. Revalidar con datos ficticios teleconsulta, exoneración, otros procedimientos, documentos y roles antes de admitir tráfico. No se conoce ni se afirma el SHA o estado de Hostinger.

Rollback no borra columnas, tabla de preferencias, solicitudes, ledgers o archivos posteriores. Antes de volver a un código anterior, conservar lectura de c:UUID y política que impide presencia física en teleconsulta; congelar escrituras si no existe paquete compatible probado. Conservar también PATIENT_PROVIDED y su versionado. No restaurar pagos ni BD a una copia anterior para perder operaciones posteriores. El flujo remoto de atención sigue pendiente por decisión del usuario.

### Estado de preparación de esta continuación

Main integrado localmente:76e6ad2 (PR51/53/54/55); checkpoint9c78b3b y merge c598129. No hacer push ni reabrir PR46 sin autorización. La QA local final y su manifiesto de fuentes acreditan el código revisado; GitHub Actions de ese SHA requiere publicación posterior autorizada y permanece pendiente. Antes de una liberación, revalidar que main y entorno destino sigan en los SHAs/esquemas esperados; no se conoce su estado productivo real.

Desactivar el reloj de replay ficticio al terminar pruebas manuales: php tests/Support/agenda-v2-browser-clock.php --real-time, siempre en el worktree QA verificado. Ese reloj y sus credenciales no forman parte de una configuración productiva; no copiar storage/app/qa-v2 al hosting. Esta instrucción no autoriza despliegue.


## Revisión de gestión rápida, todavía local

No hay autorización de push, PR o despliegue. Esta revisión no añade migraciones. Para una liberación futura autorizada, revisar y publicar del mismo SHA el backend, Blade, gestor, estilos y nuevo agenda-reschedule-history.js; conservar versionado de AgendaAssets. Verificar reprogramación con otro paciente ocupando el origen, historial no bloqueante y pagos intactos.

Revertir solo código conserva eventos REPROGRAMADA y sus motivos; ocupación sigue basada en fecha actual. No borrar eventos ni reconstruir reservas a partir de anotaciones. Esta compatibilidad no autoriza un futuro rollback que ignore ajustes financieros, aún no implementados. Conservar también requisitos de lectura de cuarta ronda, teleconsultas y documentos privados. [Informe actual](GESTION_RAPIDA_Y_REPROGRAMACION.md).
