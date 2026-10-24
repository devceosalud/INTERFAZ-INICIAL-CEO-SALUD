# QA vigente — seis tareas del mismo Draft PR72

Base de esta revisión: `d6b381b6de3ac621b1eba2555a5eacf15c8b2b3d`. Main contrastado: `a4e2a9b0f9e517c55be10232321391b1dc970e73`. Exclusivamente cuentas/pacientes DEMO70 y bases aisladas. Las evidencias de las entregas anteriores se conservan; no se presentan sus cifras como pruebas de esta candidata.

## Resultados exactos

| Suite | Resultado | Evidencia |
|---|---|---|
| PHPUnit SQLite completa | 1.280 pruebas, 11.854 aserciones, cero fallos/errores/avisos/omitidas | [JUnit](sqlite.xml), [log](sqlite.log) |
| PHPUnit MariaDB11.8.9 completa | 1.314 pruebas, 12.158 aserciones, cero fallos/errores/avisos/omitidas | [JUnit](mariadb.xml), [log](mariadb.log) |
| JavaScript completa, después del arreglo de cierre con Enter | 364 aprobadas, cero fallos/omitidas | [log](javascript.log) |
| Chromium: recorrido histórico | 22 verificaciones | [log](corporate-regression.log) |
| Chromium: foco,3/3,privacidad/propiedad/roles y móvil | 10 verificaciones | [log](corporate-final-journey.log) |
| Chromium: creación sin adelanto, paciente nuevo/existente | 4 verificaciones | [log](corporate-without-deposit.log) |
| Chromium: PR72 original | 7 grupos completos | [log](clinical-enterprise-browser.log) |
| Seguimiento: filtros/contadores/ordenación | 6 verificaciones | [log](clinical-enterprise-followup-filters.log) |
| Refinamientos previos del PR72 | 6 grupos | [log](clinical-enterprise-refinement-browser.log) |
| Popup: título/pie, scroll, Escape y foco | 5 viewports | [log](clinical-enterprise-popup-responsive.log) |
| Nuevos casos A–H | 8 grupos | [resultado](browser-new.json), [log](clinical-enterprise-round-two-browser.log) |
| Selectores de médico y DOM dinámico | 8 grupos | [resultado](doctor-controls.json), [log](clinical-enterprise-doctor-controls.log) |
| Fondos/prioridades semánticas | 208 comprobaciones, cero discrepancias | [matriz](colors.json) |

[Resumen legible por máquina](results.json). Las suites no se recortaron ni se eliminaron pruebas para pasar. Las expectativas adaptadas son la navegación del nuevo popup por búsqueda/categorías, chips importantes visibles en lugar de +N y el título solicitado, conservando las aserciones funcionales.

## Casos nuevos realmente comprobados

- Escribir Radiografía de clavícula izquierda → +Agregar/Enter → seleccionada en borrador → confirmar → reabrir. Cirugía/MRI visibles, alias operación/RM, categoría y resumen. Cancelar o escribir no guarda.
- Otro paciente busca clavi y reutiliza el mismo UUID privado, conservando dos solicitudes independientes. La búsqueda HTTP recupera también una referencia anterior a los últimos200.
- Cirugía y MRI generan dos seguimientos automáticos únicos de Quiroz; repetir confirmación no duplica. Añadir seguimiento manual de la radiografía permite ver **tres procedimientos de una cita** en un grupo, cada uno con su estado propio.
- Enviar Curación manualmente dos veces reutiliza su seguimiento. El general guarda un registro sin solicitud; no genera cobros ni cambia atención. Reutilizar automático muestra procedencia mixta y conserva el estado.
- Estado de seguimiento En coordinación no realiza un examen. Historial/motivo cifrado, fechas, origen, paciente y acceso a la cita original.
- Otros médicos, administradores puros, Comercial y Admisión bloqueados en página/feed/API de seguimiento. Catálogo personal, UUID ajeno, paciente sustituido y versiones obsoletas cubiertos por HTTP/BD.
- Chips de cirugía/MRI activos y únicos en ambas agendas; atributos anteriores (herida, teleconsulta, postoperatorio) permanecen visibles. Se comprueban fondos turquesa de Admisión y Operativa por separado.
- 200 médicos ficticios persistidos en la demo: horarios/filtro/editor, Admisión, vinculación médica, catálogo y reportes. Operativa conserva su selector múltiple con búsqueda existente. Opciones visibles/hit-test dentro del mismo menú, clic, flechas/Enter/Escape y un único change. DOM con wire:model, reordenación y actualización de selected sin duplicar el control. El ensayo dinámico utiliza el widget real; no se presenta como una prueba de todos los componentes Livewire del ERP.

## Concurrencia y migraciones

MariaDB incluye siete carreras específicas de Clinical Enterprise: conserva las cuatro anteriores y agrega normalización simultánea de catálogo privado entre dos citas, reintento de envío general con el mismo UUID y envíos manuales competidores. Se exige una referencia compartida privada, solicitudes independientes y respuestas idempotentes o409 según corresponda, con una sola auditoría por operación.

La prueba de actualización crea el esquema anterior poblado con solicitudes/seguimientos/eventos ficticios, aplica050000, compara datos/cifrados, reejecuta y comprueba FKs. Down destructivo rechazado. También hay regresión de medianoche UTC frente al calendario de Lima.

## Integridad económica

[Hashes y conteos por cohorte](browser-regressions.json): pagos byte a byte idénticos en todos los recorridos. Popup, seguimiento y controles no cambian citas, comprobantes ni saldos. Excepciones **expresamente ensayadas**:

- Corrección económica administrativa de F:120→100, pagado60 y saldo40; actualiza su TICKET existente, sin pago ni comprobante adicional. No se exige un hash idéntico a un comprobante que se corrigió por el mecanismo autorizado.
- Creación sin adelanto agrega exactamente las dos citas de prueba solicitadas; pagos y comprobantes permanecen iguales. Los otros recorridos mantienen las23 citas.

No se comparan hashes de bases reseedadas como si fueran la misma sesión. El reset guarda un respaldo privado antes de restaurar. El estado de entrega está en [demo-final-state.json](demo-final-state.json).

## Capturas comparables

| Área | Antes d6b381 | Ahora |
|---|---|---|
| Popup | [Lista anterior](before/popup.png) | [Catálogo y prioridades](after/catalog-priorities.png) |
| Nombre nuevo | [Búsqueda anterior](before/new-search.png) | [+Agregar](after/inline-add.png), [selección/resumen](after/draft-summary.png) |
| Reutilización | No había catálogo personal | [Otra cita busca clavi](after/personal-reuse.png) |
| Seguimiento | [Registros anteriores](before/followups.png) | [Tres procedimientos agrupados](after/followups-grouped.png), [filtro manual](after/followups-manual-filter.png) |
| Envío manual/general | No existía | [Curación](after/manual-21.png), [general](after/manual-22.png) |
| Etiquetas | Sin chips de solicitud | [Admisión](after/admission-procedure-tags.png), [Operativa](after/operational-procedure-tags.png), [características anteriores](after/original-clinical-tags.png) |
| Combobox | [Menú anterior](before/combo-jul.png) | [jul con resultados visibles](after/combo-jul-options.png) |

Laptop al100%, viewport real1366×636,1440×900,1536×864,1920×1080 y móvil390×844: diálogo dentro del viewport, título/confirmación accesibles, scroll sin pérdida de borrador, sin desborde horizontal. Ejemplos: [Admisión1366](after/admission-1366.png), [móvil](after/admission-390.png), [popup1366](after/popup-1366.png), [móvil](after/popup-390.png), [seguimiento](after/followups-1366.png), [móvil](after/followups-390.png). Las demás capturas completas y fallos intermedios se conservan localmente.

## Fallos encontrados y corregidos

| Hallazgo | Corrección / prueba |
|---|---|
| Búsqueda personal ubicada inicialmente en un grupo de permisos de agenda general | Grupo de acceso de Admisión + comprobación clínica de procedimientos; médico puro autorizado en sus datos y otras cuentas403 |
| Mismo envío con fecha opcional omitida/null daba conflicto | Payload canónico antes de hash; reintentos y carreras reales |
| Etiqueta privada confirmada cambiada devolvía403 en lugar del409 contractual | Preservar identidad confirmada y conflicto explícito; prueba anterior conservada |
| Antigüedad usaba medianoche UTC | Calendario Lima; regresión de ambas medianoches |
| Menú podía quedar abierto al pulsar Enter en un select dinámico | Cierre después del key handler, sin otro change; navegador con200 opciones y reapertura |
| Prueba antigua buscaba un checkbox de otra categoría sin navegar | Adaptar búsqueda del test al popup agrupado; conservar recorrido0/3→3/3 |

No se solucionaron fallos eliminando restricciones financieras, permisos ni pruebas. [Archivos de esta revisión](archivos.txt), [decisiones](../../CATALOGO-Y-SEGUIMIENTO-PR72.md), [preparación productiva](../../PREPARACION-PRODUCTIVA-PR72.md).
