# Evidencias de los ajustes finales del PR #70

Solo datos ficticios. Base comprobada: PR70 `d255da5b`, main `a57b2d04` (#68). Ninguna credencial ni respaldo de BD se incluye aquí.

## Resultados

| Ejecución | Resultado | Evidencia |
|---|---|---|
| SQLite completa | 1.241 pruebas / 11.485 aserciones | [sqlite.log](sqlite.log) |
| MariaDB11.8.9 completa | 1.268 / 11.726 | [mariadb.log](mariadb.log) |
| Regresiones afectadas al terminar UI | 264 / 2.469 | [targeted.log](targeted.log) |
| JavaScript | 356 / 356 | [js.log](js.log) |
| Colores, cuatro roles, escritorio/móvil, fila y celdas | 208 / 208 | [matriz](colors/browser-color-matrix.json) |
| Recorrido integrado de los cinco requisitos | 22 controles | [acceptance.json](acceptance.json) |
| Observaciones finales, interfaz y backend real | 10 controles | [recorridos.json](recorridos.json) |
| Altas sin adelanto, paciente existente/nuevo | Ambas entradas, doble clic y motivo obligatorio | [observacion4-ui.json](observacion4-ui.json) |
| Integridad después de altas y corrección económica | Pagos/tickets originales, saldos, autoría y eventos sin duplicar | [integridad.json](integridad.json) |
| Densidad a zoom100% | Ocho vistas, sin desborde, nombres legibles y alertas a la derecha | [layout-final-densidad.json](layout-final-densidad.json) |

La matriz de densidad mide filas **completas de citas**, sin contar intervalos libres ni referencias históricas. En1920×928 se obtuvieron8–9 en Operativa y10 en Admisión; en1536×744,5 en Operativa y5–6 en Admisión, según la fase de DEMO70-C. Con menor altura disponible y estados con más texto, el número baja. Las capturas conservan los nombres ficticios extensos: no se ocultan datos ni se altera el zoom para inflar la cifra.

El panel derecho usa360–420px en escritorio para equilibrar formulario y lista central. Se comprueban los nombres completos y la igualdad de tipografía entre ambas agendas. [Panel y vista previa económica](panel-derecho.json):120 de precio,60 abonado y nuevo10 → saldo previsto50, sin guardar pagos.

## Capturas que permiten revisar el resultado

- [Antes: espacio de Agenda operativa,1536px](antes-densidad-agenda-1536.png) y [después](layout-final-densidad-agenda-1536.png).
- [Antes: espacio de Admisión,1536px](antes-densidad-admission-1536.png) y [después](layout-final-densidad-admission-1536.png).
- [Operativa1920px](layout-final-densidad-agenda-1920.png), [Admisión1920px](layout-final-densidad-admission-1920.png).
- [Foco real de la cita vigente](focus-desktop.png), [foco dentro del viewport móvil](focus-mobile-viewport.png).
- [Naranja3/3, con confirmación operativa aún disponible](listo-sin-confirmar-desktop.png).
- [Detalle móvil cargado](detalle-mobile-cargado-viewport.png), [lista móvil](corporativo-mobile.png).
- [Ficha ajena en solo lectura](propiedad-bloqueo-comercial.png).
- [Panel derecho con paciente completo](panel-paciente-1920.png), [resumen económico y vista previa](panel-pagos-1536.png).
- [Revisión económica](economia-revision.png), [confirmación sin adelanto de alta nueva](obs4-confirmacion-true.png).

## Reproducción y fallos conservados

`FinalAdjustments70Test` cubre propiedad del paciente y de la cita, responsable sin transferencia de autoría, excepción administrativa existente, foco en otra página y privacidad, estados3/3/manual y bloqueo clínico. `tests/Support/final-adjustments70-browser.cjs` recorre la demo aislada; `agenda-density70-browser.cjs` mide geometría y alineación real. Ambos leen credenciales privadas locales, ausentes del repositorio.

`agenda-registration-layout70-browser.cjs` verifica el formulario de paciente y los pagos en1920/1536/1280/390px. Introduce10 únicamente como vista previa, exige saldo50 y vuelve a0 sin guardar. La [comprobación posterior](integridad-final-sin-pagos-extra.json) conserva13 citas, dos pagos originales por120 y dos comprobantes; C tiene una sola indicación/confirmación/segunda atención y E una sola cita.

Los logs de `fallos-intermedios/` se conservan para mostrar los problemas encontrados. Incluyen expectativas antiguas de listado200 frente a redirección302, edición ajena antes permitida, preparación incompleta de fixtures y el recorte de la última fila al hacer foco. Se corrigieron las guardas, la preparación y el layout, sin eliminar pruebas ni relajar auditoría o autorización. Las suites finales de la tabla pasan completas.

Se mantienen también `baseline.json`, la reproducción HTTP de escritura heredada y las capturas anteriores. Los SQL, sesiones, contraseñas y archivos de conexión se conservan exclusivamente en el entorno local privado. La guía de pruebas y el reinicio seguro están en `http://127.0.0.1:8000/demo-guia`.
