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
- **PENDIENTE DE PRODUCCIÓN:** no se confirmó qué commit/flujo está desplegado, qué conexión utiliza ni qué controles externos existen.
- **RIESGO POTENCIAL PRODUCTIVO:** las rutas mutantes podrían afectar al ERP si el código está desplegado, accesible y conectado a su base; no se afirma exposición real sin evidencia productiva.

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

El flujo efectivamente desplegado en producción está **PENDIENTE DE COMPROBAR**.

## 3. Conexiones de base de datos

`config/database.php` define:

- conexión por defecto configurable con `DB_*`;
- conexión `other_system` mediante variables `OTHER_SYSTEM_DB_*` para acceso directo al ERP.

El `.env` local del llamador no contiene claves `OTHER_SYSTEM_DB_*`. No se leyeron valores de ninguna credencial. La conexión podría estar configurada externamente, en cache de configuración o no estar operativa; queda pendiente de producción.

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

Por ello, `visorTemporal` debe tratarse como flujo alternativo aislado hasta obtener evidencia productiva.

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

La explotabilidad productiva exacta está **PENDIENTE** de confirmar porque no se verificó:

- que el commit auditado esté desplegado;
- que esas rutas sean accesibles públicamente;
- que `other_system` esté configurado;
- que un proxy/firewall imponga controles externos.

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

- commit desplegado del llamador;
- URL y alcance de red;
- ruta legacy, temporal o ambas realmente usadas;
- base por defecto y conexión `other_system` configuradas sí/no;
- estructura de ambas tablas `appointments`;
- usuarios/roles operativos agregados;
- logs sanitizados de polling y escritura;
- proxy, autenticación o restricciones externas;
- flujo real en pantallas de admisión, médico y visor.

## 12. Fase 5A — dependencia del MVP de agendamiento

### 12.1 Regla de compatibilidad

El MVP de agendamiento no puede asumir que una cita termina al confirmarse. El llamador potencialmente consume y modifica la misma fila `appointments` para representar llegada, espera, llamado, atención y finalización. Hasta comprobar producción, deben preservarse o proyectarse:

- `appointments.id`;
- `patient_id`, `doctor_id`, `service_id`;
- `fecha_cita`, `duracion_cita`, `turno_cita`;
- `estado_cita`;
- `updated_at` cuando se use como señal de rellamado;
- timestamps operativos si el flujo desplegado los consume.

No se deben cambiar enum, significado ni ids por el MVP sin una prueba conjunta del flujo desplegado.

### 12.2 Efecto de los nuevos tipos de agenda

- **Cita regular:** solo debe llegar como confirmada/operable conforme al contrato que se verifique.
- **Sobreagendamiento autorizado:** puede proyectarse como cita operable, pero debe conservar internamente su autorización y no alterar la semántica del cupo regular.
- **Cita adicional:** no debe aparecer como atención garantizada. **CONFIRMADO POR NEGOCIO:** ADMISION o COMERCIAL pueden gestionarla sin una aprobación manual obligatoria del profesional. **PENDIENTE DE NEGOCIO:** el evento operativo exacto que la admite a la cola —por ejemplo, llegada o admisión a espera—.
- **Pre-reserva:** no debe publicarse al llamador como cita confirmada mientras aún puede expirar.

### 12.3 Riesgo prioritario para el piloto

El código auditado del llamador contiene rutas potencialmente capaces de modificar `appointments` sin autenticación. Si están desplegadas y conectadas al ERP, pueden saltarse las nuevas autorizaciones, transiciones y auditoría del MVP. Antes de habilitar un piloto real debe comprobarse el riesgo y acordarse una mitigación autorizada; CSRF por sí solo no sustituye autenticación/autorización.

### 12.4 Condiciones previas de aceptación

Antes del piloto deben conocerse:

1. repositorio/commit efectivamente desplegado;
2. flujo legacy, temporal o ambos en uso;
3. conexión y tabla realmente modificadas;
4. estados/timestamps reales y transiciones operativas;
5. alcance de red, proxy y autenticación;
6. comportamiento esperado de una cita adicional;
7. estrategia de compatibilidad, observabilidad y retorno.

No se modificó el llamador en esta subfase.
