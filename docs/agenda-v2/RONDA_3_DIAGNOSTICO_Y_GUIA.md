# Tercera ronda — diagnóstico, cambios y revisión local

Trabajo sobre `feat/agenda-operativa-flujo-reevaluaciones-v2`, PR Draft #46. Inicio limpio en `840522eca91638d16a58ed1b40907c8ce6f749ea`; checkpoint local `codex/agenda-v2-pre-round3-20261019`. `origin/main` verificado en `dcb9b2b7c9dafe75d79be95992fdbf0b955a9ace`. No se modificó main, Hostinger, bases productivas ni otros worktrees. Los cinco archivos previos del checkout original conservan sus hashes: [estado inicial](evidence/round3/initial-state.json).

## Diagnósticos y resolución

| Observación | Evidencia / causa | Solución |
|---|---|---|
| Cabecera apretada | La regla de Admisión perdía ante `erp-shell.css`, que impone padding con `!important`; el título empezaba justo en y=52, debajo de la barra. | Offset exclusivo de Admisión: barra +16 px en escritorio y +12 en móvil; tarjeta de título/acciones con jerarquía de Agenda operativa. No se modifica la navegación global. |
| ADMIN veía OCUPADO | Dos filtros distintos: `AgendaBoardPresenter` excluía citas sin actividad/pago; `rangeQuery` omitía reservas de otros responsables. La ocupación seguía contando esas citas y producía intervalos anónimos. | Se retira el filtro económico de presentación. Scope explícito `visibleToOperationalUser(User)` permite lectura a ADMIN activo con MVP_ACCESS/VIEW; los demás conservan `visibleToAgendaUser`. Se aplica a feeds, detalle, economía de lectura, documentos e historial operativo. |
| Postoperatorio / herida poco visibles | Ambos indicadores ya estaban persistidos y la lista ya filtraba sus badges. Los fixtures de integración anteriores no tenían esos valores; el encabezado del detalle descartaba todos los badges. | Se crean casos ficticios explícitos, sin inferir por servicio. Un badge por condición en lista y detalle. `HERIDA` se lee «PIE DIABÉTICO · HERIDA»; se conserva su clave y paleta. |
| Colores demasiado discretos | Franja de 3 px y estados generalmente neutros. | Franja de 5 px, ATENDIDO turquesa, reevaluación naranja suave y programado amarillo; etiquetas textuales. La cola distingue READY, pendientes e inactivos. No se colorea toda la fila. |
| Salida exigía motivo | SweetAlert, controlador y servicio lo imponían. | Confirmación sin textarea ni motivo requerido. Persiste hora_salida, actor, fecha/hora y evento; conserva ATENDIDO, importes y horarios. Retroceder estado sigue exigiendo justificación. |
| Salida lenta | En seis muestras, el POST consumió 72–106 ms de procesamiento después del boot QA. Después se pedían feed y detalle de forma secuencial, recalculando todo y deshabilitando todos los botones. No se reprodujo una espera SQL prolongada. | POST entrega también el detalle autorizado; el cliente actualiza fila, selección, indicadores y detalle con esa respuesta. Se elimina el par feed/detalle inmediato. Bloqueo visual solo en acciones de atención, aviso de progreso y una sola escritura. Se mantienen bloqueos, UUID y auditoría transaccional. |
| Botón Ver | Columna sexta dedicada a una acción pequeña. | Cinco columnas y fila seleccionable con clic, Enter y Espacio; foco visible, aria-selected, controles internos excluidos, repetición sobre selección actual sin otra consulta, conservación de scroll y borradores. |
| Documentos aportados | Ya existían almacenamiento privado, documentos por cita, validación MIME, versionado y visor histórico del paciente; faltaba una operación accesible a COMERCIAL sin UPDATE y una clasificación inequívoca. | Mismo controlador/modelo/store, propósito `PATIENT_PROVIDED`, política específica y upload idempotente en `appointment_operations`. Un componente compartido en ambas agendas. El historial del paciente reutiliza el endpoint existente. |

La ampliación de lectura de ADMIN **no autoriza escrituras** sobre reservas ajenas ni expone vouchers, disponibilidad pública o movimientos generales. Los tests conservan esos negativos. Los cuatro contratos anteriores que esperaban 404 para lectura ADMIN se adaptan a la decisión de negocio explícita, conservando sus demás aserciones. El seguimiento mensual permanece agregado y sin identidad. La cita tiene un único evento; los intervalos de continuación siguen representando su duración con una indicación compacta.

## Documentos y permisos

Todos requieren sesión, usuario activo, MVP_ACCESS/VIEW y visibilidad de la cita.

| Operación sobre documentos aportados | ADMINISTRADOR | COMERCIAL | ADMISION / otros |
|---|---|---|---|
| Consultar / descargar / vista privada | Sí | Sí, cita visible | Solo con `appointment.documents.view` existente |
| Adjuntar archivo | Sí | Sí, sin UPDATE ni pagos | VIEW + MANAGE + UPDATE existentes |
| Reemplazar con motivo y versión | Sí | No | VIEW + MANAGE + UPDATE existentes |
| Modificar pagos, precios, atención u órdenes | Sin ampliación | Sin ampliación | Sin ampliación |

No se crean permisos Spatie ni se cambian roles globales. `patientRead`, `patientUpload` y `patientReplace` son operaciones específicas por propósito; no habilitan documentos antiguos sin clasificar ni evidencias médicas acreditadas. ADMIN/COMERCIAL reciben el comportamiento solicitado exclusivamente para archivos aportados. Admisión sin permisos documentales sigue rechazada incluso cuando puede ver la cita.

El archivo se guarda en disco local privado con UUID; MIME por finfo, firma PDF o imagen real, límite 8 MB y límite de píxeles existente. Se vincula por appointment_id, y patient_id/fecha se resuelven desde esa cita. Se guardan nombre, categoría, actor y timestamps. Descarga/vista previa vuelven a autorizar cita y documento, con no-store, nosniff y CSP sandbox; ninguna ruta de almacenamiento se serializa ni se genera una URL pública. El reemplazo conserva bytes anteriores y relaciones de versión; ADMIN puede consultar en el mismo panel el historial de versiones retiradas, con etiqueta, actor y fecha (hasta 100), sin publicar sus rutas ni reabrir la descarga de versiones retiradas; requiere motivo y versión vigente. Una carga repetida con el mismo actor/UUID no duplica archivo ni evento. El fallo transaccional elimina solo el nuevo archivo no confirmado.

Los archivos aportados no aparecen en el selector de evidencia médica y su conversión directa a REEVALUATION_ORDER se rechaza; tampoco son comprobantes de pago ni entran a OCR. Las agendas y la ficha operativa del paciente excluyen Clarity; los paneles documentales están enmascarados. La auditoría guarda identificadores/categoría, nunca contenido clínico. La ficha tenía una dependencia Bootstrap Select ausente; se carga antes de custom.min.js para evitar los errores reproducidos al abrir ese historial. El contexto de retorno desde la ficha usa también el scope nuevo de lectura ADMIN, sin modificar el escritor de contactos. El historial ya disponible en «Ficha del paciente → Documentos de citas» filtra citas y archivos por acceso; no hay una segunda implementación documental.

La captura inicial demostró que el visor PDF nativo quedaba vacío bajo CSP. Se sustituye por PDF.js **6.4.299** fijado, servido localmente (módulo + worker + licencia, 1,7 MB aprox.), sin CDN ni cambio del almacenamiento. Se verifica SHA-512 del paquete oficial y SHA-256 de cada archivo: [procedencia](../../public/assets/vendor/pdfjs/PROVENANCE.json). El worker dibuja una página por vez en canvas: sin eval, XFA, anotaciones activas ni ejecución de scripts del PDF; imágenes limitadas a 16 MP y canvas a 2048 px por lado. Cerrar el visor o cambiar de paciente cancela la carga y destruye el worker. Se conserva la descarga privada si un PDF no puede renderizarse. Configuración y mantenimiento: [documentación oficial](https://mozilla.github.io/pdf.js/getting_started/) y [aviso de seguridad de Mozilla sobre eval](https://github.com/mozilla/pdf.js/security/advisories/GHSA-wgrm-67xf-hhpq).

No hay migración nueva: purpose (varchar32), actor_user_id, appointment_id, document_kind, timestamps, reemplazos, soft delete y operation.document_id ya existen. Se reutilizan sin backfill y sin alterar citas históricas. Desde main sigue siendo necesaria la migración aditiva previa de las tres tablas de procedimientos.

## Rendimiento medido

[Datos comparables y metodología](evidence/round3/PERFORMANCE.json), [seis muestras previas](evidence/round3/before-results.json), [seis posteriores](evidence/round3/after-results.json), [repetición con clic real](evidence/round3/replay-results.json), [perfil SQL sin bindings](evidence/round3/sql-departure-profile.json).

| Métrica | Antes | Después |
|---|---:|---:|
| Primer request → resultado visible, mediana | 866 ms | 297,5 ms |
| Rango de seis muestras | 743–1304 ms | 267–339 ms |
| HTTP inmediatos por salida | 3 | 1 |
| SQL del conjunto inmediato | 85 | 50 |

La repetición adicional mide el clic DOM real: 4,5 ms hasta el request y 293,5 ms hasta el resultado visible, en mediana. La comparación inicial también conserva el tiempo del comando Playwright (1691 →1124,5 ms), que incluye aproximadamente 825 ms de espera de accionabilidad de SweetAlert y **no representa por sí solo latencia del usuario**. Se documenta separado para evitar atribuir esa espera al servidor.

El POST completo ahora incluye el detalle: su duración individual no debe compararse con el antiguo POST aislado como si tuvieran el mismo alcance. Disminuyen el trabajo total y los viajes HTTP. El perfil directo del servicio registró 14 consultas, 15,26 ms SQL y 30,44 ms de servicio, sin transacción abierta al terminar. Incluye permisos, lectura/autorización, upsert y bloqueo de operación, bloqueo de médico/cita, actualización de salida, inserción de auditoría y cierre de operación. No se registran parámetros. Las carreras MariaDB verifican duplicación y coherencia; las muestras locales no permiten descartar contención específica en producción ni equivalen a una prueba de carga productiva.

Repetición segura con datos nuevos, desde agenda-v2 y el servidor local activo:

```powershell
$env:AGENDA_V2_ROUND3_STAGE='after'
$env:AGENDA_V2_ROUND3_REPLAY='APPEND_SYNTHETIC_ONLY'
node tools/qa/browser-agenda-v2-round3.cjs
```

Cada repetición añade seis citas exclusivamente ficticias y guarda archivos replay con timestamp; no borra salidas anteriores. El router seguro es el único que emite Server-Timing en QA. No se añaden métricas clínicas ni logs en producción.

## Capturas para revisión humana

[Antes 1366](evidence/round3/before-1366.png) · [Después 1366](evidence/round3/after-1366.png) · [1440](evidence/round3/after-1440.png) · [1920](evidence/round3/after-1920.png) · [Móvil](evidence/round3/rows-390.png) · [Documentos en Admisión](evidence/round3/documents-admin.png) · [Documentos en Agenda operativa](evidence/round3/documents-operative.png) · [Cola](evidence/round3/queue-1366.png) · [Historial del paciente](evidence/round3/documents-patient-history.png).

Se inspeccionan las imágenes, no solo el resultado automático. Los totales de las capturas corresponden a los fixtures acumulados por ejecuciones append; precios y saldos se contrastan mediante aserciones reproducibles. Se corrige también el corte del checkbox de procedimientos en 1366×768 detectado por el recorrido conservado. La aprobación visual y funcional del usuario continúa pendiente; no equivale a autorización de publicación.

## Cómo probar en la laptop

1. Chrome: `http://127.0.0.1:8092`. Entrar con las cuentas ficticias `agenda-integration-admin@example.invalid`, `agenda-integration-admission@example.invalid` y `agenda-integration-commercial@example.invalid`. La contraseña está únicamente en `storage/app/qa-v2/ACCESO_MANUAL_QA.md` o `browser-private.json`, ignorados por Git.
2. Seleccionar 19/10/2026 y MÉDICO INTEGRACION FICTICIO. Con ADMIN comprobar reservas UNPAID/ADVANCE y citas confirmadas de otro responsable. La fixture ADVANCE no inventa dinero: las pruebas de adelanto real están en la regresión de pagos; el nombre por sí solo no acredita un pago.
3. En Admisión seleccionar las filas POSTOPERATIVE y WOUND; comprobar indicador, motivo y colores. Cambiar con teclado y probar Más filtros.
4. COMERCIAL → Documentos e historial → Documentos de la cita: adjuntar PDF/JPG/PNG ficticio. ADMIN debe descargarlo y puede adjuntar/reemplazar conservando auditoría. ADMISION sin permisos de documentos debe quedar restringido. También está disponible en Agenda operativa → Documentos de la cita, y en la ficha → Documentos de citas.
5. Usar las citas ficticias nuevas de benchmark para salida. La confirmación no pide motivo; debe conservar ATENDIDO. Retroceder estado continúa pidiendo justificación y no reabre ATENDIDO.
6. El conjunto separado `round3-regression/fixtures.json` contiene las cuentas y citas de la matriz completa. Se añadió porque la cita original FRESH2 ya tenía una salida manual; no se alteró ni se borró ese evento para forzar una prueba.

El servidor permanece abierto fuera del runner. Reinicio seguro: `./tools/qa/start-agenda-v2-manual.ps1 -RestartOwnedServer`. Verifica la instancia ficticia y arranca PHP con upload_max_filesize=8M/post_max_size=10M; el proceso anterior tenía 2M y se corrigió solo en QA. Detener ERP: `./storage/app/qa-v2/stop-manual-qa.ps1`. Añadir `-StopDatabase` para detener también la MariaDB ficticia mediante shutdown ordenado; los datos se conservan.

## Despliegue y rollback

Sin despliegue autorizado. Además del plan previo, comprobar almacenamiento privado y respaldos coordinados de BD+archivos, permisos del disco y límites PHP/proxy suficientes para 8 MB (post_max_size superior). No modificar permisos generales de COMERCIAL. Validar los tres roles antes de habilitar tráfico.

Rollback conserva appointment_documents, appointment_operations, historial y todos los archivos/versiones; no ejecutar down ni borrar archivos. El código antiguo ignora PATIENT_PROVIDED y su reemplazador no conserva ese propósito: **un rollback ciego a 840522e no es suficiente**. Bloquear temporalmente escrituras documentales durante la reversión y conservar la política/lector/versionado compatible de esta ronda, o desplegar un paquete de compatibilidad probado. No reclasificar archivos ni restaurar una BD anterior que elimine cargas posteriores. La reversión no cambia pagos ni confirma/desconfirma citas.

Riesgos pendientes: aceptación humana, límites/almacenamiento del hosting aún no inspeccionados ni modificados, rendimiento productivo no medido, análisis antivirus no forma parte del validador existente. Los documentos nuevos se conservan separados de órdenes acreditadas y comprobantes. Las reglas anteriores de reevaluaciones, precios, pagos y estados críticos siguen bajo regresión.

## QA final

SQLite1083/9468 aserciones; MariaDB1092/9556, incluidas nueve carreras; JavaScript318; Chrome29 (19 de regresión y10 nuevos). Sin fallos, errores, omisiones ni errores JavaScript; procesos terminados con exit0. [Resultados completos](QA_Y_EVIDENCIAS.md) y [JSON](evidence/round3/RESULTADOS.json). El historial documental, PDF renderizado y reemplazos se revisaron en capturas. Pendiente solamente revisión humana y validaciones de hosting/despliegue expresamente autorizadas.
