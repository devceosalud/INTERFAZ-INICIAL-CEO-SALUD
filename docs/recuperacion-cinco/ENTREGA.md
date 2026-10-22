# Recuperación controlada de los cinco requerimientos

Rama: `codex/recuperacion-cinco-requerimientos-20261022`, creada directamente desde `033c3fd60ba7620dd32d82d96f8e21c64fdea759`. Referencias analizadas: `f29019a1f3649149a31af3447e67e009970a7277`, PR64, PR65, PR66 (`2f9c6405`) y PR67. No se fusionó ninguna rama previa. No se modificó main, producción, .env ni los PR existentes. El checkout original, que pertenecía a otra rama y tenía cambios locales, se conserva intacto.

La matriz previa a programar está en [INVESTIGACION.md](INVESTIGACION.md). La lista exacta de cambios está en [ARCHIVOS.txt](ARCHIVOS.txt); las reglas por estado y rol están en [MATRIZ-ACCIONES.md](MATRIZ-ACCIONES.md). El SHA definitivo y el Draft PR se registran en la entrega de la conversación y en GitHub, sin una referencia circular al propio commit.

## Matriz antes/después

| Requerimiento | Antes: main 033c3fd6 | Recuperación y comportamiento de la candidata | Evidencia |
|---|---|---|---|
| 1. Reprogramación | Persistía REPROGRAMADA, pero proyección histórica incompleta | Misma cita en destino; referencias rojas de origen, fecha/hora anterior y nueva, duración, enlaces a cita vigente en ambas agendas. Las referencias no ocupan slots ni suman pagos/citas. Paginación y privacidad conservadas | RescheduleVisualHistoryTest; JavaScript; navegador desde editor existente, enlace real y disponibilidad del intervalo original |
| 2. Atención y reevaluación | Carecía del recorrido clínico integrado de la rama avanzada | Llegada Comercial/Admisión; inicio médico propio con hora de llegada; botones Reevaluación/Finalizar en el panel actual; pendientes, checks auditados, 2/3 pendiente, 3/3 listo naranja, retorno manual/parcial autorizado sin falsificar realizaciones; bloqueo de finalización pendiente; finalización y salida separadas | IntegratedAdmissionCareTest, VerifiedMedicalCareTest, CommercialArrivalTest, UnifiedReevaluationTest, pruebas de identidad y concurrencia; Chromium escritorio/móvil y lectura de BD ficticia |
| 3. Colores | PR67 ya estaba fusionado | Bloque de fondos completos de PR67 conservado; prioridades del catálogo intactas, selección/hover conservan fondo; etiqueta nueva de excepción auditada usa amarillo sin implicar pago. No se cambian estados para colorear | Prueba JavaScript original de main conservada; AdmissionColorCompatibilityTest; matriz Chromium con fondos computados en fila y cada celda |
| 4. Economía | Guardas y operaciones económicas anteriores, sin el editor revisado de la rama avanzada | Recupera AppointmentEconomicEdit/ExistingAppointmentPricing en el editor existente, exclusivamente ADMINISTRADOR con capacidades históricas. Revisión, confirmación, motivo, antes/después, idempotencia y auditoría. Conserva servicio, catálogo global y pagos al modificar tarifa/descuento. Mantiene bloqueos de turnos cerrados, conciliación, documentos fiscales, créditos/devoluciones y contratos no soportados | AppointmentEconomicEditTest y EconomicEditConcurrencyTest; navegador revisa/guarda descuento tras adelanto60: precio120→100, saldo60→40, mismo pago60 y auditoría visible |
| 5. Confirmar sin adelanto | Exigía pago positivo o exoneración; la rama avanzada solo exponía confirmar exoneración | Acción distinta solo ADMISION, confirmación explícita con motivo y evento CONFIRMACION_SIN_ADELANTO. Reutiliza ReservationPaymentService, UUID/revisión y validador de disponibilidad. Usa CONFIRMADA, PENDIENTE y saldo real; no crea pago ni voucher. Se vuelve visible; reserva privada ajena sigue oculta | AdmissionWithoutDepositTest, JavaScript de cancelación/reintento/selección tardía y Chromium con formularios reales, fila/celdas amarillas y lectura posterior por Comercial |

## Código recuperado y descartado

Se recuperaron selectivamente los cambios funcionales necesarios entre el antecesor común y f29019a1, conservando las adiciones actuales de main. La referencia avanzada no fue tomada como base Git ni fusionada. Las diferencias productivas de main respecto al antecesor común eran las de PR67: se conservaron su bloque CSS y variable de fondo JS y su prueba propia. El JS integrado aporta además el color de contorno del servidor; el catálogo sigue calculándose en backend. El único ajuste de layout necesario en CSS es el séptimo contador y la envoltura de filtros para Pendientes.

Dependencias conservadas:

- Identidad: ClinicalAccess/ClinicalCapability, modelos User/Doctor, UserDoctorLink, invalidación de sesiones, preservación de ADMINISTRADOR + MEDICO y guardas del editor de roles/bootstrap. Sin ellas una cuenta podría actuar por otro profesional o perder el vínculo/roles heredados.
- Atención: MedicalCareService y proyección MedicalBoardService reutilizadas por Admisión; APIs históricas protegidas y redirección del GET médico. AdmissionAccess mantiene la proyección clínica propia y restringe economía/contacto/documentos a cuentas solo médicas.
- Economía: snapshot mínimo, tasa adicional de catálogo, cálculo de posición económica y serialización con cierre de caja/documentos de venta. Necesarios para no modificar dinero con estados financieros incompatibles o una revisión obsoleta.
- Historial: RescheduleHistory, eventos, anotaciones y componentes existentes; los lectores de presentación siguen separados de la ocupación.
- Tres migraciones históricas exactas y permisos que ya existían en el esquema informado. No hay una cuarta migración de recuperación.

Se descartaron la vista, CSS y JS de Mi agenda médica; no existe una segunda pantalla operativa. Se descartó la página independiente de vinculación: su GET de compatibilidad redirige al editor de usuario existente. El profesional se elige por nombre/CMP, sin escribir un ID. Se descartaron documentos de despliegue antiguos basados en la rama apilada y la restauración del CSS del PR66. De PR66 se reutilizan pruebas/fixtures/harness visuales, adaptados a esta candidata y sin presentar una reproducción histórica como captura productiva. Se conservan las funciones previas de edición, reservas, caja y documentos.

Cambios indispensables adicionales: excepción sin adelanto; etiqueta visible en lista y detalle; señal derivada de CONFIRMADA, posición económica y evento de autorización para no recolorear citas históricas no autorizadas; lectura de ese evento en lote; bloqueo de nuevos envíos clínicos durante refresco del panel; exigencia de hora de llegada incluso si un estado heredado dice EN_ESPERA.

## Roles, permisos y doctor_id

No se añadieron módulos, tablas, roles ni categorías de permisos respecto del catálogo ya aplicado. Main no contenía en fuente las tres migraciones históricas: aparecen recuperadas en el diff, pero no son permisos nuevos para producción. Se reutilizan MEDICO y las capacidades clínicas/económicas definidas por esas migraciones. La excepción sin adelanto no crea una capacidad ni reutiliza exoneración: exige el rol ADMISION y las capacidades de acceso/consulta/creación existentes. Un ADMINISTRADOR sin ADMISION no obtiene esa excepción.

Se conserva `users.doctor_id`, nullable/único/FK restringida: es la correspondencia verificable entre identidad de acceso y profesional. Se comprueba usuario/médico activos, capacidad vigente y cita del mismo doctor; los cambios de vínculo invalidan sesiones y se auditan. No hay otro mecanismo equivalente demostrado. `appointments.doctor_id` permanece intacto y los IDs históricos no se reasignan. El código preserva la combinación del usuario 2 (Julio), ADMINISTRADOR + MEDICO con profesional 1; se prueba con identidades ficticias, sin leer/modificar su cuenta real.

**Límite de Pilar:** sin una cuenta médica vinculada y verificada, no se considera completo su recorrido clínico autenticado. La contingencia administrativa existente exige configuración, rol, motivo, llegada y secuencia válidos, registra el actor real y `physician_signed=false`. No puede indicar reevaluación clínica ni firmar por Pilar. Se cierra al existir cualquier vínculo actual o histórico verificado, incluso si la cuenta está desactivada. El vínculo debe validarlo otro administrador desde el editor existente; no se asigna por nombre, por ID2 ni automáticamente.

## Esquema MySQL

Sin nuevos estados MySQL ni nuevas tablas. Se utilizan appointment_events, appointment_operations y las estructuras de procedimientos e historial existentes. La excepción es un evento de auditoría y una presentación derivada, no una columna nueva. Los tres archivos recuperados son:

1. `2026_10_21_200000_define_direct_appointment_economic_permissions.php`: definiciones existentes de corrección económica; no asigna permisos a usuarios/roles.
2. `2026_10_21_210000_link_users_to_doctors.php`: columna histórica users.doctor_id nullable, índice único y FK a doctors con RESTRICT. No elimina ni reasigna usuarios. Su reversa protege vínculos e historial; no se debe ejecutar en producción.
3. `2026_10_21_210100_define_medical_role_and_permissions.php`: definiciones históricas MEDICO y sus capacidades; no altera las asignaciones ni los permisos de un rol existente. La ambigüedad de guards detiene la operación.

Un servidor que ya registra las tres no necesita volver a ejecutarlas. Si columna, índices o registro de migraciones discrepan, detener publicación para revisión. Las pruebas de migración verifican conservación de filas heredadas y restricciones en SQLite/MariaDB; no sustituyen la comprobación del esquema real que debe hacer el responsable. No se ejecutaron migraciones inversas ni productivas.

## Pruebas y límites

Los resultados finales y los fallos encontrados/corregidos se documentan en [PRUEBAS.md](PRUEBAS.md); las evidencias ficticias están en `qa/`. Las pruebas anteriores de los PR no se cuentan como pruebas de esta candidata.

La compatibilidad ERP se comprueba mediante las suites completas de autenticación, reservas privadas, catálogo, horarios, disponibilidad, pagos, comprobantes, caja, conciliación, permisos, edición y documentos, además de los recorridos nuevos. No se afirma validación de personalizaciones ignoradas de Hostinger ni de datos/cuentas reales. Tampoco se afirma que toda operación fiscal o conciliada admita corrección directa: sus bloqueos de seguridad permanecen y se debe utilizar su mecanismo financiero existente.

## Publicación pendiente de decisión humana

Draft PR directamente contra main, sin merge ni auto-merge. [DESPLIEGUE-Y-ROLLBACK.md](DESPLIEGUE-Y-ROLLBACK.md) prepara preflight, respaldo privado verificado, ventana breve, instalación y rollback de código conservando BD/auditoría. Ninguno se ejecutó. Un respaldo productivo no puede crearse en una tarea que prohíbe tocar producción; se documentan sus requisitos para el responsable.
