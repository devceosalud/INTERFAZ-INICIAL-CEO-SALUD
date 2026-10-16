# Catálogo y precios — diseño y diagnóstico

Base de desarrollo: `c86ffa6e9ed04bb80272db400a63b48604a9a7ef` (PR #44). Worktree independiente, rama `feat/catalogo-servicios-precios-agenda`. Se conservan los cinco cambios pendientes del checkout anterior; no se incorpora su diff a esta rama.

## Incidencia 500 y evidencia

El informe productivo aportado por el usuario registra `AppointmentConfigurationException` en `StandardAdditionalRateResolver.php:37`, propagada por `AppointmentPriceResolver.php:22` el 16/10/2026. La exportación no contiene la única `TARIFA ESTANDAR` activa, fija, cero y vigente que exigía ese código. Esto demuestra la causa de los errores registrados; no demuestra que una modificación local ya esté publicada.

Se separan dos cambios:

1. **Manejo del error:** excepciones previsibles de configuración en los endpoints de cotización devuelven JSON 422 controlado. Una excepción con detalles privados no los entrega al navegador.
2. **Modelo definitivo:** sin recargo seleccionado, el precio normal no depende de una fila mutable de tarifas. `appointments.additional_rate_id` se conserva con su FK y valores anteriores y admite NULL en nuevas citas sin ajuste. Una tarifa elegida explícitamente sigue validándose y sumándose cuando corresponde. No se usan los importes de las tarifas exportadas como precios base.

## Arquitectura resultante

- `services`: identidad y especialidad principal existentes, tipo, política de precio, precio institucional opcional, vigencia, notas y versión. Una reevaluación comercial puede enlazarse a su consulta canónica. No hay backfill, cambio de IDs ni importación automática.
- `doctor_services`: autorización individual activa/inactiva, primera consulta y reconsulta particulares. Los valores heredados de plazo se conservan; el motor clínico continúa con el límite aprobado de 15 días.
- `service_specialties`: especialidades adicionales expresamente autorizadas para un servicio compartido. Incluye estado, administrador, motivo y marcas de tiempo. Mantiene un solo servicio y precio institucional; no asigna profesionales automáticamente.
- Excepción individual de otra especialidad: acto explícito de ADMINISTRADOR con motivo y especialidades vigentes del profesional/servicio capturadas. Cambiar esas especialidades invalida la excepción. No se conceden automáticamente privilegios ni se presume autorización de una asignación heredada incompatible.
- `catalog_price_events`: auditoría de antes/después y actor para precios, asignaciones, recargos y especialidades compartidas. Los formularios envían `catalog_version`; una edición obsoleta falla sin sobrescribir la anterior.
- `ServiceSpecialtyEligibility` y `ActiveDoctorServiceResolver`: filtran los selectores y validan backend de Agenda, entrada heredada y Ventas. La tarifa institucional no habilita por sí sola a un profesional.
- `ServicePriceResolver`: fuente de precio base compartida. Política institucional usa el precio común; consulta profesional usa su precio particular y puede tener un valor institucional por defecto para la consulta normal. Falta de precio, importe cero de catálogo o vigencia inválida bloquean de forma explícita. No hay fallback de reconsulta a precio de consulta normal.
- `AppointmentPriceResolver`: conserva indicaciones, gratuidad acreditada del mismo día, días 1–15, consulta normal desde día 16, referidos, descuentos y controles existentes. Las escrituras vuelven a leer precio y autorizaciones con bloqueos transaccionales; una cotización es informativa y no autoriza un cobro obsoleto.
- Ventas valida el precio actual de líneas de servicio antes de escribir y vuelve a comprobarlo bajo transacción. Las citas y liquidaciones de tickets usan su instantánea persistida, sin repricing retroactivo.

La precedencia económica es regla clínica/especial autorizada, precio particular permitido, precio institucional por defecto, o error de configuración. Las reglas de referido acreditado de Quiroz se conservan. Los importes ordinarios declarados 150/120, 100/80 y 150/120 deben ser datos editables del maestro, no constantes del motor ni seeders.

## Administración real desde ERP

En Maestros → Profesional/Servicio se selecciona un profesional, se ven todas sus asignaciones, se editan ambos precios y su estado y se guarda con confirmación. El enlace “Catálogo y precios institucionales” permite editar el precio común, vigencia, clasificación y especialidades compartidas. Los campos particulares se deshabilitan para servicios institucionales y el servidor rechaza excepciones monetarias no autorizadas.

Las asignaciones incompatibles se señalan, se evita seleccionarlas accidentalmente y solo se habilitan mediante una autorización administrativa específica con motivo. No debe aprobarse una excepción como sustituto de reconciliar el error de Keylinn.

Los botones de editar tienen endpoints y persistencia reales; los formularios muestran errores, conservan entradas y usan CSRF. Los assets administrativos se versionan por contenido. Los modales permiten desplazamiento interior y mantienen visible el guardado en móvil.

En Registro rápido, servicio y modalidad están junto a la cotización autoritativa, su procedencia, importe del adelanto y saldo. Una solicitud nueva invalida el precio previo y una respuesta antigua no puede reemplazarla. El operador puede abrir el panel para preparar un adelanto cuando ya existen paciente, intervalo y cotización válida; abrirlo o leer OCR no registra dinero. “Guardar reserva” conserva su confirmación explícita de no cobrar; pago y confirmación se realizan por los flujos autorizados.

## Reevaluaciones e históricos

Los enlaces comerciales aprobados son 8→1 y 10→3, sujetos a comprobar referencias antes de configurarlos. La cita guarda `service_id` canónico y `commercial_service_id` separado. La modalidad requiere indicación acreditada del paciente y mismo servicio, atención origen válida, evidencia, uso único e idempotencia. Elegir “Reevaluación” por sí solo no concede el precio reducido.

El maestro impide enlazar un servicio comercial con referencias clínicas, ventas o asignaciones activas que requieran transición. También impide reclasificar una consulta canónica con indicaciones o modalidades vinculadas. No se trasladan ni recalculan citas históricas. La reprogramación indicada conserva el enlace y calcula por la nueva fecha; si exige un ajuste con dinero/ticket, mantiene el bloqueo seguro existente.

Confirmación: cualquier pago real positivo correctamente atribuido o exoneración autorizada, conforme al PR #43. No se reintroduce el 50 %. Precio institucional cero, un recargo neutral, un OCR o un permiso de modalidad no constituyen una exoneración.

## Datos que requieren revisión/aprobación

Consultar `STAFF_CORRESPONDENCIA_2026-10-16.md` y JSON. El Excel aporta 20 profesionales, ocho especialidades y 39 combinaciones, sin precios. No confirma todos los IDs productivos de profesionales. MFR, TF por 10 sesiones, servicios sin precio, profesionales no asignados, clasificaciones, especialidades compartidas, la asignación incorrecta doctor 9/servicio 1 y los enlaces comerciales siguen siendo decisiones concretas de datos. Ninguna migración las aplica automáticamente.
