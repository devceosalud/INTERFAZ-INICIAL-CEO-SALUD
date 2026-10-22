# Instalación y reversión propuestas — no ejecutadas

Este documento no autoriza publicación, merge, migraciones ni despliegue. El PR57 permanece con su SHA anterior. Los puntos4/5 siguen pendientes. La candidata integrada incluye cambios anteriores a este incremento; aprobar únicamente estos archivos no equivale a aprobar toda la diferencia respecto a main.

## Dos migraciones aditivas

1. `2026_10_21_210000_link_users_to_doctors.php`: columna nullable `users.doctor_id`, índice UNIQUE y FK RESTRICT a doctors.id. Conserva usuarios, doctores, citas y sus IDs. No hay backfill ni asociación por nombre/correo/DNI/ID. SQLite requiere ADD COLUMN REFERENCES nativo porque Laravel9 omite una FK agregada por Blueprint a una tabla existente; MariaDB usa foreignId/nullable/unique/constrained. Se probaron ambas implementaciones sobre registros heredados ficticios y no vacíos. Una columna preexistente inesperada provoca parada, nunca sobrescritura.
2. `2026_10_21_210100_define_medical_role_and_permissions.php`: seis permisos clínicos Spatie y definición controlada MEDICO/web. Un rol recién creado sin usuarios recibe únicamente esas seis capacidades. Roles MEDICO existentes conservan sus permisos y asignaciones. Ninguna cuenta recibe rol ni doctor automáticamente. Guard ambiguo requiere revisión; no se fusiona por suposición. No necesita tablas de auditoría adicionales: usa user_account_events y los ledgers clínicos existentes en la candidata.

## Preflight del operador, tras autorización

- Verificar SHA y archivos realmente instalados en Hostinger, rama, git status, archivos conservados/modificados y esquema real. No inferirlos de main ni de la información histórica. Esta tarea no accedió a Hostinger. Comprobar implementación automática sigue desactivada y condiciones de publicación acordadas.
- Revisar la diferencia completa desde la base productiva verificada `1f840e9` y todas las migraciones pendientes de la candidata. **No ejecutar migrate general a ciegas**: existen migraciones previas económicas/de auditoría fuera de este incremento. Identificar dependencias reales: users, doctors, auth_version, user_account_events, appointment_events/operations/operational_reviews/requested_procedures y Spatie. No modificar tablas productivas para acomodar diferencias sin aprobación.
- Comprobar versiones reales PHP/Laravel/Spatie/MariaDB, motor InnoDB, permisos de ALTER/FK y tipo de doctors.id compatible con unsigned bigint. Composer/package-lock no cambian. Ensayar primero la release completa y las migraciones en staging aislado con datos ficticios que representen el esquema instalado. SQLite de pruebas necesita soporte nativo de DROP COLUMN para ensayo down; no es motor productivo propuesto.
- Inspeccionar MEDICO en todos los guards y sus permisos, más permisos directos de cada cuenta elegida. Ninguna cuenta se activa por coincidencias de identidad. Los perfiles con permisos ajenos a la lista clínica requieren revisión explícita; el código conserva esos permisos y rechaza activar el acceso médico mientras sean incompatibles.
- Respaldo consistente y cifrado de base de datos, archivos privados y código/configuración del servidor: users/doctors/appointments, auditorías, procedimientos, roles/permisos/pivotes y sesiones. Guardar fuera del webroot, verificar recuperación y controles de acceso; no adjuntar respaldos ni credenciales a ChatGPT/GitHub.
- Coordinar continuidad de atención. a8 bloquea acciones médicas sin cuenta sustituta; no desplegar el conjunto sin haber validado médico vinculado y recorrido completo en staging. No eliminar controles ni prestar permisos clínicos a Admisión como solución temporal.

## Activación controlada propuesta

1. Con autorización futura, fijar SHA aprobado y ventana de mantenimiento; impedir tráfico clínico durante cambio de código/esquema.
2. Tras respaldar y verificar dependencias, aplicar únicamente las migraciones aprobadas en el orden anterior sobre el esquema previsto. La ruta exacta y comando operativo los confirma el responsable contra el inventario de pendientes; no se incluye un comando global que ejecute cambios económicos no revisados.
3. Actualizar código/assets del SHA aprobado y reconstruir cachés de configuración, rutas, vistas y permisos mediante el procedimiento existente. No cambiar variables productivas desde esta tarea.
4. Administrador busca primero la cuenta existente, verifica identidad fuera de coincidencias automáticas, la vincula con confirmación/motivo y revisa historial. Luego asigna explícitamente MEDICO y sus seis capacidades desde Usuarios/Roles. No otorgar permisos generales de Agenda, Caja ni Administración al perfil médico. El médico debe estar activo.
5. En staging comprobar llegada por Admisión → inicio propio → indicación pendiente → procedimientos → listo → finalización sin salida. En activación productiva los operadores autorizados validan el acceso sin manipular pagos/comprobantes históricos ni crear cuentas duplicadas. Verificar reservas de médicos sin cuenta y acceso ordinario de Admisión/Comercial/Administración.
6. Activar por una cuenta verificada y ampliar gradualmente después de revisión. No hay asignaciones masivas ni feature flag que convierta automáticamente usuarios anteriores en médicos.

## Rollback seguro

Preferir corrección hacia adelante o reversión de código conservando columna nullable, permisos e historial. Antes de volver a a8, coordinar continuidad clínica: esa versión volverá a carecer del recorrido médico sustituto. Revertir código por sí solo no garantiza que se pueda atender; requiere una ventana y un plan operativo aprobado.

`down()` de la columna se niega si existe cualquier vínculo o cualquier evento DOCTOR_LINK_CHANGED, incluso si se retiraron todos los vínculos. No borrar eventos para forzarlo. Solo en un entorno nunca activado y sin historial se admite retirar FK/índice/columna; se ensayó en SQLite/MariaDB. La migración de permisos no revoca ni borra autorizaciones al retroceder, para no destruir asignaciones/auditoría. Reversión de permisos o de cuentas, si se necesitara, debe ser explícita y auditada por Administración.

No tocar doctors.id, appointments.doctor_id, pagos, comprobantes ni timestamps históricos durante rollback. Una restauración de respaldo completo requiere su propia aprobación y reconciliación; no es una operación automática de esta entrega.

## Riesgos residuales

- Identidad real: solo un administrador puede confirmarla; no se verificó ninguna cuenta productiva.
- Esquema/archivos de Hostinger y todos los cambios heredados de la candidata: aún requieren preflight y revisión integral.
- Cuentas MEDICO heredadas con guard/permisos incompatibles: requieren revisión sin asignaciones automáticas.
- El flujo médico nuevo admite atención presencial según reglas actuales; teleconsulta y nuevas políticas clínicas no se ampliaron.
- El cambio de esquema debe anteceder al código que consulta la relación. No publicar parcial ni aplicar migraciones sin coordinación.
- Las pruebas locales reducen riesgo; no sustituyen aceptación clínica/operativa en staging. No se ejecutó CI del nuevo SHA porque permanece local.
