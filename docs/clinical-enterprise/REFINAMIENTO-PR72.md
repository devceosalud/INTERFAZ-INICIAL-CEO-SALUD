# Refinamiento del mismo PR #72

Base verificada: main a4e2a9b0 y PR72 80bde251; worktree limpio y checkout original conservado. No se modifica el PR71.

## Referencias y decisiones previas

Se aplica la metodología refero-design, sin Refero MCP conectado. La referencia verificada B local sigue siendo dominante en Admisión. La propuesta C local, `site/prototype.css` (canvas neutral, ancla vertical, cinta de KPI y registros independientes), se aplica exclusivamente al seguimiento. El rail colapsable, el detalle algo más ancho, las señales repartidas y los bordes rectos son ajustes pedidos por el usuario, no referencias externas atribuidas a Refero.

El enlace Claude no fue accesible por web; el usuario proporcionó después una imagen completa y `claude.pdf`. El PDF contiene una página rasterizada y corta parte inferior; la imagen completa permite contrastar ecografías, observaciones y otros. SHA256 PDF: `29117f10f3e6089f92c997141014e1d3e14dea94b02fa33c07928f1284f09b80`. Se usa su contenido como referencia explícita del usuario, no como instrucción para imprimir, prescribir automáticamente o emitir documentos.

| Punto | Reproducción anterior | Cambio implementado | Comprobación |
|---|---|---|---|
| Admisión | Rail fijo176px, sin control de cierre; detalle340px; estado clínico y todos los badges a la derecha. | Toggle persistente; detalle proporcional más ancho; estado junto al paciente; progreso y alertas en grupos; radios2px. | Espacio ganado, teclado, persistencia, fondos completos y móvil. |
| Exoneración | No hay rama de exoneración en AgendaAppointmentPresentation: cae en SIN_PAGO_REAL y PROGRAMADO. La cita ficticia confirmada reproducida se ve crema `#fdf0d9`, no gris; OCUPADO/conciliación poseen gris en otros contextos. | EXONERADA amarilla cuando hay autorización real; conservar rojo, salida/presencia y naranja de mayor prioridad. No alterar pagos ni confirmación. | Ambas agendas, sin confundir excepción sin adelanto, y matriz de prioridades. |
| Orden de procedimientos | Catálogo genérico, sin lateralidad, proyecciones, dedos o cabecera clínica. | Opciones inequívocas del documento; detalles por solicitud; diagnóstico y observaciones cifrados; mismo versionado, eventos y confirmación. | Reapertura, múltiples selecciones, datos específicos, cliente antiguo, acceso y concurrencia. |
| Seguimiento C | Lista continua tradicional. Médico2 accede con200: el alcance previo era cada médico vinculado, no exclusivamente Quiroz. | Look C sin cambiar estados. Restricción personal de la cuenta2, además de ClinicalAccess y vínculo; segundo médico403 en página/API. | Capturas y operaciones legítimas de Quiroz; accesos directos denegados. |
| Buscar médico | Horarios tiene schedule-filter-doctor y schedule-doctor sin búsqueda; tema existente usa bootstrap-select. | Reutilizar bootstrap-select con búsqueda normalizada, actualización de opciones y valor nativo. Solo selects de profesionales. | Horarios, Admisión, Operativa, catálogos y vínculos; teclado, tildes y listas largas. |

## Datos y límites

Una migración aditiva agrega `request_details` JSON nullable por procedimiento y `clinical_order_ciphertext` MEDIUMTEXT nullable al review existente. No hay tablas nuevas para este refinamiento. Diagnóstico y observaciones no se incluyen en el feed operativo ni en snapshots de auditoría sin cifrar. La identificación del paciente, edad y fecha se derivan del registro autorizado; no se permite sustituir al paciente desde el popup ni fabricar firma/sello. Se conserva el autor real y la fecha del evento.

Se incorporan las opciones y denominaciones aportadas por el usuario sin precios, dosis inferidas, cobros, citas u órdenes a terceros. La selección es una solicitud médica explícita, no una realización. Los atributos de un procedimiento ya verificado no pueden cambiarse silenciosamente conservando su check de realizado. Los clientes anteriores que omiten los campos nuevos conservan sus datos.

La restricción personal del seguimiento es un estrechamiento solicitado en esta revisión: se conserva la creación longitudinal y los historiales de otros médicos, pero no se les habilita este módulo. No cambia sus permisos de atención ni selección de procedimientos. La configuración del despliegue debe mantener la cuenta verificada2; no se infiere identidad por texto.

## Resultado comprobado

Las cinco observaciones se verificaron en Chrome real y backend. [Resultados, capturas y fallos corregidos](qa/refinement/README.md). Diez regresiones HTTP nuevas, tres JavaScript nuevas y dos harnesses de navegador reutilizables acompañan la implementación. No hay contenido de Claude pendiente de pegar: se utilizó la imagen/PDF aportada. No se añadieron impresión, firma gráfica, órdenes externas, tarifas ni realización clínica automática.
