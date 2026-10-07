# Auditoría AS-IS del llamador de pacientes

## 1. Fuente auditada

Repositorio local inspeccionado en modo de solo lectura:

- repositorio: `devceosalud/LLAMADOR-PACIENTE-CEO`;
- rama local: `main`;
- commit: `80b2fd417605da3ca869450b8ae52f996045593d`;
- último mensaje: `LLAMADOR`;
- estado de trabajo: limpio;
- framework: Laravel 9 (`composer.lock` registra `9.x-dev`);
- PHP requerido: `^8.0`.

No se modificó el repositorio ni se conectó a sus bases de datos.

- **CONFIRMADO EN CÓDIGO:** existen flujos y rutas locales capaces de leer o modificar estados de citas según el código auditado.
- **CONFIRMADO EN PRODUCCIÓN:** el login operativo de ADMISION redirige al flujo `visorTemporal` en `/admision/temporal/gestion-paciente` y muestra el panel temporal de gestión.
- **RIESGO POTENCIAL PRODUCTIVO:** el código desplegado conserva rutas legacy mutantes, pero no se confirmó su uso y la configuración productiva revisada no contiene `OTHER_SYSTEM_DB_*`; no se afirma exposición efectiva ni acceso al ERP.

## 2. Resumen arquitectónico

El repositorio contiene dos flujos distintos:

```text
FLUJO LEGACY
Browser -> rutas web -> controladores DB::connection('other_system')
        -> tablas del ERP: appointments + patients + services + doctors

FLUJO visorTemporal
Browser -> rutas web -> controladores Eloquent
        -> base por defecto del llamador -> tabla appointments propia
```

No se encontró un proceso que sincronice ambos flujos. Tampoco se encontraron jobs, comandos programados, websockets o una API ERP↔llamador implementada.

El flujo productivo primario de ADMISION queda **CONFIRMADO: `visorTemporal`**. La existencia del legacy en el mismo código no demuestra que siga siendo usado por personas o procesos externos.

## 3. Conexiones de base de datos

`config/database.php` define:

- conexión por defecto configurable con `DB_*`;
- conexión `other_system` mediante variables `OTHER_SYSTEM_DB_*` para acceso directo al ERP.

**CONFIRMADO EN PRODUCCIÓN:** el llamador tiene una base por defecto propia; el `.env` revisado no contiene variables `OTHER_SYSTEM_DB_*` y `bootstrap/cache` no contiene `config.php` ni cache de rutas, solo `packages.php` y `services.php`. No se registraron nombres, usuarios ni credenciales.

En consecuencia, el flujo temporal confirmado usa la base por defecto del llamador y no depende de `other_system`. La conexión legacy existe como definición de código, pero está **NO CONFIGURADA** en la evidencia productiva actual.

## 4. Flujo legacy: acceso directo al ERP

### 4.1 Rutas

En `routes/web.php`:

| Método/ruta | Controlador | Efecto |
|---|---|---|
| GET `/admision/gestion-paciente` | `AdmisionController@index` | Lee citas y PII desde ERP |
| PUT `/admision/gestion-paciente/estado` | `AdmisionController@update` | Actualiza directamente `appointments.estado_cita` |
| POST `/llamar-paciente` | `AdmisionController@llamar` | Escribe `LLAMANDO` y modifica `updated_at` |
| GET `/home/llamador` | `VisorController@index` | Renderiza visor |
| GET `/all-appointment/visor` | `VisorController@allAppointment` | Devuelve HTML y datos del paciente llamado |

Ni `AdmisionController` ni `VisorController` aplican middleware `auth`. Las rutas tampoco están dentro de un grupo autenticado.

### 4.2 Tablas y campos consumidos

| Tabla ERP | Uso |
|---|---|
| `appointments` | id, patient_id, service_id, doctor_id, duración, estado, turno, fecha y `updated_at` |
| `patients` | nombre y apellidos para pantalla/voz |
| `services` | nombre del servicio/especialidad anunciado |
| `doctors` | nombre del profesional anunciado |

Escrituras directas:

- `update()` acepta `appointment_id` y `estado_cita` de la solicitud sin catálogo de estados ni autorización;
- `llamar()` asigna `LLAMANDO` y `updated_at = now() + 2 segundos`;
- el `updated_at` artificial funciona como señal para repetir el anuncio.

Este flujo no escribe `hora_llamado`; usa `updated_at` como versión del último llamado.

### 4.3 Mecanismo del visor

`public/js/visor/visor.js` consulta `/all-appointment/visor` cada tres segundos mediante polling HTTP. Cuando cambia el id o `updated_at`, usa `SpeechSynthesisUtterance` del navegador para anunciar nombre del paciente, servicio y médico.

No hay websocket ni broadcasting activo. El código de Laravel Echo/Pusher está comentado.

## 5. Flujo `visorTemporal`: base local

### 5.1 Modelo y tabla

`App\Models\Appointment` usa la conexión por defecto y una tabla local `appointments` cuya migration incluye:

- `user_id`;
- nombre y apellidos del paciente;
- nombre del médico y especialidad como texto;
- fecha/hora de cita;
- `hora_llegada`, `hora_llamado`, `hora_atencion`, `hora_atendido`;
- motivo, observaciones, turno y timestamps;
- enum con `PROGRAMADO`, `CONFIRMADO`, `PACIENTE_LLEGO`, `EN_ESPERA`, `LLAMANDO`, `EN_ATENCION`, `ATENDIDO`, `REEVALUACION`, `CANCELADO`, `NO_ASISTIO`.

Esta tabla no contiene ids de paciente, médico, servicio o consultorio del ERP.

### 5.2 Rutas y autenticación

| Método/ruta | Protección observable | Efecto |
|---|---|---|
| GET `/admision/temporal/gestion-paciente` | `auth` en controlador | Lista y opera citas locales |
| POST `/llamar-temporal-paciente` | `auth` en controlador | Cambia estado y timestamp correspondiente |
| GET `/doctor/temporal/gestion-paciente` | `auth` en controlador | Panel médico local |
| GET `/visor/temporal/paciente` | Sin `auth` | Visor público |
| GET `/all-appointment/visor/temp` | Sin `auth` | JSON/HTML para polling |

El login redirige según `users.name`:

- `admision` y `recepcion` → panel temporal de admisión;
- cualquier otro nombre → panel médico.

Las vistas también muestran botones según `auth()->user()->name === 'admision'`. No se encontraron roles, permisos, policies ni validación de cargo/profesión.

### 5.3 Estados y timestamps

`AdmisionTemporalController@llamar` permite solo:

| Estado | Campo temporal actualizado |
|---|---|
| `PACIENTE_LLEGO` | `hora_llegada` |
| `LLAMANDO` | `hora_llamado` y `updated_at` |
| `EN_ATENCION` | `hora_atencion` |
| `ATENDIDO` | `hora_atendido` |
| `REEVALUACION` | `updated_at` |

El visor temporal también hace polling cada tres segundos. El contador visual de admisión se actualiza cada segundo en JavaScript.

### 5.4 Sincronización no encontrada

No se encontró código que:

- copie citas del ERP a la tabla temporal;
- actualice el ERP desde la tabla temporal;
- resuelva conflictos entre ambos estados;
- reconcilie ids;
- publique eventos o callbacks;
- elimine o archive citas locales.

**CONFIRMADO EN PRODUCCIÓN:** este flujo aislado es el flujo normal de ADMISION. La pantalla observada estaba sin citas; esto puede corresponder simplemente a que no existían registros temporales para la fecha y filtros aplicados y no demuestra un error funcional.

El botón **Traer Datos** de la vista es un enlace GET a la ruta nombrada `admision.temporal.index`. Solo recarga el índice temporal; no importa datos desde el ERP. No se encontró ni se observó sincronización ERP → llamador asociada a ese botón.

## 6. Autenticación, autorización y exposición

### 6.1 Sesión

El login usa `auth()->attempt()` con email/password. No se observó ruta de logout en `routes/web.php`.

### 6.2 API y CORS

- `routes/api.php` solo contiene `/api/user` protegido con Sanctum;
- `config/cors.php` permite cualquier origen/método/header para `api/*` y `sanctum/csrf-cookie`;
- los flujos reales del llamador usan rutas web same-origin, no esa API.

La amplitud CORS no crea por sí sola acceso a rutas web, pero deberá restringirse si se desarrolla una API real.

### 6.3 Datos en pantalla y consola

Los visores devuelven nombres/apellidos y los anuncian por voz. Los JavaScript contienen `console.log` de respuestas completas, incluidas estructuras del paciente. Debe validarse el alcance de red, propósito del visor y política de privacidad.

## 7. Riesgo crítico potencial

### RIESGO CRÍTICO PRODUCTIVO POTENCIAL — escritura directa sin autenticación

El código define rutas web no autenticadas capaces de modificar `appointments` en la conexión directa del ERP:

- PUT `/admision/gestion-paciente/estado`;
- POST `/llamar-paciente`.

CSRF evita ciertos ataques desde terceros, pero no sustituye autenticación ni autorización: un cliente puede obtener su propia sesión/token y enviar la solicitud. `update()` además acepta un estado arbitrario.

El mismo código productivo conserva estas rutas, pero el flujo normal confirmado es temporal y `other_system` no está configurada según el `.env` y caches revisados. Permanece pendiente verificar si algún usuario o proceso externo consume las rutas legacy y qué controles externos existen.

Clasificación: **DEUDA/RIESGO LEGACY PENDIENTE DE RETIRO O CONTENCIÓN**. No se afirma que las rutas sean explotables productivamente ni que actualmente alcancen la BD del ERP.

No se intervino el llamador ni producción.

## 8. Otros riesgos confirmados en código

1. Autorización basada en el nombre del usuario, no en roles/capacidades.
2. Actualización directa de la tabla central del ERP desde otra aplicación.
3. Dos fuentes potenciales de estado sin reconciliación.
4. Estados del llamador no coinciden con el enum versionado del ERP.
5. `updated_at + 2 segundos` usado como señal funcional.
6. Sin validación del id/estado en el flujo legacy.
7. Visores públicos devuelven PII operativa.
8. Logging de navegador con datos del paciente.
9. No existe `consultorio`; se anuncia servicio/especialidad y médico.
10. No hay tests funcionales del flujo; solo tests `Example` de plantilla.
11. No hay colas, scheduler activo, reintentos ni auditoría de cambios.

## 9. Contrato implícito ERP ↔ llamador

### Flujo directo

```text
ERP appointments
  |-- patient_id -> patients.nombre/apellidos
  |-- service_id -> services.nombre
  |-- doctor_id  -> doctors.nombre
  |-- estado_cita
  |-- fecha_cita, duracion_cita, turno_cita
  `-- updated_at (señal de rellamado)

LLAMADOR
  |-- SELECT con joins y filtros de estado/fecha
  |-- UPDATE estado_cita
  `-- UPDATE updated_at para forzar nuevo anuncio
```

### Dependencias de `appointments`

| Dependencia | Flujo legacy | Flujo temporal |
|---|---|---|
| `id` | Sí | Sí, pero id local |
| `patient_id` | Sí | No |
| `doctor_id` | Sí | No |
| `service_id` | Sí | No |
| `fecha_cita` | Sí | Sí |
| `duracion_cita` | Sí | No en tabla local |
| `turno_cita` | Sí | Sí |
| `estado_cita` | Lee/escribe | Lee/escribe |
| `updated_at` | Señal de rellamado | Señal de último llamado |
| `hora_llegada` | No | Sí |
| `hora_llamado` | No | Sí |
| `hora_atencion` | No | Sí |
| `hora_atendido` | No | Sí |
| consultorio | No existe | No existe |

## 10. Consecuencia para el TO-BE

No se puede dividir o retirar `appointments` sin compatibilidad temporal. Antes deberá:

1. confirmarse qué flujo está desplegado y qué base usa;
2. protegerse el acceso productivo mediante una fase autorizada;
3. inventariarse el enum y timestamps reales;
4. definir posteriormente un contrato ERP–llamador;
5. eliminar la escritura directa compartida antes de convertir el ERP en fuente canónica exclusiva;
6. conservar una proyección compatible durante el corte si el visor no cambia simultáneamente.

No se diseña aún el contrato nuevo.

## 11. Evidencia productiva pendiente

Ya están confirmados el flujo normal de ADMISION, su base por defecto independiente, la ausencia de configuración efectiva `other_system` y la función no importadora de **Traer Datos**. Continúa pendiente:

- confirmar si algún usuario, enlace, integración o proceso externo usa rutas legacy;
- URL y alcance de red de visores/rutas legacy;
- estructura DDL de la tabla temporal `appointments` en vivo, sin leer filas;
- usuarios/roles operativos agregados distintos de la sesión ADMISION observada;
- logs sanitizados de uso por ruta, método y estado;
- proxy, autenticación o restricciones externas;
- comportamiento productivo del panel médico y del visor público;
- metadatos completos de deployment si se requieren para trazabilidad de infraestructura.

## 12. Fase 5A — dependencia del MVP de agendamiento

### 12.1 Regla de compatibilidad

El flujo productivo primario no comparte la fila `appointments` del ERP: opera sobre una tabla temporal propia, con datos desnormalizados y cuatro horas operativas. Tampoco existe sincronización confirmada entre ambos sistemas.

El MVP **no debe intentar integrar mediante escritura directa compartida sobre `appointments`**. Deberá diseñarse posteriormente un contrato explícito ERP ↔ LLAMADOR que publique solo citas elegibles, mantenga correlación estable y devuelva transiciones operativas de manera autenticada, autorizada, idempotente y auditable. Todavía no se diseña ese contrato.

### 12.2 Efecto de los nuevos tipos de agenda

- **Cita regular:** solo debe llegar como confirmada/operable conforme al contrato que se verifique.
- **Sobreagendamiento autorizado:** puede proyectarse como cita operable, pero debe conservar internamente su autorización y no alterar la semántica del cupo regular.
- **Cita adicional:** no debe aparecer como atención garantizada. **CONFIRMADO POR NEGOCIO:** ADMISION o COMERCIAL pueden gestionarla sin una aprobación manual obligatoria del profesional. **PENDIENTE DE NEGOCIO:** el evento operativo exacto que la admite a la cola —por ejemplo, llegada o admisión a espera—.
- **Pre-reserva:** no debe publicarse al llamador como cita confirmada mientras aún puede expirar.

### 12.3 Riesgo prioritario para el piloto

El código productivo conserva rutas legacy sin autenticación de aplicación, pero el flujo operativo normal confirmado es temporal y `other_system` no está configurada. Antes del piloto debe comprobarse que ningún usuario/proceso dependa del legacy y contenerlo o retirarlo mediante una fase autorizada. No forma parte del contrato futuro.

### 12.4 Condiciones previas de aceptación

Antes del piloto deben resolverse:

1. contrato explícito entre ERP y llamador, sin escritura directa compartida;
2. correlación, estados/timestamps y transiciones operativas;
3. autenticación, autorización, alcance de red y privacidad del visor;
4. verificación de no uso y posterior contención/retiro del legacy;
5. comportamiento esperado de una cita adicional;
6. observabilidad, idempotencia, reconciliación y retorno.

No se modificó el llamador en esta subfase.

## 13. Verificación productiva del Gate C

### 13.1 Evidencia confirmada

Mediante una sesión real autenticada en producción se verificó:

- el usuario operativo ADMISION inicia sesión y es redirigido a `/admision/temporal/gestion-paciente`;
- la pantalla muestra **Panel de Gestión Operativa - admision**;
- el comportamiento coincide con `AuthController`, que dirige ADMISION a `admision.temporal.index`;
- `AdmisionTemporalController` exige middleware `auth`, usa `App\Models\Appointment` y la conexión por defecto del llamador;
- la tabla temporal maneja `hora_llegada`, `hora_llamado`, `hora_atencion` y `hora_atendido`;
- la base por defecto propia está configurada;
- `OTHER_SYSTEM_DB_*` no está presente en el `.env` revisado;
- no existe `bootstrap/cache/config.php` ni cache de rutas; solo se observaron `packages.php` y `services.php`;
- **Traer Datos** es un enlace GET al mismo índice temporal y no una importación desde el ERP;
- la pantalla se encontraba sin citas, hecho que no prueba un fallo y es compatible con ausencia de registros para la fecha/filtros.

No se documentaron nombres de base, usuarios, hosts, passwords ni otros secretos.

### 13.2 Estado de los dos flujos

| Flujo | Estado productivo |
|---|---|
| `visorTemporal` | **CONFIRMADO COMO FLUJO PRODUCTIVO PRIMARIO DE ADMISION**; usa `appointments` propio del llamador y no depende de `other_system`. |
| Legacy | El código y sus rutas permanecen desplegados, pero no está confirmado como flujo operativo normal ni como dependencia de usuarios/procesos externos. `other_system` está **NO CONFIGURADA** según la evidencia revisada. |

El legacy sigue siendo compatible en forma con el DDL del ERP, pero esa compatibilidad ya no debe interpretarse como contrato vigente ni como evidencia de uso.

### 13.3 Arquitectura AS-IS confirmada

```text
ERP
  appointments productivo
  estados y citas administrativas
       │
       │ NO EXISTE SINCRONIZACIÓN CONFIRMADA
       ▼
LLAMADOR
  appointments temporal propio
  llegada / llamado / atención
```

**DRIFT/SEPARACIÓN CONFIRMADA:** la tabla ERP no posee las cuatro horas del temporal y la tabla temporal desnormaliza datos sin ids de paciente, médico, servicio o consultorio del ERP. No existe código confirmado que transporte citas o estados entre ambas fuentes.

### 13.4 Legacy residual

El mismo código mantiene:

- `PUT /admision/gestion-paciente/estado`;
- `POST /llamar-paciente`;
- controladores que usan `DB::connection('other_system')`;
- `updated_at` como señal de rellamado en el visor legacy.

Clasificación: **DEUDA/RIESGO LEGACY PENDIENTE DE RETIRO O CONTENCIÓN**. Antes de retirarlo debe verificarse mediante logs sanitizados y revisión de enlaces/procesos que no tenga consumidores. No se afirma que sea explotable ni que alcance actualmente al ERP.

### 13.5 Consultorio o destino

Ningún flujo auditado modela un consultorio o ubicación de destino. El temporal productivo tampoco tiene relación con consultorio. El futuro contrato deberá incorporar el destino físico sin inferirlo desde médico o especialidad.

### 13.6 Resultado del Gate C

**GATE C = CONFIRMADO — FLUJO PRODUCTIVO PRIMARIO: `visorTemporal`.**

La evidencia es suficiente para decidir el límite arquitectónico: el MVP no debe compartir escrituras directas de `appointments` con el llamador. El TO-BE requiere un contrato explícito ERP ↔ LLAMADOR que reemplace la desconexión actual. Esta conclusión habilita planificación; no define todavía endpoints, tablas, eventos ni estrategia de despliegue.
