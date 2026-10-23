# Integración de los cinco requerimientos sobre main con PR #68

> Continuación: [ajustes finales y densidad del PR #70](AJUSTES-FINALES-PR70.md), conservando esta evidencia histórica.

Actualización tras las pruebas manuales del PR #70: [OBSERVACIONES-PR70.md](OBSERVACIONES-PR70.md) registra las cinco causas, correcciones, archivos y verificaciones nuevas. Incluye historial cronológico en Admisión, importes claros, acceso directo al editor autorizado, alta y confirmación sin adelanto atómicas y etapas/filtros coherentes. La demo manual aislada utiliza loopback8000/MariaDB33343; los resultados anteriores se conservan como antecedentes.

## Referencias y alcance

Main remoto verificado al iniciar: `a57b2d04e8302279be1c775c7e8bc913a170f209` (merge #68), después de `033c3fd60ba7620dd32d82d96f8e21c64fdea759`. Rama de integración nueva: `codex/integracion-cinco-main-20261022`, creada directamente desde ese main. El PR #69, `f201e35f3e6f985de00058bed833321ce8a894bd`, permanece como respaldo; no se fusionó ni se aplicó su commit completo. `f29019a1` es referencia histórica, no base del flujo clínico vigente.

El checkout original con cinco archivos modificados y los worktrees anteriores permanecen intactos. La integración se realiza en un worktree independiente, sin `.env`. No se accede a pacientes, bases, archivos, HEAD ni procesos de producción.

## Matriz técnica de recuperación

| Requerimiento | Ya disponible en main #68 | Recuperación selectiva #69 | Integración y validación necesaria |
|---|---|---|---|
| 1. Reprogramación | Misma cita, eventos y disponibilidad existente | RescheduleHistory paginado, referencias rojas sin ocupación, enlaces en ambas agendas, privacidad y regresiones | Conservar relaciones actuales; comprobar duración, cadena anterior/nueva y ausencia de cambios en pagos/capacidad |
| 2. Atención y reevaluación | Llegada específica ADMISION/COMERCIAL; indicación médica, confirmación operativa, segunda atención, rechazo auditado y salida | Identidad médica verificada, editor de vínculo existente y contingencia explícita | Prevalece AppointmentReevaluationService de #68. Completar checks COMERCIAL y solicitud médica; bloquear atajos heredados; lecturas actuales bajo bloqueo; probar etapas A–H con una misma cita |
| 3. Colores | Fondos completos y prioridades de #67, ajustes #68 | Señal derivada de confirmación sin adelanto y matriz de colores | Conservar paleta; corregir reglas de selección de fila naranja que sobreescribían el fondo. Escritorio/móvil y cuatro perfiles |
| 4. Economía | Caja, pagos, comprobantes, crédito, conciliación y restricciones existentes | Editor económico con revisión firmada, motivo, valores anteriores/nuevos, idempotencia, concurrencia y guardas de caja/fiscal | ADMINISTRADOR + capacidades existentes; servicio contratado constante; no insertar pagos por corregir tarifa/descuento |
| 5. Sin adelanto | Reservas privadas, disponibilidad, confirmación con pago y exoneración existentes | Excepción ADMISION con motivo/revisión, estado CONFIRMADA, evento real y señal amarilla | Mantener saldo real, cero pagos/vouchers nuevos, revalidación de disponibilidad/concurrencia y visibilidad solo tras confirmar |

## Decisiones de unificación

Se conserva la máquina de etapas y las rutas del PR #68. No se recuperan MedicalCareService, MedicalBoardService, MedicalBoardController ni rutas de otra agenda. No se recuperan los botones clínicos alternativos del #69 ni su redirección generalizada. La redirección de acceso exclusiva de Julio (usuario 2) sigue siendo la de main. Los componentes de historial, economía y confirmación se incorporan dentro de las pantallas existentes.

La selección médica utiliza los procedimientos y su auditoría existentes; COMERCIAL puede verificar sus checks y autorizar retorno manual sin recibir modificación general de citas. Se diferencia una segunda atención real del cierre administrativo por rechazo. Ese cierre conserva la indicación, procedimientos pendientes y pagos, utiliza `REEVALUACION_RECHAZADA_POR_PACIENTE` y se bloquea después del retorno médico.

## Matriz de acciones y roles

Todas las acciones requieren cuenta activa, sesión, acceso efectivo y cita visible. Los permisos citados son los existentes, no nuevos roles ni categorías.

| Acción / momento | MEDICO vinculado propio | ADMISION | COMERCIAL | ADMINISTRADOR sin vínculo médico | Otros roles |
|---|---|---|---|---|---|
| Ver citas confirmadas | Solo profesional propio cuando es cuenta clínica | Según acceso | Según acceso | Según acceso | Lectura autorizada existente |
| Registrar llegada, presencial sin inicio | No | Sí, ruta específica | Sí, ruta específica, sin UPDATE | Recorrido administrativo autorizado existente | Sin ampliación |
| Iniciar / finalizar atención | Sí, cuenta verificada y llegada previa; finalizar solo con retorno resuelto | No firma médica | No firma médica | No firma de otro médico | No |
| Indicar / retirar reevaluación | Sí, misma atención; retirada con motivo y restricciones | No | No | No, salvo su propia identidad médica verificada | No |
| Confirmar indicación pendiente | No por rol clínico solo | Sí | Sí | No por rol ADMINISTRADOR solo | No |
| Verificar procedimientos | No por rol clínico solo | Sí | Sí | Sí | No |
| Autorizar retorno manual | No por rol clínico solo | Con UPDATE existente | Sí, acción limitada | Con UPDATE existente | No |
| Iniciar segunda evaluación | Sí, confirmado y listo, misma cita | No | No | No por rol administrador solo | No |
| Cierre por rechazo del paciente | No por rol clínico solo | Sí, motivo y confirmación; antes del retorno | Igual | No por rol administrador solo | No |
| Salida física después de ATENDIDO | No por rol clínico solo | Con UPDATE existente | No | Con UPDATE existente | No |
| Corregir tarifa/descuento después de pago | Solo si además ADMINISTRADOR autorizado | No | No | Sí, revisión, motivo, contrato y guardas económicas | No |
| Confirmar sin adelanto | No | Sí, capacidades existentes, motivo y confirmación | No | No por rol administrador solo | No |

## Identidad, esquema y migraciones

Se conserva `appointments.doctor_id` y `users.doctor_id`. El primero identifica al profesional de la cita; el segundo permite demostrar que la cuenta que firma es ese profesional. Nombre, correo, rol ADMINISTRADOR o un ID enviado por el navegador no sustituyen esa relación. El editor de usuarios muestra un selector de profesionales y protege cambios concurrentes/auditados; el usuario no escribe IDs. No se modifica el vínculo histórico Julio usuario 2 / profesional 1.

Se conservan exactamente las tres migraciones históricas recuperadas del #69, aplicadas anteriormente según el antecedente del usuario: `2026_10_21_200000_define_direct_appointment_economic_permissions`, `2026_10_21_210000_link_users_to_doctors`, `2026_10_21_210100_define_medical_role_and_permissions`. No se crean nuevas tablas ni estados ENUM de MySQL. No se deben volver a ejecutar ni revertir a ciegas: comprobar nombres en `migrations`, columna, tipo, índice, FK y grants efectivos contra el respaldo. Las capacidades médicas/económicas son las de esas migraciones históricas, no una nueva categoría creada para integrar #68.

Pilar sin cuenta médica verificada permanece **pendiente de validación clínica completa**. La contingencia recuperada requiere configuración explícita, rol operativo autorizado, motivo y confirmación; registra fuente administrativa y `physician_signed=false`. No permite producir indicaciones, retorno ni firmas de un médico distinto. Por defecto está deshabilitada; no se declara como sustituto del recorrido clínico.

## Plan de despliegue y rollback (solo preparación)

1. Revisar el nuevo Draft PR y comprobar otra vez main, Hostinger y el estado real de la publicación automática antes de autorizar cualquier publicación. No asumir que sigue deshabilitada.
2. Respaldar BD completa, carpeta física `/home/u676842872/domains/srv-prd01.ceosalud.com/public_html`, uploads privados, permisos y metadatos del despliegue. Custodiar `.env` por el mecanismo operativo existente, sin editarlo ni adjuntarlo al PR. Verificar restauración en entorno aislado y registrar SHA/checksums.
3. Preflight de solo lectura: PHP CLI `/usr/bin/php` 8.3.35, extensiones, main aprobado, `migrations`, tres migraciones históricas y vínculo de Julio. No ejecutar rollback de migraciones ni alterar IDs históricos. Validar las cuentas/roles reales con responsables sin exponer datos de pacientes.
4. Tras aprobación externa, publicar el SHA revisado por el mecanismo Hostinger. Este trabajo no hace merge ni despliegue. Si el esquema coincide, no hay migraciones pendientes de esta integración; una discrepancia detiene el despliegue hasta resolverla expresamente.
5. Verificar con datos ficticios y personal autorizado los recorridos, permisos, privacidad, caja y auditoría. Pilar requiere validación independiente con cuenta propia antes de declarar completo su recorrido clínico.
6. Rollback de código al respaldo/SHA anterior aprobado por el operador. Conservar BD y eventos nuevos; no borrar operaciones, pagos ni firmas para retroceder. Si fuera necesario restaurar una BD, primero detener escrituras, preservar/exportar transacciones posteriores y acordar reconciliación. No restaurar una copia antigua sobre actividad nueva sin ese procedimiento.

## Evidencias

Los resultados de 1.283/1.310/364 pruebas y las capturas del PR #69 son históricos y no acreditan esta integración. Los resultados nuevos, fallos encontrados y archivos se registran en `RESULTADOS-INTEGRACION.md` al completar la validación. Se usan únicamente SQLite en memoria, MariaDB local propio en 127.0.0.1:33342 y navegador ficticio en 127.0.0.1:8108, con servicios externos bloqueados.
