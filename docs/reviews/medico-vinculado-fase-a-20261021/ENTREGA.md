# Fase A: cuenta médica verificada y recorrido de atención

Rama local: `codex/medico-vinculado-fase-a-20261021`. Base exacta: `a8bd76d8e10090decfde56c7402be585da723fcf`, cabeza publicada del Draft PR57 al comenzar. Este incremento no se publicó; el nuevo SHA se obtiene con `git rev-parse HEAD` y se entrega por separado para no introducir una referencia circular dentro del commit.

## Matriz funcional de cinco puntos

| Punto | Estado local | Evidencia |
| --- | --- | --- |
| 1. Historial rojo de reprogramación, origen libre y destino vigente | YA FUNCIONABA; CONSERVADO | Código visual sin cambios; seis escenarios originales de navegador repetidos y suites completas |
| 2. Identidad médica, inicio/finalización y pendiente/listo | CORREGIDO Y PROBADO LOCALMENTE | Vínculo administrativo explícito, seis capacidades clínicas, escritor transaccional, once escenarios de navegador y seis carreras MariaDB |
| 3. Fila de Admisión y chip pendiente sin alterar color principal | YA FUNCIONABA; CONSERVADO | CSS/presentación de Admisión intactos; navegador compara color antes/después, filtros y contadores distintos |
| 4. Restricción económica posterior al pago a ADMINISTRADOR | PENDIENTE | Fuera de este incremento; no se avanzó |
| 5. Confirmación sin adelanto por ADMISION | PENDIENTE | Fuera de este incremento; no se avanzó |

La aceptación corresponde a revisión local, no certifica producción ni autoriza merge. No se inspeccionaron cuentas reales para inferir identidades.

## Identidad y administración

`users.doctor_id` es nullable, UNIQUE y FK restrictiva hacia `doctors.id`. No modifica ningún ID ni la relación médico/cita. Un médico del catálogo puede recibir reservas con o sin cuenta. Los usuarios anteriores permanecen sin vínculo; doctor_id no se exige a Admisión, Comercial ni Administración, ni aparece en su serialización JSON genérica.

Desde Usuarios, el administrador busca y verifica primero una cuenta existente. Si no existe, utiliza Agregar Usuario del mantenimiento habitual. La columna Médico / vínculo abre un formulario separado: médico del catálogo, motivo, atestación personal de identidad y confirmación de antes/después. Un médico ya vinculado no puede asignarse a otra cuenta. Cambiar o retirar el vínculo conserva los eventos `DOCTOR_LINK_CHANGED` en `user_account_events`, con actor, fecha, motivo, valores anteriores/nuevos, versión y UUID. Nadie gestiona su propio vínculo. Vincular no asigna rol ni crea cuentas. La invalidación de sesiones afecta exclusivamente a la cuenta modificada.

El rol `MEDICO` se define mediante una migración de datos controlada. Solo si se crea un rol nuevo y sin usuarios recibe sus seis capacidades clínicas. Si ya existe, se conservan sus permisos y asignaciones, requiriendo revisión administrativa. No se conceden roles a cuentas existentes. En Roles → Actualizar Permiso aparecen las seis etiquetas. La asignación a la cuenta es explícita mediante el editor existente. Un rol MEDICO o permisos directos con capacidades ajenas al perfil clínico bloquean la activación médica hasta revisión; no se borran silenciosamente sus permisos. Un MEDICO existente bajo otro guard requiere normalización revisada antes de migrar.

## Recorrido y persistencia

1. Admisión registra llegada por el servicio existente; no inicia atención.
2. La cuenta MEDICO vinculada al médico activo responsable entra a Mi agenda médica. Solo ve citas propias confirmadas/heredadas y procedimientos pertinentes; no recibe una proyección económica.
3. Iniciar atención valida llegada/estado, permisos vivos y vínculo bajo bloqueo; conserva médico y paciente. Registra actor y hora real.
4. Reevaluación agrega un evento de indicación médica con motivo. La cita permanece EN_ATENCION y conserva su color. Se muestra el chip PENDIENTE DE REEVALUACIÓN y el filtro correspondiente. Retirar la indicación requiere motivo y agrega otro evento.
5. El médico indica procedimientos propios mediante el mismo OperationalProcedureService. Admisión marca su realización mediante las reglas existentes. El médico no puede marcar la realización administrativa.
6. Una lista no vacía completada activa LISTO conforme a la lógica existente. Una lista vacía nunca produce listo automático. Admisión/admin puede marcar listo manual para EN_ATENCION solo con indicación médica activa y sin procedimientos seleccionados; si hay pendientes se conserva el envío parcial existente, con condiciones y motivo.
7. Listo sustituye el chip pendiente; los dos filtros son independientes y excluyentes. No se usa ATENDIDO para indicar reevaluación pendiente.
8. Finalizar atención es otra acción: EN_ATENCION → ATENDIDO, sin salida física automática. La salida sigue perteneciendo al circuito administrativo existente. El retorno ATENDIDO preexistente conserva sus reglas y actores.

No se agrega cita, cobro, nuevo estado clínico ni tabla de procedimientos. Se reutilizan `appointment_operations`, `appointment_events`, `appointment_operational_reviews` y `appointment_requested_procedures`. No se sobreescriben eventos ni timestamps previos. El reloj del navegador está congelado solo en el entorno ficticio; no se introdujo un reloj artificial en producción.

## Seguridad y concurrencia

Cada acción médica verifica usuario activo, rol MEDICO/web, permisos clínicos vivos, médico activo, vínculo y propietario de la cita. Bajo transacción bloquea rol → usuario → médico → cita y revalida identidad/epoch, estado y revisión. La misma UUID reintenta sin duplicar; una revisión obsoleta o dos operaciones distintas en carrera producen conflicto. Las pruebas MariaDB ejercitan revocación y reasignación antes de finalizar, finalización antes de revocar, doble UUID, UUID distintas y doble vinculación concurrente.

Las rutas administrativas y heredadas no eluden la verificación médica. Los 403 médicos que ya estaban en a8 se conservan, ahora acompañados por un recorrido sustituto autorizado. Consultar MATRIZ_ANTES_DESPUES.md para compatibilidad por actor.

## Intervenciones compartidas inevitables

- User/Doctor: relaciones de lectura y casteo; doctor_id protegido de la edición genérica y oculto en JSON. No cambian contratos de reservas.
- UserController/UserAccountAccess existente: el nuevo servicio reutiliza bloqueo de administrador, rotación de epoch e historial. El editor de roles de usuario invalida acceso al entrar/salir de MEDICO; los perfiles ordinarios conservan su contrato. RoleController restringe solo el perfil MEDICO a capacidades clínicas.
- MvpPermissionBootstrap: evita que el nuevo MEDICO herede permisos generales/económicos del bootstrap por todos los roles. Las adiciones de los roles preexistentes se mantienen.
- AuthController/RedirectIfAuthenticated/sidebar: destino mínimo del perfil médico; destinos actuales de otros perfiles conservados.
- OperationalProcedureController: reglas de selección extraídas sin cambiar su contenido. OperationalProcedureService conserva el camino administrativo por defecto y agrega la selección médica propia, sin facultad de completar procedimientos.
- ManualReevaluationService/AppointmentReevaluationService: reconocen retorno manual dentro de la misma atención solo en el caso nuevo descrito. El retorno heredado ATENDIDO no se sustituye.
- AdmissionBoardService: pasa el actor a la elegibilidad y explica la entrada médica separada. admission-board.js cambia el botón/confirmación únicamente en el nuevo caso EN_ATENCION; no cambia CSS ni el listado general.
- Tres pruebas de catálogo/bootstrap ajustan sus expectativas al rol adicional; los 1200 casos originales siguen presentes. No se relajaron autorizaciones para hacer pasar las pruebas.

La comprobación visual encontró el selector duplicado en el nuevo formulario; se utiliza `data-native-select`, opción ya soportada por el shell, sin modificar librerías ni formularios anteriores. Las capturas esperan a que termine la transición visual del shell.

## Revisión

- [Matriz antes/después y permisos](MATRIZ_ANTES_DESPUES.md)
- [Resultados y reproducción](RESULTADOS.md)
- [Migraciones, instalación y rollback propuestos](DESPLIEGUE_Y_ROLLBACK.md)
- [Lista exacta de archivos](ARCHIVOS.md)
- [Evidencias ficticias](evidencias/README.md)

No se incorporan dependencias nuevas, workflows ni automatizaciones de despliegue. No se modificaron Hostinger, bases productivas, usuarios/pacientes reales, main ni el Draft PR.
