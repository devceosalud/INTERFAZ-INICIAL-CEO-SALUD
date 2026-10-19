# Matriz de permisos

Estado vigente: [cuarta ronda, gestión de citas, procedimientos personalizados y teleconsulta](RONDA_4_DIAGNOSTICO_Y_GUIA.md). Los resultados y decisiones anteriores se conservan como antecedentes.

## Operaciones integradas de retorno al médico

| Operación | ADMINISTRADOR | ADMISION | COMERCIAL | Otros |
|---|---|---|---|---|
| Marca directa ATENDIDO | Con UPDATE | Con UPDATE | Sí, sin UPDATE | No |
| Selección de procedimientos | Sí | No | No | No |
| Realizados solicitados | Sí | Sí | No | No |
| Envío/retirada por procedimientos | Con supervisión específica | Sí | No | No |
| Historial y cola visibles | Con acceso/visibilidad | Con acceso/visibilidad | Con acceso/visibilidad | Solo acceso de lectura ya autorizado |

Todas requieren usuario activo, MVP_ACCESS, VIEW y visibilidad efectiva. No se conceden permisos/roles automáticamente. Marcar directamente no autoriza care, pagos, precios, documentos ni supervisión. Se conserva el contrato HTTP v1 y su auditoría. El historial no requiere UPDATE. En tercera ronda ADMIN autorizado dispone de lectura operativa de reservas ajenas; los demás roles conservan su visibilidad.

## Privacidad y permisos en la segunda ronda

Motivo y resumen requieren el mismo acceso autenticado de Admisión (`appointment.mvp.access`, `appointment.view` y visibilidad de la cita). No se abre un endpoint público ni se concede supervisión. Las pruebas incluyen ADMINISTRADOR, ADMISION, COMERCIAL y RECEPCION para el bloqueo del creador heredado; consulta y seguimiento mantienen permisos previos. El envío manual de ADMINISTRADOR sin supervisión sigue en 403. La reserva privada conserva su responsable: otra sesión no puede cobrarla solo por conocer su ID.

Base común: usuario activo, sesión válida, feature flag operativo, appointment.mvp.access, appointment.view y Appointment::visibleToAgendaUser. La tercera ronda añade el scope explícito de lectura visibleToOperationalUser para ADMIN activo con MVP_ACCESS/VIEW. El scope base y los escritores existentes conservan su privacidad; no se amplían pagos, vouchers ni cambios de estado.

| Acción estructurada | ADMINISTRADOR | ADMISION | COMERCIAL / otros |
|---|---|---|---|
| Consultar procedimientos de cita visible | Sí | Sí | Lectura |
| Seleccionar/confirmar/retirar solicitudes | Sí | No | No |
| Marcar/desmarcar realizados solicitados | Sí | Sí | No |
| Enviar/retirar reevaluación manual | Solo con appointment.operational_reevaluation.supervise | Sí | No |
| Retroceder atención | Con appointment.update | Con appointment.update | No |
| Crear desde adaptador heredado | 403 | 403 | 403 para todos |
| Consultar seguimiento heredado | Con acceso existente | Con acceso existente | Con acceso existente |

El creador heredado está bloqueado incluso si el usuario acumula roles. Agenda operativa conserva sus capacidades explícitas de creación, pago, adicional y fuera de horario. No se otorga supervisión automáticamente, no se asignan roles y no se cambia el bootstrap de permisos. Si se decide habilitar supervisión, se debe registrar esa capacidad en el catálogo y asignarla expresamente al usuario autorizado mediante el procedimiento administrativo existente, con revisión humana; esta entrega no lo ejecuta en producción.

Permisos financieros: appointment.create + appointment.view para registro/pago; appointment.payment.submit para dinero positivo; appointment.additional.create para adicionales; capacidades de exoneración y autorización expresa permanecen. Revocar permiso de pago también impide reproducir una operación con dinero positivo ya completada.

Las tareas **libres históricas** de AdmissionTaskService mantienen su comportamiento anterior, incluido su permiso de completado. Esos permisos no autorizan a solicitar procedimientos, completar procedimientos ni enviar reevaluaciones. Se prueban peticiones HTTP directas con permisos de tareas para demostrar esa separación.

Las rutas de escritura validan datos y campos permitidos, tienen CSRF web y las nuevas rutas de procedimientos están limitadas a 60 solicitudes/minuto. El servidor vuelve a filtrar la cita dentro de la transacción. El expected_version es de toda la solicitud estructurada: ante dos operadores simultáneos se rechaza el cambio obsoleto y se exige consultar nuevamente; no se sobrescribe silenciosamente.

La nueva cola no incluye documentos de identidad, contactos, diagnósticos, observaciones clínicas, comprobantes, datos bancarios ni precios. La información autorizada se renderiza con textContent. Los endpoints privados usan Cache-Control private/no-store. Las páginas de ambas agendas y las vistas heredadas de citas que muestran la cola no cargan Clarity.

## Cuarta ronda

ADMIN transcribe procedimientos personalizados por decisión expresa del usuario. Solo ADMIN/ADMISION con UPDATE y visibilidad de escritura cambian modalidad antes de iniciar atención y con confirmación, incluso con pagos; COMERCIAL no puede enviar el campo, aunque coincida con el valor actual. notes del gestor exige UUID/revisión y rechaza inyección financiera. Reprogramación conserva RESCHEDULE; favoritos solo requieren acceso activo de lectura y solo afectan al usuario autenticado. No se añaden privilegios generales ni un nuevo descuento100%. [Matriz detallada](RONDA_4_DIAGNOSTICO_Y_GUIA.md#permisos-de-cuarta-ronda).
