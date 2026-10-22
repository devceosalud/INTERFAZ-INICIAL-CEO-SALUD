# Preparación de despliegue y rollback — NO EJECUTADO

## Alcance y condiciones de publicación

Esta candidata parte de main `033c3fd60ba7620dd32d82d96f8e21c64fdea759`. No es un despliegue ni autoriza fusionar el Draft PR. Hostinger puede volver a publicar main: no usar Redistribuir para intentar instalar una rama que no está en main. El responsable debe autorizar expresamente el release, su ventana y el mecanismo Hostinger después de revisar los resultados y el SHA final del PR.

Destino informado: `/home/u676842872/domains/srv-prd01.ceosalud.com/public_html`, carpeta física. CLI informado: `/usr/bin/php` 8.3.35. No se accedió por SSH, no se cambió HEAD, no se ejecutó git pull, no se modificó .env, no se ejecutaron migraciones ni se consultaron pacientes productivos.

## Preflight del responsable, antes de autorizar

1. Confirmar SHA servido y fuente de despliegue Hostinger; detenerse si ya no coincide con el baseline. Revisar diff final contra el main vigente; no restaurar ramas apiladas ni archivos completos de versiones antiguas.
2. Verificar CLI y PHP web, extensiones, permisos de storage/bootstrap/cache, OPcache web, tareas programadas y workers. La versión CLI no demuestra la versión del pool web.
3. Verificar de forma de solo lectura las tres migraciones históricas y su estructura real:

```sql
SELECT migration, batch FROM migrations
WHERE migration IN (
 '2026_10_21_200000_define_direct_appointment_economic_permissions',
 '2026_10_21_210000_link_users_to_doctors',
 '2026_10_21_210100_define_medical_role_and_permissions');
SHOW COLUMNS FROM users LIKE 'doctor_id';
SHOW INDEX FROM users WHERE Key_name = 'users_doctor_id_unique';
SHOW CREATE TABLE users;
SHOW COLUMNS FROM appointments LIKE 'estado_agenda';
SHOW COLUMNS FROM appointment_events LIKE 'event_type';
SELECT id, doctor_id, is_active FROM users WHERE id = 2;
SELECT r.name, r.guard_name FROM roles r JOIN model_has_roles m ON m.role_id=r.id
WHERE m.model_type='App\\Models\\User' AND m.model_id=2;
```

Esperado: tres registros ya aplicados; users.doctor_id nullable, único y FK a doctors con restricción de borrado; usuario 2 conserva doctor 1 y ADMINISTRADOR + MEDICO. Verificar profesional activo y permisos clínicos efectivos sin reasignarlos automáticamente. No eliminar filas ni corregir el registro de migraciones a ciegas si hay discrepancia. La migración del vínculo aborta expresamente si la columna existe pero se intenta aplicarla como pendiente.

4. No se requiere un estado nuevo MySQL: se utiliza CONFIRMADA existente y estado_pagado PENDIENTE. CONFIRMACION_SIN_ADELANTO es un tipo de evento en la tabla ya existente, no una tabla, permiso ni estado de agenda. Verificar que event_type conserve el contrato varchar del código, y las tablas appointment_operations/appointment_events y de procedimientos ya existentes.
5. Verificar los permisos existentes de las cuentas operativas y clínicas. No hacer bootstrap general sobre MEDICO ni atribuir doctor_id por coincidencia de nombre. La identidad debe verificarse por otro administrador desde el editor existente, eligiendo el profesional por nombre. No tocar el vínculo de Julio para preparar el release.
6. Pilar: documentar si existe una cuenta verificada. Sin ella, el recorrido clínico autenticado no está habilitado. La contingencia depende de `scheduling.unlinked_doctor_admission_fallback`, por defecto false, y de las condiciones del servicio. No activar ni cambiar configuración productiva desde esta tarea. No declarar una actuación administrativa como firma médica.

## Respaldo preparado como procedimiento

Antes de cualquier publicación, el responsable debe crear un respaldo privado verificable fuera de public_html: código actual completo y manifiesto SHA-256; archivos persistentes/documentos privados; configuración y secretos protegidos; dump consistente de BD y registro de migraciones. Registrar timestamp, SHA previo, versión de esquema, ubicación segura y hash. Probar extracción/lectura del respaldo en un entorno aislado. Evitar incluir respaldos o .env en Git, webroot o evidencias públicas.

No se ha creado un respaldo productivo: hacerlo requeriría acceso a producción que esta tarea prohíbe. Los respaldos de las cohortes locales son solo ficticios y no sustituyen ese respaldo.

## Instalación propuesta

1. Con autorización separada, programar ventana breve, detener nuevas escrituras, drenar solicitudes/colas y usar mantenimiento prerenderizado. La carpeta física no permite prometer una sustitución atómica o cero interrupciones.
2. Publicar únicamente el SHA aprobado mediante el mecanismo validado de Hostinger. Evitar una mezcla temporal de rutas, PHP, Blade y JS. Conservar archivos persistentes y .env. No usar composer update; respetar composer.lock (sin cambios en esta candidata).
3. Si las tres migraciones están registradas y el esquema es consistente, no se vuelven a aplicar. Cualquier pendiente inesperada detiene el release y requiere una revisión independiente; no ejecutar migrate --force como reparación automática.
4. Regenerar cachés de rutas/vistas/configuración con el runtime del servidor bajo el procedimiento autorizado, sin cambiar variables. Invalidar OPcache del pool web por el mecanismo administrativo correcto. Comprobar versiones `?v=` de AgendaAssets: son hashes del contenido.
5. Ejecutar smoke con datos ficticios autorizados: reprogramación y enlace en ambas agendas; llegada Comercial; médico propio inicia; dos botones; pendiente bloquea finalizar; checks parciales/listo naranja; retorno manual auditado; fin y salida separados; corrección económica administrativa con pago intacto; ADMISION confirma sin adelanto con saldo real y etiqueta amarilla; reserva privada ajena continúa oculta. Verificar que no aparece Mi agenda médica y que las funciones ERP previas siguen accesibles según rol.
6. Reabrir solo si SHA, assets, permisos, esquema, saldos y smoke coinciden. Registrar responsable, hora y resultado. No usar pacientes reales para el smoke.

## Rollback preparado como procedimiento

Ante errores de autorización, pérdida de privacidad, discrepancias de importes, cachés incompatibles o fallos del recorrido: mantener/drenar escrituras, volver al código respaldado del SHA anterior verificado (`033c3fd6` solo si sigue siendo el baseline real) mediante Hostinger, regenerar cachés compatibles y OPcache web, verificar funcionamiento antes de reabrir.

Rollback de código únicamente. No ejecutar migration:rollback, down, DROP COLUMN ni borrar permisos, cuentas, citas, pagos, procedimientos o eventos. No restaurar automáticamente una BD antigua sobre operaciones nuevas: las autorizaciones sin adelanto y las correcciones económicas realizadas deben conservar su auditoría y saldo real. Main anterior puede mostrar menos controles/indicadores; restringir temporalmente la administración de roles/vínculos durante el rollback para no perder la combinación ADMINISTRADOR + MEDICO. Si el fallo afecta datos, mantener servicio restringido y conciliar cada operación con la auditoría; no inventar pagos ni revertir registros masivamente.

Todo lo anterior es preparación documental. NO MERGE. NO DEPLOY. NO CAMBIOS PRODUCTIVOS.
