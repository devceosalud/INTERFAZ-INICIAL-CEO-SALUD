# Procedimiento propuesto — requiere revisión y autorización

No se ejecutó ninguna de estas operaciones en producción. El ZIP de revisión no es un instalador y no debe descomprimirse completo sobre el sitio. Solo los 12 archivos de aplicación enumerados en `ARCHIVOS.md` pertenecen al cambio de ejecución; pruebas, herramientas y documentación son para revisión/QA.

## Preflight autorizado, antes de activar

1. Confirmar nuevamente servidor, ruta real de la aplicación, dominio, SHA y hora del despliegue vigente. La base esperada es `1f840e90912d2ce8c55ce7502a22a1d582492bc3`. Si cambió desde la confirmación del responsable, detenerse y revisar la candidata sobre la nueva base; no asumir que `main` equivale al servidor.
2. Guardar la revisión local y los manifiestos; comparar el diff exacto de base a candidato. Debe contener únicamente los archivos declarados. `database/`, `config/`, Composer, servicios de pagos/precios y permisos no tienen cambios. No incorporar ningún commit de la rama económica.
3. Con un operador autorizado, guardar un inventario sanitizado de archivos conservados o modificados por Hostinger y el estado Git, si existe checkout. No imprimir `.env` ni el contenido de archivos privados. Un HEAD correcto y `git status` limpio no acreditan por sí solos archivos ignorados, dependencias ni cachés.
4. Ejecutar el verificador CLI independiente desde una ubicación administrativa no pública, suministrando la ruta real del sitio y el manifiesto BASE. No carga Laravel, no lee `.env`, no se conecta a una base y no escribe archivos:

   ```text
   php /ruta-administrativa/reschedule-preflight.php /ruta-real-aplicacion /ruta-administrativa/DEPENDENCIAS_BASE.json
   ```

   Exigir `result: MATCH`, 535 comprobaciones y cero divergencias. `BLOCKED`, archivos ausentes, enlaces que salgan de la raíz o archivos inesperados requieren revisar el drift antes de continuar. Se permite únicamente la diferencia CRLF/LF en los textos. El archivo CSS nuevo debe estar ausente en la base. Este inventario no certifica la inexistencia de archivos adicionales ajenos: contrastar también el inventario de archivos conservados y la configuración del webroot.
5. Verificar las dependencias instaladas frente al `composer.lock` aprobado y los requisitos de plataforma del proyecto, extensiones PHP, versión de MariaDB/JSON, assets servidos, permisos de lectura y autorización efectiva existente. No actualizar dependencias como parte de esta entrega. Confirmar que las rutas de QA/pruebas no se sirven desde el webroot, que debe seguir siendo `public/`.
6. Comprobar, mediante el procedimiento de lectura autorizado, que ya existen `appointment_events` con `metadata`, `event_type`, `appointment_id`, `actor_user_id`, `occurred_at` y las columnas/relaciones originales de Agenda. No exportar datos de pacientes. No se necesita una columna nueva. **Si falta ese contrato o aparece la necesidad de una migración, detenerse e informar; no ejecutarla.**
7. Obtener una copia restaurable del código anterior (incluidos archivos retenidos relevantes), inventario, configuración y artefacto de despliegue. Confirmar el respaldo de BD previsto por el procedimiento operativo, custodiado fuera del paquete de revisión. Esta entrega no requiere restaurar ni transformar datos.
8. Probar el artefacto exacto en un entorno previo aislado con datos ficticios: una cita de 12:20 trasladada al 24/10 a 12:00, nueva cita en el origen, Admisión, destinos sucesivos, permisos, móvil, misma capacidad/KPI y pagos. No crear estas citas de prueba en producción.

## Activación, únicamente después de autorización

1. Registrar el SHA local aprobado y los fingerprints de la revisión. Preparar un artefacto exclusivo de los 12 archivos de ejecución desde ese commit. Desplegar con el mecanismo aprobado de Hostinger y una sustitución coherente del conjunto; evitar mezclar PHP/vistas/JS/CSS de versiones diferentes.
2. Preservar `.env`, `storage`, uploads, evidencias y datos. No ejecutar migraciones, seeders de permisos, Composer update ni operaciones de Caja. No introducir los archivos económicos ni las herramientas de QA en el webroot.
3. Invalidar/reconstruir solo las cachés de rutas y vistas según el procedimiento existente (`route:clear` y `view:clear`; reconstruir rutas si así opera el sitio). No cambiar la configuración cacheada ni los permisos. Renovar OPcache del proceso PHP web con el mecanismo aprobado de Hostinger si es necesario; resetear OPcache en CLI no garantiza actualizar PHP-FPM.
4. Ejecutar el mismo verificador con `DEPENDENCIAS_CANDIDATO.json`: 535 archivos, cero divergencias. Verificar que HTML/JSON privado y assets nuevos respondan correctamente, sin errores JS/HTTP. El hash de `AgendaAssets` cambia las URLs de los archivos modificados.
5. Realizar una comprobación operativa autorizada de lectura con usuarios habilitados: referencia histórica en origen, navegación vigente, nueva ubicación y cifras coherentes. No manipular pagos ni crear citas reales para comprobar el release. Registrar resultado y conservar los respaldos.

## Rollback de código

1. Ante errores de visibilidad, intervalos, cifras, archivos o navegación, detener la activación y restaurar el conjunto de 11 archivos anteriores desde el artefacto/preflight respaldado. El CSS nuevo puede quedar sin referenciar; no hace falta borrarlo para revertir.
2. Repetir la invalidación/reconstrucción de rutas/vistas y la renovación del OPcache web que corresponda, usando el procedimiento aprobado. Verificar las pantallas anteriores y las rutas base.
3. Verificar el manifiesto BASE; si se conserva el CSS nuevo, esa única entrada se informará `UNEXPECTED_FILE`, sin efecto funcional tras restaurar las vistas. Documentar expresamente esa diferencia residual; las otras 534 comprobaciones deben coincidir. Para exigir 535/535, apartar únicamente ese CSS creado por este release tras comprobar su ruta y fingerprint, por el operador autorizado.
4. No revertir la base de datos ni eliminar eventos. Las reprogramaciones realizadas durante la ventana siguen siendo las mismas citas vigentes y su historial. El código previo puede seguir leyéndolos con su panel anterior.

No ejecutar `migrate:rollback`, resets generales, eliminaciones recursivas ni restauraciones de BD para revertir esta visualización. No es necesario regenerar tickets, comprobantes o permisos.
