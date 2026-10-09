# Estabilización Comercial — revisión previa al despliegue

## Línea de desarrollo preservada

Verificación remota del 09/10/2026: `origin/main` es
`36ec2072183959f28d4a6d86333ff2a2b6443196` (merge PR #1).
El checkout previo está en `pilot/agenda-horarios-clientes`,
`c76498b565eb7c9bd3f012ca3b21ccf8ff653f5e`; ambos tienen el mismo árbol.
El piloto es ancestro de main; no había commits pendientes de publicar en esa rama.
El main local permanece en su baseline anterior; no se actualizó.

Los tres controllers heredados y las dos marcas CRLF/LF de servicios se dejaron
en el checkout original. No se copiaron sus modificaciones al parche.
El stash histórico y la rama piloto se preservaron. Este trabajo usa el worktree
`stabilize-commercial-pilot`, rama `codex/stabilize-commercial-pilot`, desde main
verificado. No contiene `.env`, secretos, fixtures ni respuestas productivas.
No se realizó push, merge ni despliegue. El SHA real de Hostinger sigue pendiente.

## Cambios y contratos

1. **Contacto del paciente:** PATCH `/patients/{id}/agenda-contact`, únicamente
   teléfono principal/secundario, canal activo y medio activo. Conserva el acceso
   de ficha existente (COMERCIAL/ADMISION/RECEPCION; ADMINISTRADOR sigue en lectura).
   Si llega contexto de cita, valida visibilidad y correspondencia con paciente.
   No modifica identidad, dinero ni agenda. Vacíos conservan el dato previo;
   para quitar datos deliberadamente se utiliza Completar ficha.
2. **Motivo/observación:** PATCH `/scheduling-mvp/agenda/appointments/{id}/notes`.
   Requiere acceso a Agenda, visibilidad y `appointment.update`, o `appointment.create`
   cuando el actor es dueño efectivo. No hay bypass de privacidad para ADMIN.
   Registra actor y campos cambiados en appointment_events; no crea otra cita.
3. Botones separados y feedback junto a los campos. El registro inicial conserva
   su endpoint/transacción. Captura parcial vacía tampoco borra datos en un alta.
4. El endpoint económico entrega `es_exonerado` como boolean JSON: una cadena
   `"0"` de PDO no debe encender una exoneración al usar Boolean() en JavaScript.
   No modifica contratos económicos ni calcula una autorización como dinero.
5. Paneles de médicos/calendario con alto automático, lista desplazable y leyenda
   fuera del área que Registro rápido cubría. En móvil se elimina el límite de
   145px que recortaba el panel de médicos. Se mantienen semáforo y ayudas existentes.
6. Editor de Usuarios manda sesión, CSRF y Accept JSON. Distingue 401/419/403,
   redirects y HTML antes de parsear; devuelve error operativo. La API entrega
   solo id/name/email. No registra respuestas/personas en consola. No cambia
   passwords, roles ni los controllers protegidos.

   Se corrigió además el orden de middleware en Kernel: la API interna combinada
   con api/throttle ordenaba StartSession antes de App EncryptCookies. El navegador
   perdía autenticación porque la sesión intentaba leer la cookie cifrada. Ahora
   la subclase propia de EncryptCookies tiene prioridad explícita; el flag MVP
   sigue primero. Se mantienen auth/rol/CSRF, sin bypass. Test de cookie cifrada
   sin actingAs reprodujo401 antes y200 después; las pruebas anteriores basadas
   solo en actingAs no detectaban este problema. La separación de cookie del
   servidor QA descartó interferencia entre instancias como causa del fallo.

La asignación de roles sigue su flujo existente. La prueba de asignación a otro
usuario conserva la sesión del administrador y la contraseña del usuario objetivo.
No se reprodujo en producción el redirect reportado; hace falta evidencia HTTP.

## Comercial: configuración propuesta, pendiente de aprobación productiva

El mensaje «No tienes permiso para registrar adelantos» se decide en
AgendaBoardController mediante `user->can('appointment.payment.submit')`.
No demuestra un fallo de turno/serie: esas comprobaciones ocurren después.
No se concedieron permisos a cuentas reales.

| Capability | COMERCIAL autorizado | ADMISION operativa | ADMINISTRADOR |
|---|---|---|---|
| appointment.mvp.access / appointment.view | Sí | Sí | Sí |
| appointment.create | Sí | Sí | Según función |
| appointment.payment.submit | Solo operadores aprobados | Solo operadores aprobados | Si registra adelantos |
| appointment.reschedule | Sí si reprograma | Sí | Si reprograma |
| appointment.additional.create | Sí si atiende adicionales | Sí | Según función |
| appointment.withdraw | Sí | Sí | Sí |
| appointment.update | No necesario para notas propias | Solo edición transversal aprobada | Para edición operativa |
| appointment.responsible.assign | No | No | Solo administrador autorizado |
| appointment.audit.view | No | No por defecto | Sí si audita |
| appointment.overbook / payment.verify / zero_cost.approve / down_payment.override | No | No por defecto | Aprobación específica, nunca automática |

La vista del heatmap exige rol ADMINISTRADOR en backend. No basta audit.view.
El contrato vigente desde `714a0e49` permite escrituras de Horarios también a
COMERCIAL; este parche no revierte esa decisión ni inventa capabilities de horarios.
Si CEO quiere volver a ADMISION solamente, necesita una decisión separada.

Otorgar payment.submit **solo a las cuentas nominadas**, con guard web y registro
before/after de sus permisos efectivos (rol + directos). No convertir a ADMIN ni
otorgar permisos generales de Caja. Asegurar que permisos sean conocidos por
Spatie y su caché tras una modificación aprobada. No ejecutar grants ahora.

### Dos modos de caja, elegir uno conscientemente

* `SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT=false`: requiere exactamente
  un turno manual abierto del actor y una serie TICKET activa de su caja.
* `true`: AppointmentTicketService usa PilotAppointmentCashContext. Con permiso
  payment.submit y rol COMERCIAL/ADMISION/ADMIN, crea al primer pago una caja
  contable exclusiva, serie `P` + ID base36 de tres caracteres (IDs 1..46655), y
  turno por actor/día America/Lima. Guarda origin AGENDA_PILOT_AUTO; rechaza
  colisión de serie o contexto inválido. No necesita crear otra caja manual.

La misma transacción conserva idempotencia request_key, Voucher/VoucherItem y
Payment reales, deduplicación bancaria global y saldo. No envía SUNAT; TICKET
interno no sustituye comprobante fiscal. Revisar series, estado/cierre y conciliación
de estos contextos con el responsable financiero antes de activar el flag.
No liberar una serie usada ni reabrir un contexto automáticamente como reparación.

Registrar adelanto y confirmar siguen siendo acciones distintas. Pagar 50% no
confirma automáticamente una reserva si se usó Registrar adelanto. Confirmar
requiere >=50% real o autorización válida existente. No duplicar Payment para
confirmar ni interpretar documentos como dinero. Multilínea ambiguo sigue rechazado.

Prerequisitos de schema: lifecycle, registro/documentos, retiro/historia, contextos
piloto y `2026_10_07_140000_add_bank_identity_to_payments_table` si registra pagos.
Esta corrección **no agrega migrations**. Verificar migrate:status antes del release;
no proponer migrate general ni rollback de datos financieros.

## Factiliza: diagnóstico antes de cambiar configuración

Proveedor actual por defecto: aqpfact. Factiliza necesita valores efectivos:

```
RENIEC_PROVIDER=factiliza
FACTILIZA_BASE_URL=https://api.factiliza.com/v1
FACTILIZA_TOKEN=<secreto gestionado por el responsable>
```

La implementación ya usa GET `/dni/info/{dni}` con Bearer desde Laravel,
timeout 5s/connect 2s, sin redirects, DNI de 8 dígitos y confirmación humana.
Coincide con [documentación oficial](https://docs.factiliza.com/api-consulta/endpoint/dni).
No se cambió arbitrariamente el proveedor por defecto ni se consultaron DNIs reales.
Token ausente, HTTP inválido, rate limit o timeout permiten registro manual.
Tests simulan HTTP y verifican que la consulta no crea/sobrescribe Patient.

Después de desplegar el parche, el responsable puede ejecutar:

```
php artisan pilot:diagnose --user=<ID_COMERCIAL_APROBADO>
```

Produce flags efectivos, provider, **presencia booleana** del token, base oficial,
schema y capabilities/turnos/series del operador. No muestra token, DNI, nombres,
passwords ni saldos; no llama proveedores ni escribe entidades financieras.
No confundir `.env` con config cache: comparar provider efectivo/config_cached.
Un cambio aprobado de env requiere regenerar caché mediante el procedimiento
de despliegue del responsable. No hacerlo durante esta auditoría.

Antes de que exista el comando nuevo, enviar solo: proveedor efectivo, token
presente sí/no, base oficial sí/no, config cached sí/no, flags booleanos. Obtenerlos
en el bootstrap Laravel por el responsable; NO enviar `.env` ni bootstrap/cache/config.php.
Revisar salida HTTPS desde el servidor sin consultar pacientes (DNS/TLS). Después,
una consulta funcional debe ser explícitamente autorizada con datos controlados;
nunca registrar Authorization o el URL completo con DNI.

## Hostinger: lo que Rodrigo debe consultar

No se utilizó acceso productivo. El nombre srv-prd01 no prueba SHA ni autodeploy.

1. En hPanel: **Sitios web → Administrar → Avanzado → GIT** (si el servicio usa
   esa integración). Capturar repositorio, ruta instalada, rama configurada y
   estado de despliegue automático; revisar salida del último deploy. **No pulsar
   Deploy ni cambiar switches.** Ver [guía Hostinger](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/).
   Si es VPS sin ese menú, informar el mecanismo del responsable: webhook,
   script, Actions, Git pull manual o subida de archivos. No inferirlo de main.
2. En terminal SSH del responsable, situarse en el directorio real del ERP:

```
pwd
git branch --show-current
git rev-parse HEAD
git status --short
git log -1 --format="%H %cI %s"
php -v
php artisan migrate:status
```

   Lectura únicamente; no pull/checkout/migrate/cache clear. Si no existe .git,
   pedir identificador del artefacto y manifiesto/checksums del deploy. La fecha
   del commit NO demuestra fecha de push o instalación.
3. Consultar el SQL adjunto con cuenta SELECT. Entregar resultados de catálogos y
   conteos; no exportar patients, users passwords, vouchers con identificación,
   adjuntos ni operaciones bancarias. Primero bloque 0, luego bloques disponibles.
4. Para Usuarios: DevTools → Network → click lápiz → request
   `/api/admin/user/search`. Enviar únicamente method, status, Content-Type,
   si redirigió y URL final sin parámetros sensibles; verificar presencia de
   X-CSRF-TOKEN/Accept **sin copiar el valor**. Repetir en asignación de rol si
   sigue cerrando sesión. No enviar body JSON del usuario ni cookies.
5. Datos aún necesarios: SHA/rama, mecanismo/auto deploy, PHP y DB versions,
   migrations aplicadas, operador ID/roles/capabilities, flags efectivos,
   series/turnos, configuración Factiliza, storage privado escribible, logs
   sanitizados del error, catálogo oficial aprobado y backups disponibles.

## Inventario y conciliación de datos

[COMMERCIAL_PILOT_READ_ONLY_AUDIT.sql](COMMERCIAL_PILOT_READ_ONLY_AUDIT.sql) contiene
inventarios activos/inactivos, especialidades, servicios, tarifas, horarios,
solapes compatibles/incompatibles, historia y dependencias agregadas, candidatos
ficticios y permisos. Inspecciona schema primero. **No se ejecutó en producción**;
por tanto no existe aún inventario productivo confirmado.

Una etiqueta TEST/DEMO/QA es sospecha, no autorización de borrado. No considerar
una especialidad incorrecta únicamente porque no coincide con las tres principales.
No inferir qué fila doctor_services se usó históricamente: Appointment no guarda
su ID. Sus snapshots permanecen intactos.

CEO debe devolver por ID: médico oficial, especialidad, servicio, precio vigente,
sede, fechas/weekday, rango, duración y estado deseado. Propuesta de conciliación:

| Clasificación | Acción propuesta para aprobación futura |
|---|---|
| Oficial válido | Mantener |
| Histórico con citas/pagos | Conservar; evaluar inactivar oferta futura con negocio |
| Duplicado ACTIVO | Elegir una asignación vigente por significado/precio, inactivar la otra; nunca fusionar pagos |
| Sospecha ficticia | Revisar dependencias y confirmar con CEO; ninguna limpieza automática |
| Nulo/inconsistente | Bloquear uso ambiguo y confirmar tarifa/horario |

Reglas ya confirmadas: Julio Quiroz Trauma/Pie diabético consulta150/reconsulta120;
médicos generales CEO Trauma100/80. Identificar por doctor+service, **no IDs locales**.
La inactivación local previa de la tarifa100/80 de Quiroz no viaja en Git.
Preparar before/after con IDs, estado/precio, aprobación y rollback lógico al estado
anterior; no tocar precios/snapshots de citas, dinero ni historia.
Un horario incompatible con distintas sede/duración requiere revisión de Admisión.
Cambiar horarios nunca implica borrar reservas/citas; conservar contingencias.

## Pruebas y revisión

Regresiones específicas: edición parcial sin duplicación/erosión, privacidad404,
denegación403, notas con actor, guardado inicial, JSON/CSRF/sesión de Usuarios,
roles sin pérdida de sesión, Factiliza fake/manual, diagnóstico sin secretos/escrituras.
JS ejecuta clicks de guardado contra PATCH y errores inline, no solo inspección estática.
Un test de Horarios se hizo tolerante a CRLF/LF conservando su verificación de orden;
no representa un cambio de dominio.

QA navegador en servidor aislado127.0.0.1:8001, SQLite nuevo fuera del repositorio:
usuarios y pacientes exclusivamente ficticios. No se modificó ERPCEOSALUD ni el
servidor local anterior. Capturas antes/después con CSS baseline y datos ficticios,
restaurando el parche byte a byte después de capturar. El reporte final distingue
pruebas de navegador, tests y evidencia productiva pendiente.

Resultado final local (09/10/2026): `php artisan test --env=testing`:
**547 passed / 1 skipped / 0 failed**, 40.50s. Omisión: rollback nativo DROP COLUMN
en SQLite local <3.35; no se ejecutó rollback MariaDB. `node --test
tests/JavaScript/*.test.js`: **184 passed / 0 failed / 0 skipped**.
Rutas y sintaxis PHP verificadas; diff check sin errores.

| Verificación | Evidencia real |
|---|---|
| Contacto/captación posterior | UI Comercial: PATCH, mensaje, recarga y persistencia |
| Motivo/observación posterior | UI Comercial: PATCH, mensaje, recarga; vacío conserva motivo |
| Reserva → adelanto → confirmación | UI Comercial: reserva privada, pago75/150 separado, confirmación posterior |
| No duplicación | SQLite QA: 1 Patient, 1 Appointment, 1 Voucher, 1 Payment por75; historia del actor |
| Lápiz Usuarios | UI Admin: modal abierto con nombre/email ficticios; contraseña vacía; cancelado |
| Asignación de rol | UI abre formulario sin perder sesión; envío/capabilities/password verificados en tests |
| Factiliza | Tests con HTTP fake; ninguna consulta externa real ni prueba productiva |
| Médico9 | Seleccionado por UI desplazando la lista; encabezado se actualiza |
| Responsive | 1920x1080,1366x768,1024x768,390x844; sin overflow horizontal; leyenda antes del registro |
| Calendario | Feb2027 (4 semanas), Dic2026 (5), Ago2026 (6): leyenda visible; UI conserva grilla de42 días |
| Privacidad/403/CSRF | Regresiones automatizadas; no se relajaron controles |

Fixtures pertenecen exclusivamente al archivo SQLite desechable del QA:
usuarios1/2, médicos1..9, paciente1, horario1, cita1, voucher1, payment1 y eventos
del actor1. Estos IDs **no son ERPCEOSALUD ni producción**. No hacer limpiezas por
esos IDs en otra base. Servidor y base de QA se retiran al cerrar; las capturas
permanecen como evidencia fuera de Git. Fixtures previos de otros lotes se conservaron.

Limitación visual heredada: assets/custom.min.js reporta selectpicker ausente en
algunas páginas Admin. El editor y formulario de roles se verificaron funcionando
tras la corrección de sesión; no se refactorizó la plantilla/vendor global en este
parche. Revisar ese asset por separado si afecta otra acción concreta.

## Pull request y despliegue preparados, no ejecutados

Título propuesto: **Estabilizar guardado operativo, editor de usuarios y paneles de Agenda**.
Base propuesta main; rama codex/stabilize-commercial-pilot. Comparar GitHub antes
de publicar. Si cambió main, revisar divergencia antes de integrar; no forzar/rebase
destructivo ni sobreescribir la otra sesión. No se creó PR remoto sin push.

Deploy futuro: aprobar permisos/config y catálogo, identificar SHA real, respaldar,
publicar/revisar PR, aprobar merge y despliegue según mecanismo verificado.
Este parche no necesita nuevas migrations. Las migrations del piloto previo deben
estar aplicadas antes de su código consumidor; si faltan, detener activación y
usar el plan de release previo, nunca improvisar migrate general.

Backup mínimo: dump BD completo consistente con versión/hora y prueba de lectura,
storage/app/appointment-documents privado con listado/checksum, copia protegida
de env/config y artefacto/SHA actual. Confirmar existencia, tamaño y recuperación
en destino seguro antes de cualquier deploy/config/datos. No adjuntar esos backups
al PR ni compartir secretos. No publicar documentos mediante storage symlink.

Rollback: contener Agenda con SCHEDULING_MVP_ENABLED=false si el piloto completo
falla; apagar cash automático exige turnos manuales válidos y no borra Payment;
Factiliza puede quedar indisponible con ingreso manual. Revertir únicamente los
commits de este parche si falla la corrección, conservando los avances anteriores.
Reponer configuración exacta anterior aprobada; nunca hacer rollback financiero
o de migrations con datos reales como primera medida. Los datos registrados
siguen existiendo aunque se retire el botón nuevo.

Smoke tras aprobación: login Comercial autorizado, paciente ficticio, reserva,
guardar contacto y notas/recargar, adelanto real controlado por responsable
financiero, confirmar separadamente, verificar saldo/no duplicación, Factiliza o
manual; Admin abre editor de usuario ficticio y cancela. Comprobar leyenda/último
médico en desktop y móvil. Registrar IDs; no borrar dinero productivo de prueba
sin procedimiento de Caja aprobado. El smoke con dinero requiere autorización
financiera y nunca se ejecutó durante este trabajo.

GO del parche depende de tests/QA final. Activación productiva Comercial:
**NO-GO hasta verificar SHA/schema, payment.submit, caja/series, backups y catálogo**.
Factiliza puede operar con limitación manual si está indisponible y CEO lo acepta.
Ningún código local demuestra que estos requisitos ya se cumplieron en Hostinger.
