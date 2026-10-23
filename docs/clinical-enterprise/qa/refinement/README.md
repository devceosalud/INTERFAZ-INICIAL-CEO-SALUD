# Validación vigente del refinamiento PR72

Código funcional comprobado: `14fb5a6f268ba023803c3592db91a94b09891edd`; los commits posteriores documentan y conservan esta evidencia. PHP8.3.35, SQLite y MariaDB11.8.9 en instancias loopback descartables. Chrome real con Playwright, exclusivamente DEMO70. Los informes anteriores se conservan como históricos.

| Verificación | Resultado | Evidencia |
|---|---|---|
| PHPUnit SQLite completo | 1.268 pruebas / 11.710 aserciones; 0 fallos, errores, avisos u omitidas | [JUnit](suites/refine-final-sqlite.xml), [log](suites/refine-final-sqlite.log) |
| PHPUnit MariaDB completo | 1.299 / 11.990; 0 fallos, errores, avisos u omitidas; incluye carreras reales | [JUnit](suites/refine-final-maria.xml), [log](suites/refine-final-maria.log) |
| JavaScript completo | 361 aprobadas; sin fallos u omitidas | [Log](suites/refine-final-js.log) |
| Cinco observaciones | 6 grupos de navegador aprobados con cinco cuentas | [Resultado](browser-refinement.json) |
| Popup responsive/accesible | 1366×636,1440×900,1536×864,1920×1080,390×844; título/pie visibles, notas accesibles, Escape y foco devuelto | [Resultado](popup-responsive.json) |
| Funciones originales PR72 | 7 grupos de navegador: popup, alerta, documentos, retorno y actualización real de seguimiento | [Resultado](browser-integral.json) |
| Regresiones anteriores | 22 recorridos completos +10 ajustes previos +4 casos de creación sin adelanto | [22](regresiones-22.json), [10](regresiones-10.json), [4](sin-adelanto-4.json) |
| Seguimiento | 6 comprobaciones de filtros, KPI, búsqueda, orden y restablecer sin recarga | [Resultado](followup-filters.json) |
| Colores | 208 verificaciones aprobadas; solo cambia intencionalmente EXONERADA | [Matriz](../colors/browser-color-matrix.json) |
| Operativa frente a main | 38 intervalos normales idénticos en texto, geometría y fondos, en1366/1440/1536/1920/390 | [Comparación](operativa-main.json) |

La excepción explícita de Operativa es el slot exonerado17:00, ahora amarillo, junto con la búsqueda del control de médico solicitada. Sus vistas y CSS propios no cambian. Las mediciones excluyen ese único slot al comparar igualdad con main y lo verifican aparte en ambas agendas. No se afirma igualdad completa cuando existe este cambio autorizado.

## Antes/después y revisión visual

| Pantalla | Antes80bde251 | Después |
|---|---|---|
| Admisión | [Rail fijo y detalle](before/admission.png) | [Expandido](after/admission-expanded.png), [colapsado](after/admission-collapsed.png), [laptop1366×636](after/admission-1366.png), [móvil](after/admission-390.png) |
| Popup | [Catálogo anterior](before/popup.png) | [Orden y características](after/popup-1366.png), [observaciones](after/popup-notes-1366.png), [móvil](after/popup-390.png), [notas móvil](after/popup-notes-390.png) |
| Exoneración | [Sin rama específica](before/exempt.png) | [Admisión amarilla](after/exempt-admission.png), [Operativa amarilla](after/exempt-operational.png) |
| Seguimiento | [Lista anterior](before/julio-followups.png) | [Premium Operations](after/followups-premium.png), [móvil](after/followups-390.png) |
| Médicos | [Horarios anterior](before/schedule.png) | [Búsqueda en el editor](after/schedule-search.png) |
| Operativa | [Main1366](operativa/operativa-main-1366.png) | [Candidata1366](operativa/operativa-candidate-1366.png) |

Viewports reales y zoom100%. Se inspeccionaron capturas, no solo resultados automáticos. Las imágenes contienen identidades y motivos manifiestamente ficticios. Algunas reflejan el estado después de ejecutar acciones; la semilla final vuelve a veinte citas y cuatro seguimientos.

## Integridad y permisos

[Resultados de los tres recorridos antiguos](results.json) y [cuatro grupos nuevos](extra-results.json) comparan hashes antes/después dentro de cada misma semilla. Los pagos permanecen byte a byte idénticos: dos registros que suman S/120. El descuento autorizado de F actualiza su TICKET interno existente mediante el servicio económico original; las pruebas nuevas de popup/seguimiento no cambian ni pagos, ni comprobantes, ni cantidad de citas. La creación sin adelanto añade intencionalmente dos citas, sin pagos ni comprobantes. No se comparan hashes entre reinicios con timestamps diferentes.

Se comprueban usuario2 vinculado, médico ajeno, roles operativos, administrador puro y mixto, documentos por URL/API, retorno manual denegado a administrador/médico, privacidad y propiedad de edición. La cabecera clínica nueva se cifra y solo se devuelve al médico vinculado; Admisión puede ver las características necesarias de los procedimientos sin recibir diagnóstico u observaciones clínicas.

## Fallos y correcciones durante la verificación

- Las primeras suites detectaron la expectativa cromática antigua SIN_PAGO_REAL para una exoneración autorizada: se cambió únicamente esa expectativa por EXONERADA/#fff2a6, conforme al requisito. Las otras doce categorías permanecen iguales. Las suites completas se repitieron y pasaron.
- Bootstrap-select y estilos de agenda podían mostrar el select nativo y su sustituto simultáneamente; se ocultó visualmente solo el nativo, conservando valor y envío. El botón recibe nombre accesible breve y tabulación correcta; la prueba real incluye200 opciones ficticias, búsqueda sin tildes, teclado y un único evento change.
- Al ampliar la orden, el scroll podía desplazar el título fuera de vista. Cabecera y confirmación quedan fijas y el cuerpo tiene scroll propio. Escape desde un buscador con texto ahora cierra el diálogo de forma consistente, respetando la confirmación de cambios sin guardar y devolviendo foco.
- Se corrigieron errores del harness separados del producto: selector incorrecto para un botón con role=combobox, control ficticio colocado bajo la navegación fija, espera móvil sobre una fila oculta al mostrar detalle y texto anterior «de3» frente a «/3». Los fallos originales y sus capturas siguen conservados localmente. No se retiraron pruebas para lograr verde.

## Repetición

Los nuevos scripts `tests/Support/clinical-enterprise-refinement-browser.cjs` y `clinical-enterprise-popup-responsive.cjs` exigen CEO_ENTERPRISE_QA=OWNED_LOOPBACK_DEMO y el perfil privado de esta demo. No contienen contraseñas. El primero modifica el casoQ; restaurar antes de repetir. Los scripts anteriores conservan sus guardas. La guía local /demo-guia contiene cuentas y pasos; el reinicio exige confirmación y copia SQL, solo sobre33345.

No hay validación productiva ni de SUNAT real. La migración aditiva040000 y la identidad de Quiroz deben revisarse antes de una publicación futura autorizada. Mantener APP_KEY y el esquema aditivo en rollback para conservar datos/auditoría.
