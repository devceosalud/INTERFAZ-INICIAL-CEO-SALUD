# Catálogo personal y seguimiento — segunda revisión del PR72

Base comprobada antes de modificar: PR72 Draft d6b381b6de3ac621b1eba2555a5eacf15c8b2b3d; main a4e2a9b0f9e517c55be10232321391b1dc970e73. Checkout original, worktrees y evidencias previas conservados. No se accede a Hostinger.

## Investigación y referencia bloqueada

La metodología refero-design se aplica con referencias locales verificadas: B para Admisión, C Premium Operations para seguimiento, la orden de la imagen/PDF del usuario como contenido y el selector bootstrap-select ya instalado como patrón de interacción. No existe Refero MCP conectado. Se conservan tipografía, color de acciones, bordes sobrios y fondos semánticos completos. La navegación lateral de categorías y la adición desde búsqueda son propuestas propias derivadas del flujo solicitado; no se atribuyen a una referencia externa.

| Tarea | Situación inicial | Decisión y reutilización | Riesgo / aceptación |
|---|---|---|---|
| Popup y reutilización | Lista larga; claves surgery/mri existentes; personalizadas con UUID solo por cita. | Categorías compactas, prioridades visibles, alias explícitos, Enter para añadir; solicitudes independientes y referencia personal cifrada por médico. | Duplicados normalizados, categoría inequívoca, privacidad, borrador sin escritura y reapertura. |
| Etiquetas | Las solicitudes activas no producen chips cirugía/MRI; el contador sí existe. | Derivar chips exclusivamente de claves/familias estructuradas seleccionadas, con lectura agrupada. | Ambas agendas, médico autorizado, sin modificar fondo, cobros o estado. |
| Seguimiento manual/general | Seguimiento solo automático; FK de solicitud obligatoria. | Ampliar el servicio existente; FK nullable para general con clave única; motivo cifrado, versiones y ledger existentes. | Quiroz verificado, transacción, doble envío, sin solicitud ficticia. |
| Seguimiento útil | Una fila independiente por procedimiento; faltan origen manual y antigüedad. | Agrupar visualmente por cita con cada procedimiento visible; añadir filtros de origen y antigüedad sin fusionar estados. | Cada seguimiento conserva ID, auditoría y transición propia. |
| Etiquetas anteriores | Algunos chips quedan bajo +N o se excluyen del payload médico. | Conservar etiquetas clínicas estructuradas, repartirlas junto a paciente y alertas; información adicional accesible. | No inferir de texto libre ni revelar datos económicos al médico puro. |
| Combobox | Búsqueda del plugin existente; falta evidencia del menú filtrado y actualización dinámica completa. | Corregir overflow/capas si se reproduce; mantener búsqueda y resultados en el mismo dropdown y valor nativo. | 200 médicos ficticios, flechas/Enter/Escape, Livewire, un único change. |

## Persistencia implementada

Una migración adicional aditiva: catálogo personal cifrado (sin paciente, cita ni precio), vínculo nullable desde cada solicitud y ampliación del seguimiento existente para origen, motivo manual y seguimiento general. La identidad normalizada compara únicamente equivalencia exacta (tildes, mayúsculas, espacios); no combina nombres clínicamente parecidos. Una solicitud personal nunca se promueve automáticamente al catálogo oficial ni a un servicio tarifado. El catálogo compartido sigue bajo configuración revisada.

La cuenta2 y su doctor_id verificado siguen siendo obligatorios para el módulo y envío manual. Las sugerencias son privadas del médico creador y su vínculo; no amplían el acceso a pacientes de otro médico. Los campos de la orden clínica y su cifrado permanecen vigentes.

## Decisiones finales

- Ocho categorías y una vista personal compacta; Cirugía y Resonancia permanecen a la vista. Alias completos explícitos y búsqueda sin tildes; Enter agrega un nombre nuevo al borrador, con categoría/nombre revisables antes de confirmar. Cancelar no escribe. La cabecera deriva paciente/médico; diagnóstico, observaciones y atributos específicos conservan cifrado y validación.
- Cada solicitud conserva su identidad por cita. La referencia privada conserva primer nombre, familia y UUID. Equivalencias exactas reutilizan; familia contradictoria devuelve409. No se fusionan izquierda/derecha ni se modifican solicitudes completadas. Los últimos200 aparecen al abrir; buscar recorre también referencias anteriores.
- No se importan masivamente etiquetas libres antiguas: solo se incorporan al confirmar por el médico vinculado. Se rechazan documentos/correos y nombres identificables del paciente de la cita; esto y la advertencia no sustituyen revisión humana. No se promueve automáticamente al catálogo compartido ni al tarifario. La promoción requiere configuración revisada por responsables clínicos/administrativos, sin otro módulo.
- Los chips cirugía/MRI provienen de solicitudes activas con familia validada, incluidas personalizadas. Se cargan relaciones agrupadas. Retirar una solicitud retira el chip y registra la retirada de fuente del seguimiento sin borrar historia ni marcar realizado/cancelado.
- Envío manual con ledger existente, motivo cifrado, paciente esperado y versiones de fuente/seguimiento. Repetir UUID no duplica; modificación concurrente devuelve409. Reutilizar automático cambia origen a MIXED conservando estado. General usa FK de solicitud nula y clave GENERAL única por médico/cita: no fabrica exámenes.
- Seguimientos agrupados visualmente por cita, con ID/estado/auditoría propios por procedimiento. Nuevos filtros de origen, fechas y antigüedad. Días calculados con calendario de Lima, evitando un día ficticio al cruzar medianoche UTC.
- Las características importantes anteriores dejan de ocultarse bajo +N. En Admisión, clínicas junto al paciente y operativas en alertas. Operativa conserva su composición; solo cambian las etiquetas expresamente pedidas y ayuda accesible. Ningún chip altera el fondo.
- Bootstrap-select reutilizado, búsqueda y opciones juntas. El fallo reproducido: altura medida durante carga y overflow dejaban opciones fuera del menú. Se corrige exclusivamente el control de médicos. El selector múltiple propio de Operativa conserva su búsqueda/lista.

## Acceso efectivo

| Acción | ADMISION / COMERCIAL | Médico vinculado | Administrador puro | Julio ID2 MEDICO+ADMINISTRADOR |
|---|---|---|---|---|
| Solicitud técnica / checks | Controles operativos anteriores | Solicita sus citas con capacidad PROCEDURES; sin ampliar checks | Autorización técnica anterior, sin cabecera clínica | Solicitud clínica propia |
| Catálogo personal / diagnóstico / notas de orden | No | Propios y capacidad clínica | No | Propios |
| Envío / módulo / feed / detalle de seguimiento | No | No, salvo cuenta personal verificada | No | Sí, solo sus pacientes/citas |
| Registrar alerta | Sí | No | Solo resolución administrativa autorizada | No edita como operativo |
| Documentos en Admisión | No | Sus citas autorizadas | Autorización administrativa anterior | Según autorización anterior |
| Retorno manual | Sí | No | No | No |

Se conservan prioridades de roles mixtos, `users.doctor_id` y las tres migraciones históricas. No se crean roles ni categorías de permisos. Se reutilizan `appointment.clinical.procedures.request` y `appointment.clinical.view`; no se asignan permisos productivos.

## Archivos principales

| Tarea | Archivos / componentes |
|---|---|
| Popup y reutilización | `procedure-picker.js`, `clinical-order-fields.js`, `admission-clinical.css`, `DoctorProcedureSuggestionService`, `OperationalProcedureService/Controller`, `ProcedureCatalog`, búsqueda privada y migración050000 |
| Chips y características anteriores | `AgendaAppointmentPresentation`, `AgendaLegend`, `Appointment`/`AppointmentRequestedProcedure`, ambos presenters/feed, `admission-board.js`, `agenda-appointment-indicators.js/css` |
| Envío manual/general | `MedicalProcedureFollowupService/Controller`, `medical-followup-referral.js`, Blade de Admisión y ruta protegida |
| Seguimiento útil | `medical-followups.js/css`, Blade de seguimiento, feed existente |
| Médicos | `doctor-search.js/css`; sin sustituir formularios ni guardar vínculos desde pruebas UI |

Resultados/capturas: [QA de esta revisión](qa/round2/README.md). [Preparación productiva](PREPARACION-PRODUCTIVA-PR72.md) y [DDL local exacto](ESQUEMA-PR72.md).
