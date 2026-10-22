# Matriz funcional y entrega parcial — 21/10/2026

**Fase A NO aprobada integralmente. No avanzar a Fase B ni desplegar esta revisión como flujo médico completo.** La vinculación usuario–médico se entrega como propuesta, sin implementar asociación, migración o acceso médico, conforme a la respuesta del responsable.

Base exacta: `2e81877557fc07d7bb6bf3f31e06f2f100b958e8`. Rama aislada: `codex/auditoria-funcional-mvp-20261021`. Los SHA finales y el diff se entregan al cerrar el commit local. No se asume despliegue de ninguna candidata en Hostinger.

| Punto | Estado global | Ya funcionaba | Corregido / ampliado | Pendiente / bloqueo |
|---|---|---|---|---|
| 1. Reprogramadas | CORREGIDO | Evento existente, mismo ID, referencia dentro del intervalo, navegación, Admisión, múltiples orígenes y disponibilidad/KPI sin duplicar | Rojo suave, fecha/hora originales y destino, texto Ver cita reprogramada; se conservan los enlaces existentes a la cita vigente | Ninguno identificado en el alcance local probado |
| 2. Atención/reevaluación | BLOQUEADO para recorrido médico completo | Llegada auditada, procedimientos/realización, READY automático no vacío, parcial manual con motivo, salida física separada | Se cierra el inicio/finalización por permisos genéricos en rutas actuales/heredadas. Lectura de indicación pendiente por eventos, chip secundario, historial, filtros/counters excluyentes e inmediata actualización al completar | Falta aprobar/implementar identidad y escritor médico. No hay médico autorizado que pueda iniciar, finalizar o indicar desde UI. Fixtures NO sustituyen esa prueba |
| 3. Colores/etiquetas | CORREGIDO en presentación | Fuente backend AgendaAppointmentPresentation/AgendaLegend/AdmissionStatus y estado financiero separado | Fondo de toda fila/celdas/tarjeta móvil, sin mapa local de colores. Selección/hover mantienen fondo. Pendiente no cambia clave/color/franja; listo conserva presentación anterior | La activación real por médico depende del punto2 |
| 4. Economía pospago solo ADMINISTRADOR | PENDIENTE | Se conserva exactamente el editor económico de 2e81877 | Sin cambios por instrucción de orden | Fase B retenida; la restricción nueva todavía NO está aplicada |
| 5. Confirmación sin adelanto por ADMISION | PENDIENTE | Reservas/cobros actuales conservados | Sin cambios por instrucción de orden | Fase B retenida; la excepción nueva todavía NO está implementada |

## Estado de los 25 criterios del punto2

| Criterio | Resultado y límite |
|---|---|
| 1 | CORREGIDO/PROBADO: llegada por ADMISION, actor/hora real, idempotencia y dinero intacto. ADMINISTRADOR conserva la facultad administrativa previa con capacidades; otros roles genéricos no reciben llegada. |
| 2 | CORREGIDO/PROBADO: inicio médico por Admisión devuelve403; también se bloquea en la ruta heredada. |
| 3 | BLOQUEADO: inicio por médico autenticado requiere asociación aprobada. |
| 4 | BLOQUEADO: botones médicos no se habilitan sin identidad verificable. |
| 5 | BLOQUEADO: no existe escritor/botón médico habilitado de indicación pendiente. |
| 6 | PROBADO solo contrato de lectura: etiqueta PENDIENTE DE REEVALUACIÓN de evento sintético. |
| 7 | PROBADO: clave, fondo y franja iguales antes/después de la indicación; solo chip secundario. |
| 8 | PROBADO: filtro rápido pendiente sobre la proyección, en backend y navegador. |
| 9 | PROBADO: pendiente no aparece en listos; cero procedimientos no activa READY. |
| 10 | YA FUNCIONABA/PROBADO: Admisión ve procedimientos seleccionados; selección sigue restringida al administrador. |
| 11 | YA FUNCIONABA/PROBADO: Admisión verifica realización; se conserva verificador e historial. |
| 12 | YA FUNCIONABA/PROBADO: completar todos los seleccionados activa READY por servicio existente. |
| 13 | CORREGIDO/PROBADO: READY desactiva la proyección pendiente, sin borrar indicación. |
| 14 | CORREGIDO/PROBADO: filtros/counters se actualizan tras completar sin recargar la página. |
| 15 | YA FUNCIONABA/PROBADO: envío parcial manual exige al menos uno realizado y motivo cuando corresponde; retirada mantiene historia. |
| 16 | PROBADO: Admisión no tiene un endpoint para alterar la indicación original; campos médicos ajenos al marcador manual se rechazan. |
| 17 | BLOQUEADO: finalización por médico autenticado pendiente de identidad; finalización genérica se rechaza. |
| 18 | PROBADO solo lectura: indicación proyectada y procedimientos no crean salida ni timestamps; falta probar el escritor médico futuro. |
| 19 | PROBADO: misma cita/paciente y dinero, sin cobros/citas artificiales. |
| 20 | PROBADO para llegada/reintentos rechazados; BLOQUEADO para doble clic de escritor médico aún inexistente. |
| 21 | PROBADO autor de contrato sintético y verificador real ficticio de Admisión; BLOQUEADO para acreditar un médico autenticado. |
| 22 | CORREGIDO/PROBADO:403 sin permisos/rol administrativo y para cualquier transición médica genérica sin vínculo. |
| 23 | CORREGIDO/PROBADO: contador pendiente y listo independientes, mutuamente excluyentes por cita. |
| 24 | PROBADO: fecha, médico, sede, paciente y visibilidad; combinaciones contradictorias devuelven422 sin escritura. |
| 25 | PROBADO escritorio/móvil para lectura/filtros/realización; BLOQUEADO el recorrido completo de médico. |

## Cambios exactos y necesidad

| Punto | Archivos / funciones |
|---|---|
| 1 | `public/js/scheduling/agenda-reschedule-history.js::label,render,marker`: texto de origen/destino y botón uniforme. `public/css/scheduling/agenda-reschedule-history.css`: rojo. `admission-board.js::renderReschedules`: misma etiqueta de navegación. No cambian RescheduleAppointmentService, RescheduleHistory, agenda.js o agenda-day-grid.js. |
| 2, lectura | `AppointmentReevaluationService::loadIndications,indication,pending,summary,detail,history`: último evento por ID y lectura independiente de READY; historial unificado sin sobrescribir. `ManualReevaluationService::load`: añade una carga por lote, sin modificar su autorización/escritor. |
| 2, seguridad | `AdmissionCareService::authorizeState,transition,reversalOffer`:403 de acciones médicas sin vínculo y acciones físicas administrativas; no deshacer inicio médico con reversión administrativa. `LegacyAppointmentMutation::update`: mismo bloqueo para transiciones médicas y cambios desde EN_ATENCION, sin prohibir editar campos permitidos manteniendo estado. |
| 2, filtros | `AdmissionBoardService::board,filterRows,careActions,detail`: contador/filtro y flags/restricción. `AdmissionBoardController::feed`: flag validado y rechazo de filtros contradictorios. `resources/views/scheduling/admission/index.blade.php`: filtro y séptimo contador compacto. |
| 2 y 3 | `AgendaAppointmentPresentation::of`: chip pendiente solamente; `AgendaLegend::entries`: texto/color del chip secundario, no una nueva clasificación principal. `admission-board.js::renderRows,renderProcedures,renderDetail,updateFilterState`: etiquetas/filtros/autoría. `procedureChange,toggleDirect` refrescan listado/detalle para no dejar KPI obsoleto mientras el foco pausa el polling. |
| 3 | `admission-board.js::renderRows`: elimina tones y usa presentación backend. `public/css/scheduling/admission-board.css`: aplica fondo a fila/celdas/móvil y conserva fondo al seleccionar/hover; ajuste de 7 indicadores sin nueva paleta. |

Pruebas nuevas: `MedicalIdentityAndReevaluationProjectionTest` (12 casos incluyendo proveedor de roles). Se ajustan cuatro expectativas antiguas de `AdmissionBoardTest`: permitían atención a roles genéricos; ahora verifican la regla administrativa y el bloqueo médico. Conservan assertions de dinero, auditoría, idempotencia, reservas y OCR. Dos pruebas JS y el script visual existente solo actualizan el texto obligatorio de historial, no sus invariantes. Nuevos helpers/scripts QA generan únicamente cohortes locales ficticias y distinguen eventos de contrato de acciones médicas autenticadas.

## Riesgos y reversión

Esta revisión cierra deliberadamente el inicio/finalización desde Admisión o permisos generales, incluso ADMINISTRADOR sin vínculo médico. Es una protección, pero significa que todavía NO puede desplegarse como recorrido clínico completo. La propuesta debe validarse y completarse antes de aprobar Fase A. La lógica económica de 2e81877 permanece igual; no confundir este cierre de seguridad con haber implementado el punto4.

No hay nuevas tablas, columnas o migraciones en esta revisión. No cambian tarifas, pagos, confirmación, disponibilidad, semáforo, usuarios/roles, catálogo o documentos fiscales. Se verifican regresiones; no se promete compatibilidad con un Hostinger no inspeccionado. La incorporación futura de identidad requerirá migración aditiva y pruebas indicadas en PROPUESTA_VINCULACION_MEDICA.md.

Rollback local/de código hacia 2e81877 conserva datos y eventos, pero REABRE el acceso médico genérico hallado: no recomendarlo como solución de seguridad. No borrar eventos, procedimientos, pagos o vínculos por volver al código previo. Antes de cualquier futura publicación/despliegue: revisión de diff, respaldo, verificación real del servidor/esquema y autorización explícita. No se efectuaron esas acciones.

Revisión sin ZIP: se entrega commit y diff locales. GitHub no mostrará la rama hasta que el responsable autorice push exclusivamente de esta rama y un Draft PR. No se publica automáticamente por terminar las pruebas.
