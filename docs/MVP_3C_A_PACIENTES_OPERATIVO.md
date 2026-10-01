# MVP-3C-A / MVP-3C-B / MVP-3D — pacientes operativos e integración con Agenda

> Implementación local. La ficha maestra admite alta y actualización controladas; la creación de
> citas continúa fuera de este incremento.

## Hallazgo estructural

No existe hoy una entidad persistida que represente una atención ambulatoria con un número de
registro propio. El modelo `Appointment` representa una cita programada y conserva algunos hitos
operativos heredados, pero no es un episodio clínico. Tampoco existe un modelo `Encounter`,
`Attention` o `Consultation` con identidad y ciclo de vida propios.

Por ese motivo, el listado actual proyecta fichas maestras de `patients`: una fila es un paciente,
no una atención. La interfaz reserva `N.° Registro` y `Fecha de atención`, pero muestra `—` y explica
su estado pendiente. No reutiliza `patients.id`, `appointments.id`, `numero_cita` ni `fecha_cita`.

## Fuentes de columnas

| Columna | Estado | Fuente exacta |
| --- | --- | --- |
| N.° Registro | PENDIENTE | Requiere entidad futura de atención/encounter. |
| HCE | CONFIRMADO | `patients.historia_clinica`, sin reformatear. |
| Documento | CONFIRMADO | `patients.tipo_identificacion` + `patients.numero_identidad`. |
| Paciente | CONFIRMADO | `patients.apellido_paterno`, `apellido_materno`, `nombre`. |
| Fecha de atención | PENDIENTE | No existe fuente real; no se sustituye por `fecha_registro` ni `fecha_cita`. |
| Usuario | CONFIRMADO | `patients.user_id → users.name`: usuario que registró la ficha maestra. No se presenta como usuario de atención. |
| Estado | CONFIRMADO | `patients.estado`: estado de la ficha maestra. |
| Médico | PENDIENTE | Solo existe relación mediante cita; no hay relación con atención. |
| Especialidad | PENDIENTE | Solo deriva de servicio/médico programados; no hay relación con atención. |
| Sede | PENDIENTE | `appointments.site_id` identifica contexto de cita, no una atención persistida. |

El filtro Fecha aparece con el día actual por defecto como preparación visual, pero no se aplica a
las filas mientras falte una fuente de atención. Los filtros de tipo/número de documento, HCE y
nombre consultan columnas reales de `patients`.

## Superficies e interacción

El módulo usa una sola página con dos superficies excluyentes:

1. listado operativo compacto, con filtros y tabla dominante;
2. ficha del paciente, nueva o existente, que reemplaza el área principal.

Un clic selecciona la fila. El doble clic no abre ninguna ficha. `Enter` es una acción deliberada
de teclado y el clic derecho abre un menú contextual con Abrir ficha, Agregar nuevo y
Eliminar/Desactivar deshabilitado. Sobre espacio vacío el menú ofrece Agregar nuevo.
Volver muestra el listado conservado en el DOM y recupera selección, filtros y scroll interno.

Eliminar permanece visible y deshabilitado. `POST /patients` y `PUT /patients/{patientId}` están
protegidos en backend por autenticación y por `PatientWriteAccess`: `ADMISION`, `RECEPCION` y
`COMERCIAL`. `ADMINISTRADOR` conserva la lectura y no escribe. No había una permission
`patient.create`, `patient.update` o `patient.manage` para reutilizar. El alta conserva como
`patients.user_id` al usuario autenticado que registró la ficha; una actualización no reemplaza
ese creador.

Reconciliación previa a despliegue: en la base local conocida no existe todavía el rol
`COMERCIAL` ni un usuario con ese rol. El código ya lo admite; hay que crear el rol y asignarlo
antes de que Comercial opere estas rutas.

## Integración con Agenda

- El buscador local conserva `patient_id` cuando encuentra un paciente activo.
- Un documento inexistente habilita Registrar paciente y abre una ficha modal dentro de Agenda,
  conservando médico, especialidad, fecha, hora y sede seleccionados.
- Guardar sin agendar crea o actualiza la ficha, cierra el modal y no coloca `patient_id` en el
  Registro rápido. Vale igual para un paciente nuevo y para uno existente. Médico, fecha y hora
  permanecen como estaban.
- Guardar y agendar crea o actualiza la ficha y devuelve el `patient_id` al Registro rápido, también
  si la ficha ya existía. El texto es continuidad de flujo: este incremento todavía no crea la cita.
- Completar registro abre la misma ficha modal para el `patient_id` de una cita o búsqueda
  existente y permite una actualización real cuando el usuario es de Admisión, Recepción o Comercial.
- Día, Semana y Mes consumen los mismos motores y feeds aprobados; no se cambió su geometría.

El usuario autenticado aparece como **Quién agenda**. La persistencia futura corresponde a
`appointments.user_id`, pero no se escribe aún porque Agenda todavía no crea citas. El
**comercial dueño** se selecciona entre usuarios con rol COMERCIAL y se conserva en el estado del
borrador para el siguiente paso. No existe columna o relación con esa semántica; no se reutiliza
`patients.user_id` ni `appointments.responsible_user_id` y no se simula un guardado.

## Campos persistidos y pendientes

Se guardan únicamente columnas existentes de `patients`: documento, identidad, teléfono,
género, fecha de nacimiento, canal, correo, dirección, estado civil, ocupación, grado de
instrucción, familiar de contacto y medio de interacción. La operación usa transacción,
validación de catálogos activos y unicidad de documento.

Permanecen visibles pero deshabilitados o identificados como pendientes porque no tienen columna
válida: celular alternativo, teléfono familiar y observaciones clínicas. La atribución comercial
se transporta en memoria y se declara pendiente de persistencia.

## HCE y RENIEC

- Una ficha existente muestra literalmente `patients.historia_clinica`.
- Una ficha nueva calcula solo en navegador la previsualización `código-documento`.
- Los registros heredados con tipo `RUC` se pueden consultar, pero una ficha nueva de ese tipo no
  inventa un prefijo HCE: la propuesta de negocio no definió uno.
- `SIN DOCUMENTOS` muestra `99-…` y declara pendiente el identificador final.
- La previsualización nunca se envía ni se escribe en `patients`; al crear, HCE queda nula hasta
  que exista la regla definitiva.
- La consulta RENIEC aparece solo para una ficha nueva con DNI.
- El endpoint reutiliza `AgendaReniecLookupController` y `ReniecService`; no existe una segunda
  integración.
- La consulta es explícita, no guarda y la lógica heredada de borrador evita reemplazar campos
  modificados manualmente.

## Pendientes fuera de alcance

- una HCE nueva todavía no se persiste: el alta deja `patients.historia_clinica` nula;
- `SIN DOCUMENTOS` sigue pendiente de la regla definitiva del identificador;
- Guardar y agendar todavía no crea `Appointment`;
- cuando exista el alta real de la cita, `appointments.user_id` será quién agenda;
- el comercial dueño todavía no tiene columna ni persistencia;
- MariaDB local no tiene aplicadas `appointments.site_id`, `responsible_user_id` ni `updated_by_user_id`;
- el rol `COMERCIAL` debe reconciliarse con los roles reales antes del despliegue;
- el feature flag de Scheduling MVP sigue apagado en el ERP local real usado por la auditoría;
- la validación visual autenticada del modal en ese ERP sigue pendiente;
- modelo de atención/encounter, N.° Registro y fecha real de atención;
- médico, especialidad y sede de una atención;
- responsables, menores y desactivación/eliminación.
