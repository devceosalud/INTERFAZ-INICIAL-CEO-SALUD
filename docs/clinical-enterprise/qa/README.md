# Validación local de Clinical Enterprise

## Resultado

Ejecuciones finales sobre PHP 8.3.35, SQLite y MariaDB 11.8.9. Exclusivamente bases descartables y pacientes ficticios DEMO70; Chrome real automatizado mediante Playwright. Ninguna conexión a Hostinger, producción o servicios de envío/facturación externa.

| Verificación | Resultado | Evidencia |
|---|---|---|
| PHPUnit SQLite completo | **1.258 pruebas, 11.630 aserciones**, 0 fallos, errores u omitidas | [Log](suites/sqlite-final-complete.log), [JUnit](suites/sqlite-final-complete.xml) |
| PHPUnit MariaDB completo | **1.289 pruebas, 11.910 aserciones**, 0 fallos, errores u omitidas | [Log](suites/maria-final-complete.log), [JUnit](suites/maria-final-complete.xml) |
| JavaScript completo | **358 aprobadas**, 0 fallos u omitidas | [Log](suites/javascript.log) |
| Funciones nuevas en navegador | **7 grupos aprobados**, cinco roles, popup, alertas, seguimiento y acceso directo | [Resultado](browser-integral.json) |
| Recorridos anteriores completos | **22 comprobaciones aprobadas**, siete cuentas | [Resultado](regresiones-22.json) |
| Ajustes funcionales anteriores | **10 comprobaciones aprobadas** | [Resultado](regresiones-10.json) |
| Creación y confirmación sin adelanto | **4 comprobaciones aprobadas**, paciente existente y nuevo | [Resultado](confirmacion-creacion-4.json) |
| Filtros del seguimiento | **6 comprobaciones aprobadas**, KPI, búsqueda, restablecer y orden sin recarga | [Resultado](followup-filters.json) |
| Colores de Admisión | **208 verificaciones aprobadas**, cuatro roles, dos tamaños, normal y selección | [Matriz](colors/browser-color-matrix.json) |
| Operativa contra main | **39 intervalos idénticos por tamaño**, en cinco tamaños: texto, geometría y rellenos de celdas | [Comparación](operativa-comparacion-main.json) |
| Responsive final | 1366×636, 1366×768, 1440×900, 1536×864, 1920×1080 y 390×844; médico y Comercial | [Mediciones](responsive.json) |

Los tamaños son viewports reales con zoom 100%, no reducciones de zoom. Se revisaron lista, detalle, modal, alertas y seguimiento. No hubo desbordamiento horizontal en los recorridos comprobados. Se verificó el teclado del modal (Tab, Escape, foco nativo), foco de reprogramación y badge por foco/clic. Los scripts recogen excepciones `pageerror`; eso no equivale a afirmar que no existan mensajes de consola o solicitudes bloqueadas. La demo bloquea deliberadamente recursos e integraciones externas.

## Capturas comparables y funciones nuevas

| Pantalla | Referencia / captura real |
|---|---|
| Admisión main | [Antes, 1366](capturas/admission-main-1366.png) |
| Admisión B | [Lista, 1366](capturas/admission-b-lista-1366.png), [detalle, 1366](capturas/admission-b-detalle-1366.png), [laptop de altura636](capturas/admission-b-laptop-1366x636.png), [móvil](capturas/admission-b-movil.png) |
| Selector | [Tres solicitudes: específica, resonancia y cirugía](capturas/popup-seleccion-especifica.png), [móvil](capturas/popup-movil.png) |
| Paciente problema | [Alerta y pestaña](capturas/alerta-paciente.png), [móvil](capturas/alerta-movil.png) |
| Seguimiento | [Escritorio](capturas/seguimiento-1366.png), [móvil](capturas/seguimiento-movil.png) |
| Operativa sin cambios | [Main](capturas/operativa-main-1366.png), [candidata](capturas/operativa-candidata-1366.png) |

Las capturas incluyen únicamente nombres y motivos manifiestamente ficticios. Algunas se tomaron después de ejecutar recorridos; la demo se restaura al finalizar para repetirlos.

## Cobertura y datos

Las nuevas pruebas de backend ejercitan identidad del procedimiento específico, familias aprobadas, idempotencia, conflictos de versión, firma médica, médico ajeno, cuenta ADMINISTRADOR+MEDICO, alertas cifradas, resolución auditada, acceso documental, paciente reasignado y protección de migraciones inversas con datos. Cuatro pruebas MariaDB usan solicitudes paralelas reales: doble envío de selección, versiones competidoras, alertas simultáneas y retiro de solicitud contra actualización de seguimiento.

Los recorridos conservados prueban llegada de Comercial y Admisión, bloqueo sin llegada, médico correcto/incorrecto, indicación, confirmación, 0/3→3/3, naranja sin confirmación ficticia, retorno manual con pendientes, segunda atención real, rechazo con motivo, finalización y salida separadas. Incluyen privacidad, propiedad de edición, Julio ficticio ID2, revisión económica y reprogramación con scroll/foco y duración preservada.

Durante los 22 recorridos, la tabla de pagos conservó exactamente sus dos registros y el hash completo: **S/120** en total. [Antes](funds-before.json) y [después](funds-after-22.json). El TICKET interno de F sí recalculó sus totales al aplicar el descuento autorizado mediante el servicio económico existente; es una actualización esperada, no otro comprobante ni otro pago. Posteriormente se crearon **dos citas nuevas intencionadas** al probar confirmación sin adelanto, sin aumentar pagos ni comprobantes: [snapshot posterior](funds-after-regressions.json). No se comparan hashes entre reinicios, porque la semilla genera timestamps nuevos.

La restauración final deja **19 citas ficticias, 2 pagos que suman S/120, 2 TICKET internos**, cuatro seguimientos de ejemplo (dos pendientes, uno realizado, uno cancelado) y la alerta activa de CE-X. Las copias SQL previas se conservan localmente, nunca en Git.

## Defectos encontrados y corregidos

- La longitud inicial de un índice compuesto excedía el límite de MariaDB: nombres cortos explícitos.
- El popup podía desbordar su contenido y superponer el pie: área de scroll acotada y foco nativo conservado.
- El tema intentaba mejorar filtros del seguimiento sin su dependencia; después duplicaba selectores: dependencia existente y neutralización limitada a esa pantalla, sin cambiar el shell compartido.
- Un estilo nuevo de fila rebajaba el foco de reprogramación: restaurado borde visible de3px sin sustituir el fondo semántico.
- Volver a lista en móvil durante una actualización asíncrona podía dejar ambos paneles ocultos: se conserva expresamente la intención de navegación durante la recarga de datos.
- Las instantáneas cifradas de auditoría con Unicode máximo podían superar64KiB: columnas de eventos MEDIUMTEXT y prueba de ida/vuelta sin truncamiento.
- Los KPI de seguimiento no respetaban el filtro de procedimiento y el desempate de orden no invertía al elegir más recientes: corregidos con pruebas HTTP y seis comprobaciones de interfaz.

Los fallos de los propios scripts de navegador (pulsar una fila que correctamente está oculta al mostrar el detalle móvil, esperar sobre el escenario equivocado o no marcar la nueva declaración de hechos) se distinguieron de defectos del producto y se corrigieron en el harness. Las primeras ejecuciones fallidas permanecen en la evidencia local. No se eliminaron pruebas para obtener resultados verdes. Se adaptaron expectativas antiguas únicamente para los nuevos permisos solicitados y la unificación turquesa intencional.

## Repetición segura

`tests/Support/clinical-enterprise-browser.cjs`, `clinical-enterprise-followup-filters.cjs` y `clinical-enterprise-visual.cjs` requieren `CEO_ENTERPRISE_QA=OWNED_LOOPBACK_DEMO`, Chrome y el perfil privado local `storage/app/demo-enterprise/private/config.json`. No contienen contraseñas. El primer script modifica escenarios de prueba; restaurar mediante el launcher local antes de repetir. Los filtros esperan cuatro seguimientos de la semilla y el visual es de lectura.

PHPUnit usa las configuraciones SQLite y MariaDB existentes; la suite MariaDB está restringida por el guard de QA a su instancia loopback33342, distinta de la demo33345. No copiar credenciales locales ni ejecutar estos comandos contra otra base. La guía `/demo-guia` contiene las cuentas ficticias, pasos y launchers únicamente de esta computadora.

El entorno local sirve para aceptación funcional; no se afirma validación de una cuenta productiva sin vínculo médico, integración SUNAT real ni despliegue productivo. La aprobación del catálogo específico y la revisión de incidentes siguen siendo responsabilidades clínicas/administrativas.
