# PR #5 — publicación MVP supervisada (13/10/2026)

Candidato de Entrega Urgente 1; sigue Draft. No autoriza merge, despliegue ni asignación productiva. Todo comando de cambio requiere la aprobación final y destino verificado. No se asume BD vacía: ejecutar el SQL SELECT adjunto y recibir solo catálogo/conteos. No hay acceso Hostinger disponible en esta sesión ni se abrió información clínica.

## 1. Preparar corte y alcance

- Confirmaciones ya recibidas: Hostinger 1a2c1895, tracked clean, PHP web/CLI 8.3.35, Laravel 9.52.21, requisitos Composer success, MariaDB 11.8.9 y dos UNIQUE financieros existentes. No repetirlas durante esta revisión; registrar la release efectiva nuevamente solo en el futuro momento del cambio si transcurrió tiempo o hubo otro despliegue.
- SQL docs/CEO_SALUD_MVP_PREFLIGHT_SELECT.sql: catálogo COMPLETO de roles (sin limitar a cuatro), conteos de pacientes/citas/dinero/adjuntos y zona/modos. Conteo >0 se conserva; incluso conteo 0 no autoriza limpiar/migrar. No inferir si un registro es ficticio por su nombre, fecha o saldo. Máximo ID operador >46655 requiere revisar la serie piloto existente; no forzar ni reutilizar una serie.
- Revisar roles web que usarán el MVP. Plan por defecto considera TODOS los web existentes e inventaría los otros guards; los roles api no reciben grants web. Sin columna de vigencia en roles Spatie no se inventa filtro ACTIVO; definir participantes mediante catálogo y aprobación. Se puede limitar con --role repetido. No añadir roles ni permisos inexistentes.
- Confirmar autodespliegue ON/OFF, branch/ruta y comandos de build, sin cambiarlo ahora. Si merge dispara deploy, la aprobación/corte/backup deben preceder al merge. Conservar composer.lock, APP_KEY, .env, storage/adjuntos y layout Clarity. No composer update.

## 2. Backup y recuperación antes del merge autorizado

Backup actualizado y coherente de archivos reales + vendor/lock/config bajo custodia + BD completa + storage/app/appointment-documents, storage/app/public y destinos de symlinks/adjuntos externos. Guardar también recibos de permisos en storage/app/private/mvp-permission-receipts y logs de auditoría. Copia externa cifrada, hashes y restauración ensayada a destino nuevo aislado; no adjuntar dump/.env al PR/chat. Pausa de escritores/cajas/APIs/jobs durante el corte cuando se autorice; mantenimiento web no detiene jobs. Registrar T0 UTC/Lima, conteos/correlativos, RPO y RTO.

No hay migraciones o seeders nuevos en PR #5. Si falta schema/índice previo, detener y presentar el defecto; no ejecutar migrate/fresh/seed sobre producción. Ensayar recuperación sin proveedores externos ni captura Clarity sobre datos restaurados reales.

## 3. Publicación futura y habilitación ordinaria

**Bloqueo confirmado 13/10:** cinco roles tienen guard vacío; COMERCIAL tiene web. Ejecutar primero la lectura y, solo con autorización de despliegue, la normalización delimitada de [CEO_SALUD_MVP_GUARDS_PR5.md](CEO_SALUD_MVP_GUARDS_PR5.md). Permissions confirmado: 23, TODOS web. Normalizar solo ADMINISTRADOR/ADMISION/CAJA/FACTURACION/RECEPCION; excluir COMERCIAL y no usar --permission-id. Preservar los grants extraordinarios withdraw/additional.create ya asignados a ADMISION/COMERCIAL; el procedimiento documenta su efectividad y alcance. Bootstrap mantiene la habilitación ordinaria separada.

1. Autorizar PR final y procedimiento, después de resolver privacidad/seguridad. Registrar SHA final aprobado, backups y responsable de rollback. Merge a main y deploy son acciones futuras, no realizadas en esta tarea.
2. Deploy de la release resultante por el mecanismo verificado, preservando paths compartidos. Composer install desde lock únicamente si el mecanismo necesita vendor, con PHP correcto; nunca update o flags ignore-platform. Seguir scripts revisados del repo. Invalidar/reconstruir cachés de release/Blade/routes según el mecanismo autorizado; no borrar datos ni recibos. Si no se dispone de cambio de release atómico, mantener corte hasta completar todo.
3. Configuración: SCHEDULING_MVP_ENABLED=true. Para MVP sin apertura manual de Caja, se recomienda reutilizar SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT=true, sujeto a aprobación final: contexto contable automático separado por operador/día y TICKET, sin SUNAT. No cambia el adelanto mínimo ni crea dinero por una imagen. Si se mantiene false, TODOS los cobradores requieren turno manual propio ABIERTO y única serie TICKET activa; permisos por sí solos no resuelven un 422 de contexto financiero.
4. Generar vista previa en la release nueva. Solo lectura de grants, sin asignación automática:

~~~bash
# Usar SIEMPRE la lista explícita aprobada, después de normalizar guards:
php artisan agenda:mvp-permissions --role=ADMINISTRADOR --role=ADMISION --role=COMERCIAL --role=RECEPCION --role=CAJA --role=FACTURACION
~~~

La segunda línea es EJEMPLO: los nombres deben existir y coincidir con el catálogo aprobado, incluyendo cualquier otro perfil participante; no inventarlos. Revisar missing_definitions=[] y additions. Generar/refrescar plan después del corte; si cambia, no reutilizar una huella antigua.

5. Solo con autorización final para esa lista, aplicar exactamente el plan revisado, usando mismas opciones --role que en la vista previa sin usar una lista vacía por omisión:

~~~bash
php artisan agenda:mvp-permissions --role=ADMINISTRADOR --role=ADMISION --role=COMERCIAL --role=RECEPCION --role=CAJA --role=FACTURACION --apply --confirm=HUELLA_SHA256_DEL_PLAN --reference=PR5-APROBACION-REFERENCIA
~~~

Referencia alfanumérica técnica, sin PII. Añade únicamente appointment.mvp.access/view/create/update/reschedule/payment.submit que falten. Preserva roles, permisos anteriores y grants directos; no crea definiciones. Transacción y recibo privado before/after/additions/id, más auditoría; Spatie invalida caché. Guardar primer recibo con additions y verificar código de salida 0/estado efectivo. Un recibo repetido sin additions es un no-op; no reemplaza el primero para reversión. Si faltan definiciones, hash cambió o no se guarda recibo, se aborta/revierte; no corregir con SQL masivo.
6. Recargar formularios/sesiones para obtener UI nueva. El editor de roles existente es solo ADMIN: guard web, revision de concurrencia, nombre fijo, checkboxes existentes marcados, confirmación y syncPermissions con auditoría. No otorga administración a los otros perfiles; exoneración, cancelación, retiros/devoluciones y anulación financiera quedan fuera de la asignación ordinaria. Grants extraordinarios anteriores se conservan, no se revocan por bootstrap.

## 4. Humo y apertura a datos reales

Primero ejecutar humo en staging equivalente con fixtures, esquema y flags/modos/config productivos; PHP/MariaDB con versiones exactas ya tienen suites locales aisladas, pero no acreditan LSAPI/FPM, cachés/filesystem y política Clarity de Hostinger. No apuntar tests destructivos a staging compartido/producción ni relajar guardas.

- Por cada perfil aprobado: login/dashboard → Pacientes → crear/completar/editar ficha → cotizar → reservar sin dinero → adelanto S/50 de catálogo S/100 sin confirmación implícita → confirmar explícitamente → consultar → editar estado/reprogramar por Agenda y tres rutas heredadas. Mostrar nav ordinaria para roles adicionales y mantener maestros/roles/usuarios restringidos a ADMIN.
- Negativos: confirmar sin 50% devuelve 422 y rollback; precio/saldo manipulados 422; sin grant o intentando exoneración/extraordinaria 403; reserva privada ajena/missing indistinguible 404. Repetir UUID y operación bancaria sin duplicar cobro; dos confirmaciones del mismo slot solo una gana. Históricos fixture intactos y reconsulta/tarifas consistentes; Día/Semana/Mes, consola y adjuntos privados.
- Tras desplegar, comprobar release, assets/cachés, salud y accesos en producción sin crear pacientes/pruebas de dinero reales. Humo con datos ficticios en producción solo con autorización explícita separada; hasta entonces usar staging. Autorizar apertura después de evidencias y visto bueno de responsable, no porque exista un botón habilitado.

## 5. Reversión preservando datos nuevos

Si se normalizaron guards, revertir grants antes de guards y antes de retirar la release nueva; seguir el recibo/after.state_hash y las guardas del procedimiento de normalización. No restaurar tablas Spatie parciales ignorando pivots.

Ante incidente autorizado: detener escritores de Agenda/Admisión/Caja/APIs/jobs, conservar solicitudes/cobros en curso, backup POST-cambio de BD/storage/recibos. Retorno SOLO de código/vendor/assets/config compatible preservando BD, correlativos, payments, vouchers, operaciones/idempotencia y adjuntos. Conservar Clarity; nunca restaurar una release anterior a su instalación ni hacer reset --hard/force push sobre servidor compartido.

Si también debe revertirse la asignación, hacerlo ANTES de retirar la release que contiene el comando, con su primer recibo aplicado y estado revisado. La huella es after.plan_hash de ese recibo; si otro cambio de permisos ocurrió, se rechaza la reversión y se revisa manualmente. Se retiran solo additions del recibo, nunca todos los permisos:

~~~bash
php artisan agenda:mvp-permissions --rollback=UUID_DEL_RECIBO --confirm=HUELLA_AFTER --reference=PR5-ROLLBACK-REFERENCIA
~~~

No borra dinero/citas ni asignaciones directas; repetición del rollback no vuelve a mutar grants. Se exige autorización y revisión del estado DB/recibo si hubo interrupción/commit fallido; no inferir éxito de un archivo aislado sin cotejarlo. Si hubo cambios concurrentes, conservar servicio restringido hasta revisar, no forzar.

La versión anterior reintroduce bypass heredados: no reabrir escrituras financieras automáticamente. Restaurar la BD T0 perdería entradas posteriores; si es necesario, recuperación a BD nueva con PITR/logs realmente disponibles y conciliación de nuevos pagos, modificaciones de registros anteriores, vouchers/series/operaciones y adjuntos antes de cutover. No re-enviar cobros, fabricar UUID nuevas, truncar tablas ni ajustar dinero por SQL. Mantenimiento/rollback requiere aprobación final, no está autorizado por esta documentación.

## 6. Condiciones aún abiertas

- Catálogo confirmado: seis roles y 23 permisos web; normalización únicamente de cinco roles vacíos. Continúan pendientes selección/aplicación productiva final, conteos reales y modo de caja elegido. No hay sesión Hostinger disponible para verificarlos aquí; usar SQL sanitizado y revisión supervisada.
- Backup/restauración/rollback y autodespliegue comprobados. Versiones/UNIQUE previos ya confirmados; restante schema/config y diferencias de sistema operativo/modos siguen pendientes.
- Clarity se conserva EXACTAMENTE: masking efectivo de nombres, DNI/HCE, teléfonos, texto clínico, RX/informes, imágenes, pagos, mensajes, títulos/URLs/referrers/etiquetas; acceso/retención y grabaciones ficticias. Sus [términos 1.b.ii](https://clarity.microsoft.com/terms) restringen contenido sensible sanitario/financiero: resolver ámbito admisible con responsable/proveedor; enmascaramiento por sí solo no acredita admisibilidad. [Masking oficial](https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-masking). No generar capturas de pacientes reales para probarlo ni modificar/remover instalación sin autorización.

Estado: código ordinario y QA local terminados; NO LISTO para abrir datos reales hasta cerrar estas condiciones. PR sigue Draft; no merge/deploy/permiso productivo ejecutado. Siguiente hito queda fuera de esta rama.
