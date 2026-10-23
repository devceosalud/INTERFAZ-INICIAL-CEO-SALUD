# Refinamiento visual corporativo de las agendas

## Punto de partida y alcance

Rama nueva `codex/refinamiento-visual-agendas-20261023`, creada desde main remoto `0ce2b9b54b67db4a681e0330697bce6dab38b881`, merge del PR #70. El contenido inicial coincidía con su última candidata `ec508058`. Durante la validación avanzó main a `a4e2a9b0f9e517c55be10232321391b1dc970e73`; la rama visual local, todavía sin publicar, se actualizó sobre esa base. Se conservaron los tres commits de main que habilitan reportes existentes para FACTURACION. Esos cambios no pertenecen al refinamiento y no se reimplementaron. La comparación final y las suites se repitieron sobre la nueva base. El despliegue productivo es el confirmado por el usuario; no se abrió conexión a Hostinger.

Se conservan los cinco archivos con cambios ajenos en el checkout original y todos los worktrees anteriores. La demo utiliza la misma BD MariaDB ficticia verificada, con respaldo previo y runtime local aislado. Las dependencias se reutilizan sin actualizarlas.

## Mapa de presentación, antes de implementar

| Superficie | Clases / archivos actuales | Refinamiento previsto |
|---|---|---|
| Identidad compartida | `agenda-layout.css`, `admission-board.css`, `agenda-indicators.css` | Una capa CSS común y acotada a las dos agendas; Poppins existente, jerarquía y espaciado consistentes |
| Horarios | `.agenda-day-entry`, `.agenda-day-cell`, `.agenda-row-head` | Banda continua, divisores verticales mínimos, selección/foco conservando todos los fondos semánticos |
| Pacientes | `.admission-table`, `.admission-patient-name`, `.admission-signals-cell` | Hora/paciente prioritarios, profesional y datos secundarios con menor peso; alertas separadas y alineadas |
| Indicadores / filtros | `.admission-kpis`, `.admission-kpi`, `.admission-filter-primary` | Barra uniforme y controles alineados, manteniendo IDs, acciones y agrupaciones |
| Paneles laterales | `.agenda-operations`, `.agenda-registration`, `.admission-detail` | Proporciones moderadas; campos legibles y bloques diferenciados sin restar ancho innecesario al listado |
| Aviso diario | `agenda/partials/toolbar.blade.php` | Trasladar el texto de Seguimiento al componente de ayuda contextual existente, sin retirar sus reglas |
| Carga de estilos | Dos vistas `scheduling/agenda/index` y `scheduling/admission/index` | Incorporar el mismo CSS, versionado por `AgendaAssets` existente |

No se modificarán backend, consultas, permisos, autenticación, rutas, API, estados, reglas cromáticas, migraciones ni JavaScript de negocio. Las capturas antes/después se tomarán con los mismos datos y tamaños reales, a zoom 100%, sin datos de pacientes reales.

## Validación

Se documentarán capturas comparables y mediciones en 1366,1440,1536,1920 px y móvil; estados sin/con selección, continuidad cromática, badges, KPI, teclado y paneles. Las regresiones existentes cubrirán llegada, atención, reevaluación/confirmación/procedimientos, foco, economía, sin adelanto, filtros, autoría, privacidad y salida. La entrega permanecerá en Draft, sin merge ni despliegue.

## Implementación visual

Solo cuatro archivos de producto:

- `public/css/scheduling/agenda-corporate.css`: capa compartida de presentación; ninguna regla cambia el fondo semántico de una cita. Retira divisores verticales de Operativa, reduce peso tipográfico, unifica badges, controla el foco/selección, uniforma la barra KPI y ajusta proporciones y bloques laterales.
- `resources/views/scheduling/agenda/index.blade.php`: carga el CSS mediante `AgendaAssets` e incorpora el botón de ayuda existente en la leyenda.
- `resources/views/scheduling/admission/index.blade.php`: carga el mismo CSS versionado.
- `resources/views/scheduling/agenda/partials/toolbar.blade.php`: retira el párrafo cotidiano, cuyo texto completo sigue disponible en la ayuda con la misma condición administrativa de antes.

No se modificaron `app/`, `routes/`, `database/`, `config/`, `public/js/` ni dependencias. No se agregan módulos, pantallas, frameworks, roles o permisos. El botón de ayuda utiliza el manejador ya existente, sin añadir JavaScript operativo.

### Tipografía, bandas y alertas

Poppins existente en ambas agendas. Hora y paciente con peso 600, profesional con peso 500–600 y datos secundarios con peso 400. Se conservan nombres, servicio, motivo, historia, importes y todas las etiquetas. Los textos del catálogo no se reescriben ni se reinterpretan.

Los slots mantienen exactamente los fondos y prioridades de main. Se comparan fila y celdas antes/después y en hover/selección. La selección usa contorno y un detalle interior; no sustituye el fondo por blanco ni por otro estado. Las etiquetas conservan el color recibido del catálogo, con geometría común y separación horizontal/vertical. Las áreas se ajustan de forma natural al ancho, evitando forzar una nueva línea para cada texto de procedimientos.

### Espacio y paneles

Operativa mantiene profesionales/calendario, horario central y registro rápido. El ancho de profesionales se adapta entre 244 y 280 px, con nombres que admiten varias líneas. Su lista usa altura natural y scroll si hay muchos profesionales, evitando recortar el segundo nombre en laptop. Registro rápido ocupa 350–400 px en escritorio y conserva valores completos y pagos 2×2.

En Admisión se conserva la lista a ancho completo sin selección. Al seleccionar, el detalle se limita a 360–440 px en escritorio y deja espacio al listado. Se mantienen pestañas, acciones, procedimientos, resumen económico y retorno a la lista. KPI, filtros y reprogramaciones conservan todos sus IDs y comportamientos.

## Comparación reproducible

Las primeras capturas se hicieron con main real antes de cargar el CSS nuevo. Para repetir la matriz con recursos completamente cargados y los mismos datos, el modo `before` desactiva únicamente la nueva hoja y reproduce el párrafo original de main. No simula API, estados, permisos, importes ni JavaScript. Las capturas iniciales se conservan localmente como `before-initial`.

`agenda-corporate-browser.cjs` comprueba veinte vistas por fase: ambas agendas, con/sin selección, en 1366×668,1440×800,1536×744,1920×928 y 390×844. Espera recursos y fuentes; admite únicamente un píxel de redondeo geométrico, igual antes y después. Cuenta citas completas dentro del panel y del viewport, sin contar intervalos libres ni referencias históricas. Exige no reducir ese número en los escritorios comparados, además de igualdad exacta de fondos/KPI y ausencia de desborde de página.

Resultados observados: la tabla operativa comienza 19 px más arriba. En 1366 px con selección pasa de 2 a 3 citas completas; en 1920 px Admisión con detalle pasa de 6 a 7. Las demás combinaciones de escritorio mantienen su cantidad. El número depende de huecos, nombres, estado y referencias presentes; no se ocultan intervalos ni datos para inflar la medición.

En móvil se conserva el flujo existente y el desplazamiento de página. Se comprueban también tabla, formulario y detalle después de desplazarse hasta ellos, sin simular el tamaño mediante zoom.

## Pruebas y evidencias

| Comprobación | Resultado |
|---|---|
| PHPUnit afectadas, SQLite |264 pruebas / 2.469 aserciones, sin fallos|
| PHPUnit afectadas y concurrencia, MariaDB 11.8.9 |292 / 2.716, sin fallos|
| JavaScript existente |356, sin fallos|
| Matriz cromática existente |208 verificaciones, cuatro perfiles, escritorio/móvil, sin fallos|
| Comparación de presentación |20 vistas antes +20 después; fondos/KPI iguales y densidad conservada|
| Teclado y móvil real |Ayuda, Más filtros, selección de cuatro colores, tabla/formulario/detalle: cinco comprobaciones, sin fallos|
| Contraste de textos de filas |145 mediciones, mínimo 4,87:1; no constituye una certificación global de accesibilidad|
| Recorridos integrados reales |22 de operación, 10 de ajustes finales y cuatro de altas sin adelanto, sin fallos|

Se comprueba además el contraste de los textos de las filas. Se oscurece únicamente el texto auxiliar, incluido el motivo cuyo CSS anterior imponía otro color, para superar 4,5:1 también sobre el naranja existente. Los colores de fondo y los del catálogo funcional permanecen idénticos.

Los recorridos integrados reales de navegador y la restauración final de DEMO70 se documentan en el [índice de evidencias](qa/README.md), junto con capturas comparables y logs. Las credenciales y respaldos SQL son exclusivamente locales y privados.

### Incidencias de preparación conservadas

La primera suite MariaDB se negó a usar el datadir del worktree anterior. Se apagó limpiamente esa instancia QA, se copió solo su base descartable cerrada a un directorio físico del worktree nuevo y se inició en 33342; la guarda original permanece intacta. No se modificó su código ni se relajó la identidad de conexión.

Las primeras capturas móviles esperaban una fila visible cuando el detalle seleccionado oculta la lista por diseño; se corrigió la espera de QA. Las primeras iteraciones visuales aumentaron demasiado la altura de referencias/badges: se ajustó la composición CSS y se mantuvo la exigencia comparativa de densidad. El lanzador local también requirió salida UTF-8 para imprimir los resultados de Node en Windows; las 356 pruebas ya devolvían éxito. Una apertura inicial de navegador agotó el tiempo de espera; la repetición completa pasó sin cambiar controles ni expectativas. Los registros iniciales se conservan; no se eliminaron pruebas de negocio ni se modificaron sus expectativas.

## Entrega segura

Nuevo Draft PR contra main, sin merge ni despliegue. La demo en 127.0.0.1:8000 utiliza únicamente usuarios y pacientes DEMO70, MariaDB local 33343 e integraciones bloqueadas/simuladas. Los recorridos se ejecutan sobre datos descartables con respaldo; al terminar se restauran los escenarios iniciales. El checkout original y todos los worktrees anteriores permanecen conservados.
