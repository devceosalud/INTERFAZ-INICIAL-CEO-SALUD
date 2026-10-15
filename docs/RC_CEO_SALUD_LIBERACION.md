# RC CEO Salud — liberación controlada (sin ejecutar)

**Estado: NO-GO productivo.** Esta guía prepara una intervención supervisada; no autoriza merge, despliegue, SQL de escritura, instalación ni cambios en Hostinger. Rama `codex/rc-integracion-preproductiva`. El SHA de código probado y el manifiesto están en `evidence/rc/final/release-summary.json`; el SHA final publicado se obtiene del PR Draft y `git rev-parse HEAD`.

## 1. Solo lectura y criterios de parada

1. Registrar responsable, ventana, autorización y ubicación fuera del directorio público para evidencias. Suspender cualquier autodespliegue antes de que se autorice el merge; **su existencia/configuración en Hostinger sigue PENDIENTE**. Revisar hPanel/Git y hooks sin imprimir secretos.
2. Comparar `git rev-parse HEAD`, `git status --short`, `git remote -v` con el commit aprobado. Último main confirmado en GitHub: `1a2c1895c5ab65d35928fc7244d3cb57e6b4adc4`; producción fue confirmada por el operador en ese commit. Si difiere, detener y reconciliar. Nunca usar reset/clean para ocultar cambios.
3. Evidencia previa del operador: PHP web/CLI 8.3.35, Laravel 9.52.21, MariaDB 11.8.9, índices únicos de operaciones por actor/UUID y pagos por identidad bancaria. No se repitieron consultas productivas. Confirmar únicamente cambios posteriores y requisitos nuevos del lock. No publicar `.env`, phpinfo ni credenciales.
4. `php artisan migrate:status` y diagnóstico `php artisan pilot:diagnose --user=ID_NOMINADO` son de consulta. Comparar tablas, columnas, FK e índices con el manifiesto y migraciones. Los conteos productivos comunicados fueron 4.807 pacientes, 14 citas y 2 pagos: **no se asumen ficticios ni se reutilizan en QA**.
5. Resolver antes del GO: avisos de seguridad pendientes de Laravel, privacidad/admisibilidad de Clarity, equivalencia de PHP web/Linux, respaldo restaurable y roles participantes aprobados. Clarity permanece instalado; no se autoriza cambiarlo desde esta guía.

## 2. Respaldo y restauración antes de publicar

- Ventana sin escrituras: registrar hora/commit/conteos agregados y último identificador financiero; mantener la aplicación anterior y su vendor recuperables. Respaldar BD completa mediante exportación transaccional consistente de MariaDB, código/lock/vendor, configuración cifrada (incluida APP_KEY), almacenamiento privado y público necesario, sesiones/configuración de autenticación, auditoría y archivos de evidencia/recibos CLI. Guardar fuera del webroot, restringir acceso y obtener SHA-256; probar lectura del archivo. Evitar contraseña en argumentos/historial.
- Restaurar **en una BD nueva aislada**, nunca sobre producción, usando la misma familia de MariaDB. Comparar conteos de todas las tablas, huellas de filas relevantes y hashes de adjuntos; comprobar acceso privado y asociaciones de comprobantes. Probar también extracción de código/configuración sin divulgar secretos. Registrar RTO/RPO reales; no hay valores productivos medidos.
- Ensayo local reproducible: `powershell -NoProfile -File tools/qa/backup-restore-rc.ps1`. Sus guards solo admiten las BD ficticias RC 33322/33323 y generan un esquema nuevo de restauración; **no es un script para apuntar a Hostinger**. Evidencias saneadas en `evidence/rc/final/backup-*.json`.

## 3. Preparar paquete y esquema (solo tras autorización independiente)

1. Ensayar primero el paquete exacto en preproducción aislada Linux/PHP web equivalente. El entorno actual es Windows local, PHP 8.3.35/MariaDB 11.8.9; no acredita FPM, filesystem sensible a mayúsculas, TLS, límites ni capacidad de Hostinger.
2. Instalar desde `composer.lock`, nunca `composer update` productivo: `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`; `composer check-platform-reqs --no-dev`; comprobar auditoría de seguridad. En QA se validó autoload estricto con `COMPOSER_ROOT_VERSION=dev-rc-integracion-preproductiva`, evitando una detección Git que se bloqueaba. Esta variable solo fija metadatos de Composer, no cambia Laravel ni permisos. No ignorar requisitos.
3. Publicar todos los assets locales de SweetAlert, FullCalendar y OCR (worker/WASM/idioma), sus licencias y archivos versionados. OCR funciona en navegador; no requiere Tesseract nativo, Node ni proc_open en el hosting. Verificar MIME, CSP worker/blob/WASM, compresión, caché y límites de subida en PHP web con imágenes ficticias.
4. Durante mantenimiento, ejecutar únicamente migraciones pendientes revisadas, en orden ascendente. El RC añade ocho sobre main (ver manifiesto): snapshot de precio; indicaciones; doble duración; comprobantes revisados; identidad/acceso de trabajadores; auditoría/versiones de horarios; definición de capacidad de horarios; versionado privado de documentos. Revisar también el estado real de migraciones históricas, sin ejecutarlas a ciegas. No hacen backfill de precios/estados de citas ni asignan privilegios extraordinarios. Varias bajadas bloquean pérdida de información: **no usar migrate:rollback como procedimiento de reversión**.
5. Después del código/esquema, regenerar cachés de configuración/rutas/vistas según procedimiento Laravel revisado. Conservar APP_KEY y rutas privadas; no exponer storage/app mediante symlink público. Verificar escritura restringida de storage/bootstrap/cache y auditoría duradera. Ningún router/servidor/fixture de `tests/Support` debe exponerse como entrada HTTP productiva.

## 4. Guards, permisos, sede y configuración: acciones separadas

Las siguientes órdenes son plantillas **NO EJECUTADAS EN PRODUCCIÓN**. Sustituir huellas y referencias por las de la vista previa inmediatamente revisada, posterior a las migraciones. Detenerse ante colisiones/incompatibilidades. Guardar JSON/UUID de recibo fuera del webroot.

```sh
# Solo lectura: exactamente los cinco roles con guard vacío confirmados
php artisan agenda:mvp-guards --role=ADMINISTRADOR --role=ADMISION --role=CAJA --role=FACTURACION --role=RECEPCION
# Escritura delimitada, únicamente con autorización y hash recién revisado
php artisan agenda:mvp-guards --role=ADMINISTRADOR --role=ADMISION --role=CAJA --role=FACTURACION --role=RECEPCION --apply --confirm=HASH_PLAN --reference=APROBACION
# Separadamente: repetir --role solo para participantes expresamente aprobados
php artisan agenda:mvp-permissions --role=ROL_APROBADO
php artisan agenda:mvp-permissions --role=ROL_APROBADO --apply --confirm=HASH_PLAN --reference=APROBACION
# Sede: código estable elegido por responsable, nunca un ID inventado
php artisan agenda:magdalena-site --code=CODIGO_APROBADO
php artisan agenda:magdalena-site --code=CODIGO_APROBADO --apply --confirm=HASH_PLAN --actor-id=ADMIN_ACTIVO --reference=APROBACION
```

- COMERCIAL ya tiene web; los 23 permisos existentes son web. **No pasar `--permission-id`**, no renombrar permisos ni borrar relaciones. Normalizar cinco roles conserva sus IDs y asignaciones; puede hacer coherentes facultades heredadas. ADMISION/COMERCIAL ya tienen `appointment.withdraw` y `appointment.additional.create`: revisar explícitamente su efectividad y alcance. WITHDRAW no equivale a ejecutar una devolución.
- Bootstrap MVP añade solo seis capacidades: acceso, consulta, crear, editar, reprogramar y registrar pagos. No usar la opción sin roles para una aplicación productiva no revisada. No otorga exoneración, ADD, fuera de horario, devolución, documentos, indicaciones ni gestión de horarios. Revisar usuarios con varios roles y permisos directos: revocar un rol no quita capacidades otorgadas por otro.
- Asignar por editor existente y autorización nominada: `doctor.schedule.manage`; `appointment.documents.view` y `.manage` (gestión además exige UPDATE); `appointment.reevaluation.indication.record` para acreditar por escrito la indicación del médico. La definición de los tres primeros se agrega por migraciones sin grants; confirmar/crear la definición web de RECORD si falta, sin otorgarla globalmente. Aprobar separadamente exoneración y otras facultades extraordinarias.
- La sede productiva estaba vacía. El comando reutiliza Magdalena compatible o crea una sola sede bajo hash y auditoría; nunca fabrica ID ni asigna sede retrospectivamente a citas/horarios. Configurar horarios futuros explícitos mediante UI y capacidad. Rechazar nuevos solapamientos; preservar bloques heredados y citas vinculadas.
- Validar mapa Quiroz según catálogo confirmado: doctor 1; servicios 1 y 3; tarifas adicionales 2 y 3. Configuración opt-in `CEO_QUIROZ_DOCTOR_ID`, `CEO_QUIROZ_REFERRAL_SERVICE_IDS`, `CEO_QUIROZ_TRAUMA_REFERRAL_RATE_ID`, `CEO_QUIROZ_PIE_REFERRAL_RATE_ID`; revisar asociación antes de habilitar. No cambiar importes generales del catálogo. Política 250/220 solo referido explícito; gratuidad requiere indicación acreditada, mismo servicio y misma fecha de atención.
- Aprobar expresamente `SCHEDULING_MVP_ENABLED` y `SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT`. El piloto crea contexto/serie interna por operador sin apertura manual: registra pagos reales, medio, actor, UUID y TICKET; exige conciliación operativa y responsabilidades de cierre. **TICKET no es comprobante electrónico SUNAT**. La configuración financiera productiva sigue pendiente; no se presupone activada.

## 5. Smoke y habilitación

Con cuentas y pacientes **ficticios autorizados en staging equivalente**: iniciar sesión por perfil; crear paciente y horario futuro; reserva sin dinero; 20/100 protege sin confirmar; completar 50/100 y confirmar expresamente; segundo operador privado, colisión y ADD explícito autorizado; reintento sin duplicar; OCR Yape/BCP/BBVA local con revisión humana; exoneración con/sin permiso y motivo; Día/Semana/Mes; llegada/llamado/inicio/fin; Nicole actualiza sin recarga; documentos privados/versiones; revocación de permisos y desactivación invalida sesión. Comparar BD, auditoría, totales y ausencia de huérfanos. Registrar resultado; abrir operaciones reales solo con aprobación final y privacidad resuelta.

## 6. Rollback sin perder cobros

1. Ante duplicados, filtración, inconsistencia, errores financieros o pérdida de disponibilidad: cerrar nuevas operaciones, conservar evidencia y snapshot **actual** de BD/adjuntos/auditoría, identificar transacciones desde el corte. No borrar ni editar cobros para cuadrar totales.
2. Revertir código/assets/vendor al paquete anterior únicamente si se comprobó su compatibilidad con el esquema aditivo **y** con datos/estados nuevos. Main anterior carece de algunas garantías financieras y de sesiones del RC: no reabrir automáticamente con ese código. Si no es compatible, mantener mantenimiento y corregir hacia delante.
3. No restaurar una BD anterior sobre pagos/citas posteriores. Restauración completa solo bajo incidente controlado, conciliación individual de movimientos nuevos y aprobación del responsable. Conservar archivos privados y APP_KEY; una restauración de sesiones puede resucitar accesos, revisar auth_version/tokens.
4. Guards/grants/sede tienen rollback por UUID de recibo y `after.state_hash`, rechazando deriva; retirar solo cambios del recibo y solo si sigue siendo seguro. No deshacer permisos de forma general ni borrar Magdalena utilizada. Revisar el recibo y la sintaxis de ayuda antes de cada reversión.

**Pendiente productivo:** autorización, autodespliegue, backup/restore real, equivalencia web/Linux, privacidad Clarity, remediación Laravel, participantes/grants, modo financiero y smoke. No se ejecutó ninguna de estas escrituras en Hostinger.