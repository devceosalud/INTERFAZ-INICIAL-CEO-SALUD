# CEO Salud: estabilización Comercial, segunda ronda

Fecha: 09/10/2026, America/Lima. Revisión local; sin push, merge, PR ni despliegue.

## Línea de desarrollo y preservación

Base remota y productiva informada: `36ec2072183959f28d4a6d86333ff2a2b6443196`.
`git ls-remote origin refs/heads/main` volvió a confirmar ese SHA durante este cierre.
La constatación productiva corresponde al informe de Rodrigo, no a una conexión del agente a Hostinger.

Rama de trabajo conservada: `codex/stabilize-commercial-pilot`.
HEAD inicial de la segunda ronda: `daafe1a43d12b23481cfa47ef9c2324f82ae686b`.
Se conservan como ancestros los cuatro commits anteriores:

1. `277a58854a6cc3d7490e5d998917eda8cb861b18`: guardado seguro de datos operativos.
2. `2ee7816f7dda90bca9e7f411dfbf0cd3cdfdf578`: sesión en edición de usuarios.
3. `a7d2409aff64220038f83c605e700692403e4a77`: distribución y acceso a paneles de Agenda.
4. `daafe1a43d12b23481cfa47ef9c2324f82ae686b`: diagnóstico y revisión de despliegue.

El checkout original permanece en `pilot/agenda-horarios-clientes`, HEAD
`c76498b565eb7c9bd3f012ca3b21ccf8ff653f5e`. Sus cinco archivos modificados no se mezclaron:

| Archivo original | Diferencia respecto de la rama de estabilización |
|---|---|
| DoctorController.php | Comentarios/formato y cambio funcional de `code: 1` a `code: 0` en una respuesta de error; protegido, pendiente de revisión independiente. |
| DoctorServiceController.php | Comentarios; código equivalente después de normalizar EOL para la comparación, sin editar el archivo. |
| UserController.php | Comentarios; código equivalente. |
| AgendaBoardPresenter.php | EOL; código equivalente. |
| OperationalAgendaAppointmentService.php | EOL; código equivalente. |

Los hashes SHA-256 de los cinco archivos coincidieron con los preservados al iniciar.
Los tres controllers heredados no se modifican ni se incluyen en los nuevos commits.
El archivo no rastreado `a` es una captura de estadísticas/advertencias Git, no implementación.
Se conserva y se excluye del release; no se descarta sin necesidad.

## Cancelación, inasistencia y RETIRO

Se incorpora un flujo explícito en «Estado de cita y seguimiento», reutilizando
AppointmentWithdrawalService, AppointmentHistory y appointment_operations.
No se crea otro sistema de estados o de dinero.

| Operación | Regla aplicada |
|---|---|
| RETIRO | Paciente llegó y se fue; se conserva la validación de presencia existente. |
| CANCELACIÓN | Motivo obligatorio; misma cita, sin borrar ni mover fecha/hora; historial con actor. |
| NO ASISTIÓ | Nunca llegó; solo al terminar el intervalo, con duración conocida y sin evidencia de llegada. |

Capabilities nuevas: `appointment.cancel` y `appointment.no_show`. Para Comercial/Admisión
deben aprobarse y asignarse expresamente junto al acceso/view de Agenda. Ni el rol ADMINISTRADOR
ni `appointment.update` sustituyen estas capabilities. No se concedieron permisos a cuentas reales.

CANCELACIÓN y NO ASISTIÓ se permiten inicialmente sin vínculos financieros. Se rechaza cualquier
VoucherItem vinculado (incluso ticket impago), crédito entrante/saliente, solicitud de devolución o
snapshot pagado positivo. Los casos con dinero/documentos requieren revisión; no se eliminan pagos,
comprobantes ni movimientos de caja, no se crean devoluciones y no se inventa su disposición financiera.

El servicio conserva transacción, idempotencia por actor/request_key, bloqueo de médico y cita,
privacidad por dueño efectivo y respuesta 404 para cita privada ajena. Los cierres liberan ocupación
mediante las reglas de ciclo de vida ya existentes. Sales y la reprogramación preservan sus reglas anteriores.

Se encontró y cerró un bypass en las ediciones heredadas de citas y agenda de Admisión:
AppointmentClosingGuard impide cerrar o reabrir RETIRO/CANCELADO/NO_ASISTIO desde esos endpoints.
Las demás ediciones permanecen operativas. El guard no depende del feature flag: apagar Agenda
no rehabilita el cierre heredado que eludía historial y revisiones financieras.

### Procedimiento propuesto para la cita productiva de prueba #14

No se consultó ni modificó #14. Antes de cualquier operación, el responsable debe verificar
estado, duración, evidencia de llegada y todos los vínculos financieros, sin divulgar identidad del paciente.
Si no hay pagos/créditos/solicitudes ni documentos financieros, y negocio aprueba la anulación,
un actor autorizado con `appointment.cancel` utiliza CANCELACIÓN con motivo identificable de QA.
Debe comprobar misma cita/fecha/hora, evento con actor y horario liberado.
Si hay dinero o un comprobante, este lote bloquea la operación: Facturación debe aprobar el tratamiento.
Nunca declarar presencia ficticia para usar RETIRO, borrar la cita o actualizar el estado por SQL directo.

## Heatmap de Agenda reconocible y sin pacientes reales

Único colector: `POST /ui-telemetry/click-events`.
La Agenda emite v3: coordenadas relativas del click, rectángulo real de la zona, viewport y estado
técnico controlado de paneles/scroll/selección. El profile key se calcula en backend.
No se captura texto del DOM, screenshots, valores de inputs, identificadores de paciente/cita/usuario,
DNI, HCE, teléfono, nombres ni contenido médico. Los clicks en INPUT/TEXTAREA/contentEditable se omiten.
Campos y paneles admitidos se validan mediante listas cerradas; PII anidada y v3 de otros módulos se rechazan.

El visor y su nuevo preview requieren ADMINISTRADOR en backend. El preview reutiliza los parciales
reales de filtros, médicos, mini calendario y Registro rápido, las clases de la grilla y las leyendas;
emplea catálogos fijos de demostración. El test verifica que no consulta tablas de pacientes/citas/médicos.
El documento es inert y no carga handlers de Agenda ni colector. CSP bloquea conexiones y formularios.
No contiene datos de pacientes reales ni credenciales.

Los puntos v3 conservan posición capturada del viewport; se comparan los rectángulos de las zonas:
azul significa zona coincidente (tolerancia 4 px), ámbar referencia aproximada.
La coincidencia de zona no promete identidad del contenido interno: textos, filas y permisos de una
sesión real pueden diferir del fixture sanitizado. Los perfiles separan dimensiones, vista y estado técnico.

Los eventos v1/v2 no tenían geometría/scroll suficiente para reconstrucción exacta. Se seleccionan
explícitamente como aproximados, sin inventar scroll. Los puntos fuera de la referencia visible se contabilizan
y se explican. Horarios y Pacientes conservan el colector/filtros/preview esquemático v2; el preview fiel v3
de esta incidencia corresponde a Agenda. El límite es 30 perfiles recientes y 2000 puntos del perfil seleccionado.
La telemetría es best effort; fallo del endpoint no bloquea módulos ni constituye una auditoría financiera.

## Registro rápido y Horarios Médicos

Registro rápido: cabecera dentro de un solo scroll, sin sticky que tape Guardar reserva; datos de contacto
en dos columnas uniformes y una columna en móvil; labels alineados, textarea redimensionable y fila de guardado
integrada con su explicación. Se preservan IDs, handlers, validación y PATCH de paciente/motivo/observación.
Los valores vacíos siguen conservando información existente; estos botones no crean otra cita.

Horarios: se retiran + Ausencia, + Horario excepcional y el anuncio de funciones no implementadas.
Se mantiene + Horario y el contrato actual de permisos, sin ampliar roles. La cabecera/acciones no se encogen
ni invaden filtros; en móvil se apilan. No se modifican horarios operativos ni citas heredadas.

## Migración y compatibilidad

Nueva migration: `2026_10_09_120000_add_click_event_geometry.php`.
Depende de las migrations del colector de 05/10 y de layout_version/zone de 06/10.
Añade JSON nullable `geometry`, CHAR(64) nullable `geometry_key` e índice corto `ace_geometry_idx`.
No hace backfill, no cambia v1/v2 y no altera appointments ni finanzas.

Orden recomendado: desplegar esta migration aditiva por path individual, después activar el código v3.
Código antiguo ignora columnas adicionales. Sin nuevas columnas, collector v3 devuelve 503 best effort,
el visor puede consultar v1/v2 y Agenda sigue operativa; los nuevos eventos no se guardarán hasta instalarla.

Rollback se bloquea si hay geometría v3. Sin v3, se permite en MariaDB y SQLite >=3.35;
SQLite anterior se rechaza ANTES de eliminar el índice/columnas. No efectuar rollback con datos reales.

Validación final ejecutada sobre MariaDB 10.4.32 local (127.0.0.1:3308), en la base exclusiva
`ceo_qa_geom_20261009_dcaa6b0f`: migrations antiguas + filas ficticias v1/v2 + nueva migration;
integridad de filas antiguas, persistencia JSON/key, consulta agrupada, índice, bloqueo de rollback con v3,
rollback sin v3 y reaplicación. Todas las comprobaciones pasaron y la base temporal fue eliminada.
También se verificó una base temporal anterior `ceo_qa_geom_20261009_8bf6b47d`, eliminada.
No se aplicó la nueva migration a ERPCEOSALUD ni a producción.
La compatibilidad reproduce el schema heredado del heatmap; no sustituye verificar versión/schema de Hostinger.

## Pruebas y evidencia

- Laravel completo FINAL: `php artisan test --env=testing`: **558 passed / 1 skipped / 0 failed**, 44.44 s.
- JavaScript completo: todos los `tests/JavaScript/*.test.js`: **186 passed / 0 failed / 0 skipped**.
- AgendaHeatmapGeometryTest después del ajuste: 6 passed; guarda SQLite probada sin salto.
- Omisión Laravel conocida: rollback nativo de schema con SQLite local anterior a 3.35.
- PHPUnit fuerza SQLite en memoria, proveedores de prueba y Http::preventStrayRequests; no usa BD operativa.
- Sintaxis PHP y `git diff --check` sin errores; advertencias CRLF no implican cambio semántico.

QA aislado del navegador: servidor 127.0.0.1:8002, APP_ENV testing, SQLite followup-qa.sqlite;
COMERCIAL y ADMINISTRADOR ficticios creados en esa base, nunca cuentas reales.

| Verificación | Evidencia |
|---|---|
| Cancelación e inasistencia | UI de segunda ronda: citas ficticias 1 CANCELADO y 2 NO_ASISTIO; mismas fechas originales y eventos con actor, posteriormente comprobados en SQLite. |
| Guards/capabilities/dinero/privacidad/fin de intervalo/RETIRO | Tests; no se intentó cerrar una cita financiera real por navegador. |
| Registro rápido actual | UI 1366x768 y 390x844: alineación, foco, scroll y confirmación «Datos del paciente guardados.». Sin citas nuevas al guardar datos. |
| Horarios | UI 1024x768 y 390x844: solo + Horario, acciones debajo de filtros, sin desbordamiento horizontal. |
| Heatmap ADMIN | UI: preview real sanitizado, perfil de escritorio 2 clics/2 coincidentes; módulos, zonas, vistas y aproximaciones históricas. |
| Heatmap COMERCIAL | UI 403, además de tests de autorización del visor/preview. |

Capturas antes/después incluidas en el paquete externo. «Heatmap antes» es el renderer anterior
reconstruido desde daafe1a con eventos ficticios, NO una captura de producción. Las otras capturas son
del QA aislado. No se efectuó un recorrido financiero nuevo: ese código no cambió en esta ronda.

Fixtures exclusivos de SQLite antes de la limpieza: usuarios 1/2, paciente 1, médicos 1–9, horario 1, citas 1/2,
eventos 1–6 y 14 eventos de telemetría al cierre visual. Vouchers, payments, cash_movements, documentos,
créditos, devoluciones y contingencias: 0. No corresponden a IDs de ERPCEOSALUD ni de producción.
Las bases temporales MariaDB y el SQLite desechable quedaron eliminados con guardas; se conservó
su inventario de evidencia. Se detuvo únicamente el servidor QA del puerto 8002 identificado por PID,
ejecutable y ruta del router. No se eliminaron registros ni archivos de la base operativa o respaldos.

## Información productiva recibida y decisiones pendientes

Informe de Rodrigo 09/10/2026, solo lectura: SHA 36ec207, árbol limpio y posible detached HEAD;
config Laravel cacheada; AQPFACT efectivo con token ejemplo, Factiliza sin token.
COMERCIAL sin appointment.payment.submit, sin turno propio, flag de caja automática false y cero contextos.
Hay turnos antiguos y una BOLETA pagada con SUNAT pendiente: clasificación exclusiva de Facturación.

Catálogo informado: médicos 3/9 con horarios sin servicios; 4–8 sin horarios/servicios.
Doctor 1 Trauma ya tiene 150/120 activo y 100/80 inactivo: no hay corrección por IDs que repetir.
Todos los horarios activos y las cuatro citas de 09/10 carecen de sede, sin citas posteriores.

El código conserva site_id null: disponibilidad incluye filas legacy sin sede, validación de alta compara
la sede exacta del slot y ocupación se protege globalmente; no se inventa una sede por defecto.
La UI avisa «Sin sede registrada». Negocio debe conciliar sedes, servicios y horarios futuros válidos.

Antes de producción se requieren aprobación y ejecución CONTROLADA por responsables de:

1. Capabilities cancel/no_show y payment.submit para las cuentas autorizadas; no permisos generales de Caja/auditoría.
2. Caja/turno/serie o configuración de caja piloto; confirmar cita sigue siendo una acción distinta de registrar pago.
3. Factiliza/provider/token y regeneración controlada de caché; no publicar token, consultas reales solo smoke aprobado.
4. Horarios futuros, servicios y sedes oficiales; ninguna regularización automática en este lote.
5. Tratamiento de cancelación/inasistencia con dinero o documentos y de la boleta SUNAT pendiente.
6. SHA/rama y mecanismo de despliegue Hostinger, backup y versión MariaDB/schema antes de migration.

## Despliegue y contención propuestos, NO ejecutados

Revisar el paquete y commits acumulados desde 36ec207, backup BD + storage privado + configuración segura,
aprobar distribución de capabilities y configuración. Preparar deployment desde la línea revisada;
no push, merge, PR ni ejecución remota desde esta tarea.

Aplicar migration heatmap por path individual y verificar columnas/índice/fila de migrations; publicar código
y actualizar caches con procedimiento del responsable; smoke ficticio autorizado y limpieza selectiva.
Si falla: SCHEDULING_MVP_ENABLED=false contiene Agenda; suspender altas/adelantos del piloto según configuración;
revertir código a SHA conocido si es necesario, conservando schema/datos. No usar rollback destructivo de migrations.
El guard heredado de cierres permanece con flag OFF; si hace falta un cierre, usar procedimiento aprobado,
no reabrir el bypass. Detener colector es suficiente para contener telemetría; no borrar su historia.

Veredicto: **GO técnico LOCAL para revisión de la estabilización; NO-GO para despliegue automático**.
La habilitación productiva sigue condicionada a configuración, catálogo oficial, permisos, revisión financiera,
backup y autorización expresa. No se cambió producción, Windows, .env ni permisos reales.
