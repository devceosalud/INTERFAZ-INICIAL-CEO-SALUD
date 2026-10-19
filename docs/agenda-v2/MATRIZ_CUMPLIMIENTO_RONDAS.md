# Matriz de cumplimiento — rondas1 a4

Estado contrastado con código, interfaz y pruebas de la cuarta ronda. Los cambios de negocio posteriores sustituyen instrucciones anteriores; no se declara una función completa solo por existir un endpoint. Los resultados concretos están en QA_Y_EVIDENCIAS y sus JSON/XML/capturas.

| Requerimiento | Ronda | Estado | Operación real y evidencia |
|---|---|---|---|
| Pago positivo confirma automáticamente |1 | Implementado | Registro rápido y reserva; Chrome de regresión y nueva teleconsulta, suites financieras/idempotencia/carreras. No confirmación por OCR o importe0. |
| Registro único de citas |1–2 | Sustituido por regla posterior más estricta | Se bloquea creación heredada para todos, incluido ADMIN; menús de seguimiento sin creador. Consulta, edición autorizada y Nicol se conservan. Negativos POST/roles en PHP y Chrome. |
| Polling con filtros, selección y borradores |1–4 | Implementado | Ambas agendas, pausa en edición/dialog/pestaña oculta, secuencia/abort y control de respuestas viejas. Chrome conserva borradores y dos sesiones; pruebas JS de esas condiciones. |
| Siete procedimientos y confirmación explícita |1 | Implementado | Checkboxes de ADMIN en detalle; guardar confirma, ADMISION registra realizados. Versión y ledger. |
| Procedimientos personalizados |4 | Implementado | Otro procedimiento revela nombre; c:UUID separado,1–500 caracteres, transcriptor/verificador, retiro histórico, conteo y reevaluación. PHP y Chrome ADMIN/ADMISION. |
| Reevaluación automática completa y parcial autorizada |1–2 | Implementado | Parcial sin autorización no naranja; todos realizados automático; manual exige permiso/confirmación/motivo. Desmarcar recalcula. Matriz completa, actualizaciones entre sesiones y carreras. |
| Diagnóstico de1/2 naranja |2 | Implementado y conservado | Autorización manual previa demostrada en ledger; protección adicional contra READY automático incoherente. No se fuerza una hipótesis de conteo erróneo. |
| Retorno manual directo ATENDIDO sin procedimientos |Hotfix47/49 | Integrado en solución unificada | COMERCIAL activo autorizado marca/desmarca sin UPDATE; no recibe pagos/care. Eventos v1 se leen directamente, tres orígenes independientes y cola única. |
| Historial de hotfixes y retirada de un origen |Integración | Implementado | Marca histórica + procedimiento completo/parcial conviven; retirar uno no elimina otro. Sin importaciones duplicadas ni procedimientos/pagos inventados. |
| Motivo obligatorio, principal y único |2 | Implementado | Registro rápido y modal de paciente comparten un textarea antes de guardar/pagar; front/back trim y max2000. Chrome valida foco, preservación y reserva. No se altera historia vacía. |
| Motivo visible en Admisión |2 | Implementado | Bajo identificación, con No registrado para historia vacía; feed autenticado de resumen. |
| Observación significativa desde1 carácter |2 | Implementado | Agregar observación admite punto; rechaza espacios; histórico/auditoría preservados. PHP/Chrome y límite técnico de columna. |
| ADMIN ve citas y reservas de otros responsables |3 | Implementado | Scope explícito de lectura operativa, sin pago como filtro; disponibilidad pública sin pacientes. Permisos de escritura/vouchers no ampliados. PHP y Chrome de reservas ajenas. |
| Postoperatorio y herida persistidos |3–4 | Implementado | Chips en fila/detalle/agenda, sin inferencias por nombre. Casos sintéticos explícitos y varias etiquetas simultáneas. |
| Turquesa ATENDIDO, naranja reevaluación y alertas |Hotfix48/49–4 | Implementado | Paleta y texto; alertas/retirada/salida prioritarias. Franja explicada, programado no equivale a pagado. Capturas y pruebas de precedencia. |
| Selección de toda la fila y accesibilidad |3 | Implementado | Sin columna Ver; clic/Enter/Espacio, foco, sin interceptar controles ni repetir detalle seleccionado. Chrome/JS. |
| Salida física sin motivo y rápida |3 | Implementado | Confirmación simple, actor/hora/auditoría, único POST con detalle. Medición reproducible de tercera ronda y regresión actual; no modifica ATENDIDO o dinero. |
| Retroceso con motivo |1–4 | Implementado | Última transición reversible, estado/evento esperado, motivo obligatorio, cierre final bloqueado y auditoría preservada. PHP/Chrome/carrera. |
| Popup Gestionar cita |4 | Implementado | Doble clic, Alt+Enter y botón móvil. Motivo, datos, economía, documentos, reschedule y panel existente de cierre. Chrome real y cuatro resoluciones. |
| Editar datos operativos con conflicto |4 | Implementado | notes existente con UUID/revisión, permisos y visibilidad; rechazo409 conserva texto. Dos sesiones y carreras MariaDB. |
| Reprogramar mediante popup |4 | Implementado | Disponibilidad real y endpoint existente, confirmación y fecha/hora/revisión esperadas. Misma modalidad; precios y contratos protegidos. |
| Cambiar profesional/servicio de cita pagada como campo ordinario |4 | Pendiente de decisión de negocio para una operación nueva | No se añade. Regla vigente: nueva cita y revisión autorizada de crédito, conservando original. UI solo lectura; no se improvisa conciliación. |
| Editar importes históricos directamente |Todas | Sustituido por operaciones económicas seguras | Popup muestra originales, descuento, autorización, pago/saldo. Sin inputs de escritura económica; endpoints financieros existentes conservan capacidades, transacciones e idempotencia. |
| Costo0 autorizado y estado económico |4 | Implementado | Proyección común de exoneración, gratuidad clínica acreditada, descuento total histórico acreditado, validación, saldo o pago real. Sin modificar enum/pagos. |
| Descuento nuevo100% |4 | Pendiente por decisión expresa: no habilitar | Usuario elige mantener exoneración. Se conserva rechazo del descuento ordinario total; solo se reconoce historia con respaldo suficiente. |
| Teleconsulta en registro/detalle/reprogramación |4 | Implementado | attendance_mode independiente, defaultpresencial; distintivo violeta y combinación con adicional/postoperatorio. Creación/pago por UI, cambio autorizado auditado y preservación de precios. |
| Espera/atención/salida remotas |4 | Pendiente de decisión de negocio | Usuario decide dejar flujo remoto pendiente. No hay registro automático o manual equivalente de presencia física para teleconsulta. |
| Videollamada/enlace externo |4 | Pendiente de requerimiento/autorización | No se genera enlace ficticio ni se integra proveedor externo. |
| Documentos aportados privados |3–4 | Implementado | COMERCIAL carga; ADMIN consulta/adjunta/reemplaza; ADMISION solo autorizado. MIME/tamaño, PDF.js, URL privada, auditoría, historial/paciente y versiones. PHP/Chrome/carrera. |
| Documentos simplificados y separados |4 | Implementado | Lista primero, + Adjuntar archivo, formulario progresivo; versiones, comprobantes e historial en secundarios. Evidencia clínica solo si autorizada. |
| Favoritos, frecuentes y último profesional |4 | Implementado | Persistencia individual en BD, límite5, activos, filtros, otra sesión y separación por usuario. UI con estrella y desplegable compacto. |
| Precios45, Caja, OCR, adelantos, horarios |Regresión | Implementado y conservado | Suites completas SQLite/MariaDB/JS y recorridos previos de Chrome; no se cambia el resolutor para habilitar gratuidades o teleconsulta. |
| Reevaluación clínica días0,1,15,16 |Regresión | Implementado y conservado | Se mantienen indicación acreditada, identidad/origen/servicio, evidencia privada, uso único, revisión económica y pruebas de fronteras. Distinta de cola operativa. |
| Bootstrap Select MFR |Corrección adicional | Implementado y conservado | refresh tras compatibilidad preserva selección válida; Chrome Keylinn ficticia/Consulta MFR y Traumatología bloqueada sin DevTools, además de PHP/JS. |
| Auditoría, privacidad y documentos privados |Todas | Implementado y conservado | Lecturas/escrituras diferenciadas, CSRF, idempotencia, versiones, negativos por cita/documento, Clarity excluido. No se borran eventos o archivos históricos. |

La aceptación visual y funcional final corresponde al usuario. Ningún estado de esta matriz autoriza merge, despliegue, migración productiva o uso de datos reales.
