# Acceso clínico compatible: MEDICO y MEDICO 2

## Problema y decisión

Base: `main` **ea1f63401c3b1d20c7005591f4298c4ae744d839**. Rama independiente `codex/clinical-medical-roles-20261024`. El PR #73 de teleconsultas permanece separado e intacto.

El usuario confirmó mediante capturas del editor que MEDICO 2 tiene exactamente los seis permisos `appointment.clinical.*` y los dos permisos de lectura `appointment.mvp.access` / `appointment.view`, sin creación, edición, pagos o administración. **No se consultó producción ni se contrastaron sus tablas.** Los ensayos usan una cuenta ID 13 totalmente ficticia, vinculada a un médico distinto del Julio ficticio ID 2.

Había dos bloqueos independientes:

1. Los consumidores clínicos exigían el nombre exacto MEDICO: cuenta, vínculo, solicitud de procedimientos, catálogo personal, documentos/historial y reconocimiento del médico vinculado.
2. Tanto `ClinicalAccess` como los editores rechazaban cualquier permiso adicional a los seis clínicos. MEDICO 2 podía guardar permisos de Agenda porque no entraba en la restricción del editor, pero tampoco era reconocido como médico. Agregar solo un alias habría mantenido el rechazo de esos dos permisos y podía tratar la cuenta como operativa por error.

La solución centraliza una lista **explícita** `[MEDICO, MEDICO 2]` y admite, para ambos, únicamente las seis capacidades clínicas y los dos accesos de lectura existentes. No se utiliza un prefijo ni expresiones que autoricen MEDICO 3/4 automáticamente. No se crea ningún rol, permiso o categoría nueva, no se asignan permisos automáticamente y no se cambia la BD.

## Política efectiva

| Acción | MEDICO / MEDICO 2 sin ADMINISTRADOR | Cuenta administrativa médica existente | Administrativo puro |
| --- | --- | --- | --- |
| Abrir Admisión | Cuenta/médico activos, vínculo y capacidad clínica de lectura | Conserva acceso actual | Conserva reglas actuales |
| Ver citas en Admisión | Solo su `doctor_id`, confirmadas o heredadas autorizadas; reservas privadas ocultas | Conserva lectura administrativa actual; firma clínica solo propia | Reglas actuales de operación |
| Iniciar/finalizar atención | Capacidad específica y cita propia; llegada presencial obligatoria | Misma validación clínica | No puede firmar por el médico vinculado |
| Solicitar procedimientos / diagnóstico | Capacidad PROCEDURES, atención propia iniciada, control de versión, UUID y auditoría | Misma validación clínica | Solo las autorizaciones administrativas que ya existían |
| Reevaluación | Indicación/retirada con sus capacidades; retorno exige confirmación, disponibilidad y segunda atención | Misma validación clínica | Acciones operativas actuales, sin firma médica |
| Marcar exámenes realizados / retorno manual | No por pertenecer al rol médico | Restricciones actuales, sin ampliar privilegios | Roles operativos autorizados actuales |
| Documentos, historial y alertas | Contenido clínico autorizado de sus citas; no edición de alerta operativa | Conserva prioridad clínica actual | Reglas actuales |
| Agenda operativa y APIs generales | URL de pantalla redirige a Admisión; feeds, economía y maestro de pacientes no se abren por los dos permisos de lectura | Conserva funciones administrativas ya asignadas | Sin cambios |
| Seguimiento de pacientes | Autorización independiente; **ID 13 no recibe acceso ni seguimiento automático** | IDs configurados `[2,1,7]` más controles clínicos actuales | ADMINISTRADOR por sí solo no basta |

No se sustituye `doctor_id` por un ID de usuario ni se permite elegir la identidad que firma. El producto no contiene una excepción para el usuario 13. Una cuenta inactiva, profesional inactivo, sin vínculo, ajena a la cita, con sesión/vínculo cambiado o sin la capacidad concreta sigue rechazada. Los roles operativos mezclados con roles médicos no se convierten en una vía alternativa a la firma clínica.

La cuenta de Julio ID 2 conserva MEDICO + ADMINISTRADOR, su redirección exclusiva y su acceso independiente a Seguimiento. No se modificaron `AuthController`, `MedicalFollowupAccess` ni `config/medical_followups.php`.

## Archivos y justificación

| Archivo / grupo | Cambio |
| --- | --- |
| `app/Support/Scheduling/ClinicalCapability.php` | Lista explícita de roles compatibles, reconocimiento por guard web y whitelist de permisos del perfil. `all()` conserva los mismos seis permisos clínicos. |
| `app/Support/Scheduling/ClinicalAccess.php` | Usa la política común, conserva cuenta/médico activos, permisos vivos, vínculo e identidad. Locks de ambos roles en orden estable antes de la actuación. |
| `app/Support/Scheduling/AdmissionAccess.php` | Un médico sin rol administrativo no se convierte en operador por tener permisos de lectura; siempre entra en el ámbito clínico. |
| `app/Support/Scheduling/AdmissionRolePolicy.php` | Misma prioridad restrictiva para alertas, documentos y acciones operativas en los dos roles. |
| `app/Support/Scheduling/ClinicalAgendaBoundary.php`, `routes/scheduling.php` | Barrera interna para las rutas operativas ya existentes: redirección a Admisión, rechazo de APIs generales y autorización por cita del historial clínico. Sin endpoints nuevos. |
| `app/Support/Scheduling/MvpOperationalAccess.php` | Los permisos de navegación de un médico no conceden lectura global ni escrituras en el maestro de pacientes. |
| `app/Support/Scheduling/MvpPermissionBootstrap.php` | Excluye también MEDICO 2 de la asignación masiva de permisos de edición/pagos; no concede nada nuevo. |
| `app/Services/Scheduling/AdmissionBoardService.php` | Reconoce ambos roles al filtrar historial y elegir URLs/documentos clínicos. No cambia transiciones ni colores. |
| `app/Services/Scheduling/AppointmentReevaluationService.php` | Reconoce la cuenta vinculada MEDICO 2 y evita el fallback operativo por no detectar al médico. |
| `app/Services/Scheduling/OperationalProcedureService.php` | Solicitud clínica propia, diagnóstico, búsqueda personal y selección con la misma política. No cambia realización de exámenes. |
| `app/Services/Scheduling/DoctorProcedureSuggestionService.php` | Catálogo personal y referencias reutilizables continúan vinculados al mismo médico/usuario. |
| `app/Services/Scheduling/MedicalProcedureFollowupService.php` | Reconoce la solicitud como clínica, pero conserva el control independiente de Seguimiento y la lista de IDs vigente. No autoriza al ID 13. |
| `app/Http/Controllers/Scheduling/AppointmentDocumentController.php` | Mantiene el filtrado de documentos clínicos también para MEDICO 2, sin abrir documentos económicos. |
| `app/Http/Controllers/admin/master/role/RoleController.php` | Permite los dos accesos de lectura para ambos roles y rechaza permisos administrativos/económicos. Conserva revisión concurrente, CSRF y auditoría. |
| `app/Http/Controllers/admin/master/user/UserController.php` | Misma asignación controlada y revocación de sesiones de la cuenta modificada para ambos nombres; conserva administrador y exige confirmación para retirar perfil médico. |
| `resources/views/admin/user/edit.blade.php` | La confirmación de retirada también aparece para MEDICO 2 + ADMINISTRADOR. |
| `resources/views/admin/role/partials/form.blade.php`, `app/Support/Authorization/PermissionDisplay.php` | Explican los ocho permisos admitidos y la recomendación de reutilizar MEDICO. |
| `resources/views/templates/sidebar.blade.php` | Enlace correcto a Admisión para ambos roles, sin menú operativo redundante para el médico sin rol administrativo. |
| `tests/Feature/Scheduling/MedicalRoleCompatibilityTest.php` | 14 regresiones HTTP de recorrido, permisos, privacidad, editor, identidad, documentos, auditoría y Julio. |
| `tests/Integration/AgendaV2/MedicalRoleLockConcurrencyTest.php` | Doble inicio y revocación de permiso concurrente con selección usando locks reales. |

No se cambiaron CSS, JavaScript, diseño de agendas, reglas económicas, precios, rutas nominales ni contratos de respuesta. La restricción de la ruta operativa aplica al perfil médico sin rol administrativo, no a los trabajadores operativos existentes.

## Solución consistente para nuevos médicos

Usar **un único rol MEDICO compartido**, vincular cada cuenta a su profesional mediante el editor existente y asignar solo las capacidades clínicas necesarias. Los dos permisos de lectura de Agenda pueden guardarse ahora en ese mismo rol. La especialidad y las citas pertenecen al profesional, no a nombres de roles numerados.

MEDICO 2 se mantiene para no romper las cuentas actuales. No se renombra ni migra masivamente ningún rol/usuario y no se ejecuta un bootstrap de permisos operativos. Una futura unificación de las cuentas existentes en MEDICO deberá ser explícita, revisada y auditada, conservando los vínculos y las sesiones según las reglas del editor. No hace falta crear MEDICO 3 o MEDICO 4 para nuevos profesionales.

## Pruebas y evidencia

PHP 8.3.35, SQLite en memoria y MariaDB 11.8.9 aislada. Resultados detallados: [validation.json](evidence/validation.json).

| Verificación | Resultado |
| --- | --- |
| Casos específicos finales SQLite | **14 pruebas, 126 aserciones, sin fallos** |
| Casos específicos finales MariaDB | **14 pruebas, 126 aserciones, sin fallos** |
| Concurrencia nueva MariaDB | **2 pruebas, 20 aserciones, sin fallos** |
| JavaScript de esta rama | **369/369**; esta rama no incorpora los tests JS adicionales del PR #73 |
| Suite completa local SQLite | **1.299 pruebas, 11.865 aserciones, 18 fallos y 2 errores heredados** |
| Suite completa local MariaDB | **1.333 pruebas, 12.172 aserciones, mismos 18 fallos y 2 errores heredados** |
| Ampliación después de esa suite completa | El caso adicional de confirmación en el editor forma parte de las 14 pruebas finales; las 2 carreras nuevas se verificaron por separado. Los checks remotos ejecutan el conjunto final completo. |
| Integración virtual con #73, SQLite | **16 recorridos remotos, 308 aserciones, sin fallos** |
| Integración virtual con #73, MariaDB | **16 recorridos remotos, 308 aserciones, sin fallos** |
| Chromium real | Login de 5 perfiles; ID 13 ve solo sus citas; atención normal y con reevaluación; popup/diagnóstico; exámenes, segunda atención y finalización; otro médico/admin rechazados; Julio conserva clínica y RM automática; ambos roles guardan los ocho permisos mediante editor real; laptop 1366×768 y móvil 390×844 |

Se rechazaron ausencia de capacidad, cuenta inactiva, falta de vínculo, médico distinto, reserva privada, acceso ajeno a documentos y APIs operativas. El doble inicio conserva una sola actuación. La revocación del permiso y la selección quedan serializadas: una selección anterior puede completar, pero una nueva petición posterior se rechaza sin escrituras.

Los 20 casos fallidos son **exactamente los mismos** documentados en `main` y PR #73: tres fixtures ClinicalEnterprise todavía usan `medical_followups.authorized_user_id` singular. Se compararon los nombres y tipos de fallo en ambos motores con la evidencia de `main` sin cambios. Por la instrucción vigente, no se alteraron esos archivos ni la configuración de acceso para obtener un verde artificial. **La suite general no se declara aprobada.**

Los ajustes hechos durante QA fueron de fixtures: ID 2 explícito ante AUTO_INCREMENT de MariaDB, códigos 401/404 de controles existentes, redirección 302 del editor y llegada del Julio ficticio registrada por la acción real en lugar de asumir que `create()` guardaba un timestamp protegido. No se debilitó ningún control del producto ni se borró auditoría.

### Compatibilidad con teleconsultas

Se usó `git merge-tree --write-tree` como simulación sin mover HEAD ni fusionar ramas. La combinación de los commits clínicos con **74c2746ef5ea788400ee26515d91424ada25f8ad** (#73) es limpia. Sobre el PHP combinado se ejecutaron los ocho casos remotos de #73 y los mismos ocho con MEDICO 2, en ambos motores. Esos fixtures de ensayo usan autorización sintética explícita para las verificaciones de Seguimiento; esto no modifica ni concede acceso al usuario productivo 13.

Este PR no copia el hotfix de teleconsultas: habilita los roles sobre `main`. Si se publica antes del #73, TELECONSULTA sigue necesitando ese hotfix separado. Cuando ambos estén autorizados, la combinación ya ensayada permite el flujo remoto con ambos roles. Revalidar SHA/base antes de cada publicación si `main` avanza.

### Capturas con datos ficticios

[Agenda propia](evidence/medico2-final-agenda.png) · [Popup del médico ID 13](evidence/id13-popup.png) · [Atención normal finalizada](evidence/id13-finished.png) · [Móvil](evidence/id13-mobile.png).

## Demo local y pasos de revisión

**http://127.0.0.1:8020/demo-guia**. Cuenta `endocrino@demo.ceosalud.invalid` (ID 13 ficticio, MEDICO 2). Contraseña y cuentas de Admisión/Julio/otro médico disponibles exclusivamente en esa guía local privada. El entorno original **8000** con el hotfix #73 continúa intacto. No se conectaron las demos a servicios externos ni se usaron cuentas/pacientes reales.

1. Como Endocrino, abrir Admisión: aparecen solo sus citas. El paciente A conserva el recorrido de reevaluación realizado y B la atención normal finalizada.
2. Abrir el paciente C sin usar desde la guía. Como Admisión, registrar llegada. Como Endocrino, iniciar atención, abrir el popup, seleccionar y confirmar procedimientos, indicar reevaluación o finalizar normalmente.
3. Para reevaluación: Admisión confirma y verifica los procedimientos; el Endocrino inicia la segunda atención y luego finaliza. Los exámenes no se realizan por solicitar retorno.
4. Intentar acceder a Seguimiento con Endocrino: denegado. Julio ficticio sí conserva su acceso autorizado y su seguimiento RM creado.
5. Como administrador, editar MEDICO o MEDICO 2: guardar las ocho casillas seguras; intentar agregar edición/pagos devuelve rechazo. No se necesitan roles adicionales.

Los escenarios finalizados y sus eventos se conservan; C es un escenario sintético adicional preparado por el seeder local, no una cita creada por el flujo clínico. Scripts, credenciales, sesiones, bases y logs están en `storage/app/qa-clinical`, ignorado por Git.

## Preparación, publicación y rollback

**Sin migraciones ni cambios de esquema.** Antes de una publicación autorizada, contrastar solo en modo lectura que la cuenta real 13 está activa, vinculada a su profesional activo, con guard web y los ocho permisos confirmados por el usuario. No asumir que una captura del editor acredita todas las relaciones de BD. No añadir permisos económicos ni asignar ADMINISTRADOR como solución.

Resolver o acordar explícitamente el bloqueo heredado de CI, revalidar los dos PR y sus SHA, y respaldar código/configuración/BD mediante el procedimiento habitual autorizado. Publicar solo el artefacto revisado; no copiar bases, credenciales ni perfiles de QA. Invalidar las cachés de aplicación/OPcache según el runbook existente y comprobar permisos efectivos; no ejecutar migraciones, seeders ni bootstraps de permisos por este cambio.

Smoke autorizado: clínica de la cuenta vinculada, citas ajenas denegadas, popup, reevaluación y fin; probar Julio y el rechazo de Seguimiento para ID 13. No ensayar sobre pacientes reales.

Rollback de código al SHA anterior, conservando todos los datos, órdenes, eventos, historiales y roles. No hay `down` ni columnas que revertir. Una cuenta MEDICO 2 puede volver a perder sus acciones con el código antiguo; considerar ese impacto y preferir corrección hacia adelante. No reasignar roles o doctor_id automáticamente ni restaurar una BD anterior para solucionar un problema de código.

No hubo merge, despliegue ni acceso/modificación de Hostinger o producción.
