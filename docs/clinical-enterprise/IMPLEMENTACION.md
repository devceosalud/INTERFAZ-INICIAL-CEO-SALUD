# Clinical Enterprise, procedimientos, alertas y seguimiento médico

## Base y alcance

Rama independiente `codex/clinical-enterprise-integral-20261023`, creada desde `main` remoto `a4e2a9b0f9e517c55be10232321391b1dc970e73`. El PR #71 permanece en Draft, sin merge y sin modificaciones. No se utilizó su rama como base ni se sustituyeron archivos actuales por versiones antiguas. La situación productiva conocida procede del usuario; no se consultó Hostinger ni se verificó producción por SSH.

La investigación previa está en [INVESTIGACION.md](INVESTIGACION.md). La dirección visual es el prototipo B aprobado por el usuario, verificado en sus archivos locales y contrastado con el ERP real. Se aplicó la metodología de `refero-design`: referencia bloqueada, decisiones explícitas y comprobación del resultado renderizado. **No se consultó Refero MCP**, que no está conectado. Popup y alertas adaptan esa dirección a los componentes existentes. El seguimiento utiliza ahora el prototipo C local aprobado para esta revisión, sin atribuirlo a Refero MCP.

## Matriz final

| Requerimiento | Implementación y reutilización | Persistencia | Aceptación |
|---|---|---|---|
| B solo en Admisión | Indicadores en rail lateral; lista principal; detalle condicional; cuatro columnas; misma navegación, filtros, IDs y eventos. Estilos exclusivos y adaptación puntual del render existente. | Sin cambios por presentación. | Chromium: escritorio, laptop de altura636, móvil, foco y regreso a lista. |
| Turquesa único | `admissionBackground()` transforma únicamente el fondo `#99f6e4` en `#74dccb` dentro de Admisión. El catálogo compartido y las prioridades del servidor se conservan. | Ninguna escritura. | 208 verificaciones cromáticas; Operativa contra main en cinco anchos. |
| Popup de procedimientos | Diálogo nativo accesible, búsqueda sin tildes/mayúsculas, categorías, selección múltiple y resumen. Reutiliza solicitud, revisión, eventos, versión y UUID existentes. | Dos columnas nullable en solicitudes existentes: familia y etiqueta histórica. Las solicitudes específicas usan el UUID `c:…` existente. | Selección específica + MRI + cirugía, reapertura, doble clic, 0/3 a3/3 y reevaluación real. |
| Alertas y roles | Alerta operativa del paciente; motivo cifrado; hechos observables y confirmación; edición operativa, resolución auditada; consulta por acceso efectivo. Pestañas y API de Admisión protegidas. Ambas rutas de autorización manual quedan limitadas a operadores. | Una fila vigente por paciente y ledger de eventos cifrados. No se agregan atributos al maestro serializable de pacientes. | Roles simples/mixtos, acceso directo, paciente cambiado, versiones, concurrencia y motivo por hover/foco/pulsación. |
| Seguimiento médico | Una entrada por solicitud relevante, creada dentro de la misma transacción médica. Pantalla personal de la cuenta médica verificada2 (Quiroz), con estados independientes de la cita y estética C. | Estado longitudinal + ledger clínico separado del historial operativo compartido. | MRI/cirugía; otro médico403 en el módulo, registro ajeno404 para el propietario autorizado; administrador puro403; retiro/reselección; actualización auditada; sin citas, pagos ni realización automática. |

## Refinamiento posterior del PR72

El estado vigente está documentado en [REFINAMIENTO-PR72.md](REFINAMIENTO-PR72.md) y [QA del refinamiento](qa/refinement/README.md). Admisión incorpora rail plegable persistente, detalle más ancho y señales repartidas. Exoneraciones autorizadas son amarillas en ambas agendas; el resto conserva sus prioridades. Los selectores de médicos reutilizan bootstrap-select y admiten búsqueda normalizada, teclado y opciones dinámicas.

La orden conserva atributos por procedimiento y diagnóstico/observaciones cifrados. Solo el médico vinculado con capacidad clínica puede consultar o escribir la cabecera clínica; el administrador puro conserva únicamente la selección técnica administrativa anterior. No se le concede acceso clínico por ser administrador.

## Catálogo e identidad clínica

El catálogo real contenía siete opciones genéricas. Se conservan y se incorpora **Cirugía** por petición expresa del usuario, sin precio ni programación automática. La revisión posterior incorpora las denominaciones y atributos de la orden aportada por el usuario en imagen/PDF; no se derivan de tarifas ni de inferencias médicas. Véase REFINAMIENTO-PR72.md para la referencia y sus límites.

El médico puede registrar varias solicitudes específicas, cada una con UUID, etiqueta y familia inmutables después de confirmar. Por ejemplo, la radiografía de brazo izquierdo de DEMO70 es **una solicitud ficticia específica**, no un nuevo servicio tarifado. Las opciones aprobadas posteriores se incorporan explícitamente en `config/operational_procedures.php`; el seguimiento se configura por familia o por clave concreta. No se infiere del precio.

La selección de una cuenta con MEDICO exige `ClinicalAccess`, capacidad clínica de procedimientos y la cita propia en atención, también si acumula ADMINISTRADOR. El administrador puro conserva su autorización administrativa previa para seleccionar procedimientos, pero no genera una firma médica ni un seguimiento clínico por sí solo. Una confirmación posterior del médico vinculado sí puede generar el seguimiento, preservando la solicitud y su auditoría anteriores.

No hay backfill automático de seguimientos históricos: no se atribuyen retrospectivamente indicaciones a médicos sin verificar su identidad. El vínculo `users.doctor_id` y `appointments.doctor_id` permanece intacto. Julio ficticio mantiene usuario local2 y profesional ficticio1. Pilar continúa pendiente de validación clínica completa si no tiene cuenta médica verificada; no se simula su firma.

## Matriz de acceso efectivo

Las capacidades existentes, actividad de la cuenta, visibilidad de la reserva y vínculo profesional siguen siendo requisitos. No se crean roles ni categorías de permisos.

| Acción | ADMISION | COMERCIAL | MEDICO | ADMINISTRADOR puro |
|---|---|---|---|---|
| Ver Admisión | Acceso actual | Acceso actual | Acceso clínico actual | Acceso actual |
| Seleccionar procedimientos | No en la configuración actual | No en la configuración actual | Sus citas en atención + capacidad existente | Autorización administrativa existente |
| Marcar procedimientos realizados | Acceso existente | Acceso existente | Restricción existente; el rol médico puro no completa | Acceso existente |
| Crear/editar alerta | Sí, con acceso operativo | Sí, con acceso operativo | No | No |
| Consultar alerta | Citas visibles | Citas visibles | Solo sus pacientes autorizados | Sí, según visibilidad |
| Resolver alerta | Sí | Sí | No | Sí, gestión administrativa explícita y auditada |
| Documentos e historial en Admisión | No; API específica403 | No; API específica403 | Documentos clínicos autorizados de sus citas | Capacidades documentales anteriores |
| Autorizar retorno manual | Acceso operativo + permiso de modificación existente | Acceso operativo, sin conceder modificación general | No | No, incluso con supervisión económica/operativa |
| Seguimiento médico | No | No | Solo cuenta2 verificada + vínculo y acceso clínico | No automático |
| Actualizar seguimiento | No | No | Cuenta2; además requiere capacidad clínica de procedimientos | No automático |

**Prioridad:** cualquier MEDICO o ADMINISTRADOR impide editar alertas y autorizar retorno manual, aunque también tenga un rol operativo. ADMINISTRADOR+MEDICO usa el vínculo médico para procedimientos/seguimiento/documentos clínicos; conserva sus capacidades económicas administrativas existentes. Los roles mixtos no admitidos por `ClinicalAccess` siguen bloqueados, sin relajar esa política.

La restricción de documentos se aplica al detalle y a las rutas específicas de **Admisión**. El administrador puro conserva la carga/reemplazo ya autorizados por el componente anterior. El médico dispone de lectura clínica acotada; no recibe gestión documental general automáticamente. Las rutas y capacidades legítimas de documentos de **Agenda operativa** permanecen iguales. No se usan las pestañas ocultas como única barrera.

La etiqueta «Paciente problema» se agrega a las citas autorizadas de Admisión; no se añade a Operativa para cumplir la prohibición expresa de modificar esa agenda. Su motivo no viaja en el feed de filas ni en búsquedas: se consulta bajo autorización al interactuar con la etiqueta. Resolver conserva el motivo original y el autor, con un evento separado del responsable de la resolución.

## Separación de ciclos

- Solicitar procedimientos no indica automáticamente reevaluación, no confirma el retorno operativo y no realiza exámenes.
- Crear seguimiento no modifica la cita, pagos, reservas, realización ni estado de reevaluación.
- Retirar una solicitud conserva el seguimiento y marca su fuente retirada. No lo cancela ni lo completa ficticiamente; el médico debe decidir y justificar.
- Pendiente, En coordinación, Programado, Realizado y Descartado/cancelado pertenecen al seguimiento. Programado exige fecha prevista y no reserva ningún horario.
- Terminar la consulta original no cierra seguimientos futuros. La salida física sigue separada de la finalización clínica.

## Salvaguardas y límites

Motivos de alertas y observaciones de seguimiento se cifran con el `APP_KEY` existente. La auditoría privada se separa del ledger de procedimientos visible a personal operativo. Se mantienen control de versiones, transacciones y el ledger de idempotencia existente; las claves reutilizadas para otro contenido devuelven409. Las modificaciones de paciente asociado invalidan el formulario de alerta y bloquean la exposición de seguimiento ajeno.

Las categorías de incidencia son neutrales y operativas. Se exige describir hechos observables, motivo, fecha y declaración explícita; se rechazan insultos y clasificaciones diagnósticas expresas del filtro. Un filtro léxico no sustituye revisión humana: requiere formación y supervisión administrativa de la calidad de los motivos. No se asignan diagnósticos ni categorías discriminatorias.

El catálogo específico de producción necesita aprobación clínica real antes de ampliarse. No se presupone que una etiqueta de servicio económico sea una orden o procedimiento clínico. Las migraciones nuevas y los enlaces médicos productivos deben verificarse en una publicación futura autorizada; no se ejecutó ninguna operación productiva.

Resultados y capturas: [qa/README.md](qa/README.md). Esquema, respaldo y rollback: [DESPLIEGUE-Y-ROLLBACK.md](DESPLIEGUE-Y-ROLLBACK.md). Lista concreta de cambios: [ARCHIVOS.md](ARCHIVOS.md).
