# Matriz de permisos

## Privacidad y permisos en la segunda ronda

Motivo y resumen requieren el mismo acceso autenticado de Admisión (`appointment.mvp.access`, `appointment.view` y visibilidad de la cita). No se abre un endpoint público ni se concede supervisión. Las pruebas incluyen ADMINISTRADOR, ADMISION, COMERCIAL y RECEPCION para el bloqueo del creador heredado; consulta y seguimiento mantienen permisos previos. El envío manual de ADMINISTRADOR sin supervisión sigue en 403. La reserva privada conserva su responsable: otra sesión no puede cobrarla solo por conocer su ID.

Base común: usuario activo, sesión válida, feature flag operativo, appointment.mvp.access, appointment.view y Appointment::visibleToAgendaUser. Una reserva privada ajena devuelve 404 también para ADMINISTRADOR; no hay bypass de visibilidad por rol.

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
