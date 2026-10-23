# Evidencias: refinamiento corporativo de las agendas

Base final: main `a4e2a9b0f9e517c55be10232321391b1dc970e73`, posterior al merge del PR #70. Todos los usuarios, pacientes, importes y documentos de las capturas son ficticios. Las contraseñas, configuración local y respaldos SQL no se incluyen.

## Comparativas al 100%, sin/con selección

| Agenda / tamaño | Antes | Después |
|---|---|---|
| Operativa, laptop 1366×668, cita seleccionada |[Antes](before/agenda-1366-selected.png)|[Después](after/agenda-1366-selected.png)|
| Operativa 1440×800, cita seleccionada |[Antes](before/agenda-1440-selected.png)|[Después](after/agenda-1440-selected.png)|
| Operativa 1536×744, sin selección |[Antes](before/agenda-1536-list.png)|[Después](after/agenda-1536-list.png)|
| Operativa 1920×928, sin selección |[Antes](before/agenda-1920-list.png)|[Después](after/agenda-1920-list.png)|
| Admisión 1440×800, lista |[Antes](before/admission-1440-list.png)|[Después](after/admission-1440-list.png)|
| Admisión 1536×744, detalle |[Antes](before/admission-1536-selected.png)|[Después](after/admission-1536-selected.png)|
| Admisión 1920×928, detalle |[Antes](before/admission-1920-selected.png)|[Después](after/admission-1920-selected.png)|
| Móvil 390×844, Admisión |[Antes](before/admission-390-selected.png)|[Después](after/admission-390-selected.png)|

Los directorios `before/` y `after/` contienen las veinte vistas de cada fase, con los mismos tamaños y datos. [Mediciones anteriores](before/result.json) · [mediciones finales](after/result.json). No se usa zoom reducido. Se mide la cantidad de citas completas dentro del viewport y del panel, excluyendo huecos y referencias históricas, con 1px de tolerancia de redondeo aplicado en ambas fases.

Las capturas originales reales se tomaron antes de implementar. El modo reproducible `before` desactiva únicamente el CSS nuevo y reproduce el párrafo original de main; API, datos, permisos y JavaScript siguen siendo reales. La matriz exige fondos y KPI idénticos y ausencia de pérdida de densidad en los escritorios comparados. Los primeros intentos de medición se conservan localmente y se explican en el documento de refinamiento.

## Lectura, teclado y móvil

- [Ayuda contextual existente](accessibility/ayuda-contextual.png): conserva el aviso retirado de la cabecera y responde al teclado.
- [Operativa: tabla móvil](accessibility/operativa-mobile-tabla.png) y [paciente/formulario](accessibility/operativa-mobile-paciente.png).
- [Admisión: lista móvil](accessibility/admision-mobile-tabla.png) y [detalle](accessibility/admision-mobile-detalle.png).
- [Cinco comprobaciones de teclado/móvil](accessibility/result.json), incluida selección real de filas en cuatro colores sin perder su fondo.
- [Contraste de textos](contrast.json): se comprueba 4,5:1 en los textos de las filas. El ajuste afecta al texto auxiliar; no a los fondos ni al catálogo funcional.
- [Matriz de 208 verificaciones de color](colors/browser-color-matrix.json), cuatro perfiles, escritorio/móvil y selección.

## Regresiones de operación

| Ejecución | Resultado / registro |
|---|---|
| PHPUnit afectadas, SQLite |264 pruebas / 2.469 aserciones · [log](tests/sqlite.log)|
| PHPUnit afectadas y concurrencia, MariaDB 11.8.9 |292 / 2.716 · [log](tests/mariadb.log)|
| JavaScript existente |356 · [log](tests/javascript.log)|
| Compatibilidad de reportes tras actualizar main |Tres pruebas / 66 aserciones · [log](tests/upstream-reports.log)|
| Recorrido real integrado, siete perfiles |22 comprobaciones · [resultado](functional/acceptance.json)|
| Reevaluación 3/3 sin confirmar, foco, filtros y autoría |10 comprobaciones reales · [resultado](functional/recorridos.json)|
| Alta sin adelanto de paciente existente y nuevo |Cuatro comprobaciones, con motivo obligatorio y doble clic · [resultado](functional/observacion4-ui.json)|
| Conservación económica |Dos pagos originales por 120, dos comprobantes, F 100/60/40 y altas sin adelanto sin pagos/vouchers nuevos · [auditoría](integrity.json)|

Las pruebas se ejecutaron sobre bases descartables. Después se restaura la misma instantánea ficticia del antes para entregar la demo lista, conservando respaldos de los recorridos. El estado final tiene los 13 escenarios iniciales, F 120/60/60 y G pendiente para volver a probarlos.

## Alcance verificado

[Prueba de archivos funcionales intactos](scope.json): sin cambios en backend, rutas, migraciones, configuración, JavaScript operativo ni dependencias. [Documento y archivos de producto](../REFINAMIENTO-CORPORATIVO.md).

Los logs de preparación en `preparation/` conservan la negativa inicial de MariaDB por identidad de datadir. Se corrigió el entorno QA físico sin cambiar la guarda ni el producto. No se eliminaron pruebas para conseguir un resultado verde. Todo queda en una rama y Draft PR nuevos; no se hizo merge ni despliegue.
