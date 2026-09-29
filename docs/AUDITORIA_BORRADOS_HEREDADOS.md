# Auditoría de borrados heredados y política de integridad histórica de citas

Auditoría de solo lectura sobre las cinco entidades referenciadas por `appointments`. No se corrigió ninguna clave foránea. Toda afirmación proviene de código leído o de pruebas ejecutadas en el entorno aislado.

## Estado del riesgo — resumen exigible

- `ON DELETE CASCADE` heredado en las cinco claves foráneas de `appointments`: **RIESGO LATENTE**.
- `patients`, `doctors`, `services` y `additional_rates` se retiran mediante **desactivación** (`estado = INACTIVO`), nunca mediante borrado.
- `users` **no tiene baja soportada**: no hay ruta, ni método, ni columna de estado, ni `SoftDeletes`.
- **No existe ningún flujo normal de la aplicación que alcance hoy las cascadas.**
- **SQL directo o código futuro sí podrían alcanzarlas**, y en ese caso la cita histórica se destruye.
- Las cinco claves foráneas **no se modifican todavía**.
- **MVP-1B queda REQUERIDO ANTES DEL PILOTO.**

## Conclusión principal

Las cinco claves foráneas de `appointments` se crearon con `ON DELETE CASCADE`, y está verificado que eliminar cualquiera de los cinco padres destruye la cita histórica.

**Pero ese riesgo es hoy latente, no alcanzable.** Ningún flujo expuesto de la aplicación elimina físicamente ninguna de las cinco entidades: los cuatro endpoints que se llaman `delete` ejecutan en realidad `update(['estado' => 'INACTIVO'])`, y para `users` no existe ningún flujo de baja.

Esto reduce la urgencia respecto de la evaluación inicial, pero no elimina el problema: la protección depende hoy de que nadie escriba un `delete()` y de que nadie ejecute SQL directo, no de una garantía del esquema.

## 1. Matriz de borrados

| Entidad | Ruta | Controlador | Protección | Mecanismo | SoftDeletes | Efecto en `appointments` | UI | Clasificación |
|---|---|---|---|---|---|---|---|---|
| `users` | ninguna | `UserController` sin método de borrado | `auth` | no existe | no | no aplica | no | **NO SE ENCONTRÓ BORRADO** |
| `patients` | `POST /admissionist/patient/delete` | `admissionist\patient\PatientController@delete` | `auth` + `role:ADMISION` | `update(estado = INACTIVO)` | no | ninguno, la cita permanece | sí, `public/js/admissionist/patient/patient.js:243` | **DESACTIVACIÓN** |
| `doctors` | `POST /master/admin/doctor/delete` | `admin\master\doctor\DoctorController@delete` | `auth` + `role:ADMINISTRADOR` | `update(estado = INACTIVO)` | no | ninguno, la cita permanece | sí, `public/js/admin/master/doctor/doctor.js:186` | **DESACTIVACIÓN** |
| `services` | `POST /master/admin/service/delete` | `admin\master\service\ServiceController@delete` | `auth` + `role:ADMINISTRADOR` | `update(estado = INACTIVO)` | no | ninguno, la cita permanece | sí, `public/js/admin/master/service/service.js:187` | **DESACTIVACIÓN** |
| `additional_rates` | `POST /master/admin/additional-rate/delete` | `admin\master\additionalRate\AdditionalRateController@delete` | `auth` + `role:ADMINISTRADOR` | `update(estado = INACTIVO)` | no | ninguno, la cita permanece | sí, `public/js/admin/master/additional-rate/additional-rate.js:185` | **DESACTIVACIÓN** |

Los grupos de middleware provienen de `routes/administrador.php` línea 20 (`['auth','role:ADMINISTRADOR']`) y `routes/admision.php` línea 22 (`['auth','role:ADMISION']`).

## 2. Verificaciones transversales

- **`SoftDeletes` no se usa en ningún modelo del proyecto.** El trait no aparece en `app/`.
- **Solo dos `->delete()` reales existen en `app/`**: `RoleController@destroy` (roles de Spatie) y `Livewire\CashMovements` (movimientos de caja). Ninguno afecta a las cinco entidades.
- No hay borrados en seeders ni en `app/Console`.
- No existe ningún código que elimine `appointments`.
- `users` **no tiene columna de estado ni borrado lógico**, así que hoy no existe forma soportada de retirar una cuenta.
- `patients`, `doctors`, `services` y `additional_rates` sí tienen `estado`.

## 3. Riesgo concreto por entidad

| Entidad | Riesgo real hoy | Severidad |
|---|---|---|
| `users` | sin flujo de baja; cualquier baja futura, comando o SQL directo destruiría el historial del creador. Además no hay forma de retirar una cuenta, lo que presiona a inventar una baja física | **ALTA** (latente, y bloquea un requisito funcional) |
| `patients` | protegido por desactivación; el CASCADE solo se alcanza por SQL directo | MEDIA |
| `doctors` | igual que pacientes | MEDIA |
| `services` | igual | MEDIA |
| `additional_rates` | igual | MEDIA |

## 4. Política de integridad recomendada (TO-BE, no aplicada)

Hay que separar dos cosas, y en este caso se necesitan ambas.

### Corrección de FK

| Relación | Actual | Propuesto | Motivo |
|---|---|---|---|
| `appointments.user_id` | CASCADE, NOT NULL | **RESTRICT**, mantener NOT NULL | el creador es un hecho histórico que no debe poder quedar vacío. RESTRICT no exige tocar la nulabilidad, que es el cambio más invasivo para el código heredado |
| `appointments.patient_id` | CASCADE | **RESTRICT** | una cita sin paciente no tiene sentido clínico ni contable |
| `appointments.doctor_id` | CASCADE | **RESTRICT** | contexto histórico de quién atendió |
| `appointments.service_id` | CASCADE | **RESTRICT** | contexto histórico y base del precio |
| `appointments.additional_rate_id` | CASCADE | **RESTRICT** | afecta al importe; perderlo altera la trazabilidad financiera |

`RESTRICT` en las cinco es preferible a `SET NULL` porque `SET NULL` exigiría volver nullable a columnas que hoy son obligatorias, cambiando contratos que el código heredado asume, y porque convertir en nulo el paciente o el servicio de una cita pasada destruye información igual que borrarla, solo más silenciosamente.

La asimetría con MVP-1A es deliberada: `responsible_user_id` es un atributo de gestión reasignable, mientras que estos cinco son contexto histórico inmutable.

### Cambio del flujo de borrado

La corrección de FK sola convertiría cualquier intento de borrado en un error 500. Hace falta, en paralelo:

1. dar a `users` una baja soportada: columna de estado o `SoftDeletes`, más exclusión del login y de los selectores;
2. renombrar los endpoints `delete` a lo que realmente hacen, o al menos su intención en la UI, para que nadie los "corrija" luego convirtiéndolos en borrados reales;
3. definir el comportamiento esperado cuando un padre tenga citas: mensaje explícito de que debe desactivarse, no un error genérico;
4. cubrir con pruebas que el borrado quede bloqueado en lugar de cascada.

## 5. MVP-1B — Integridad histórica y ciclo de vida de entidades vinculadas a citas

**Estado: requerido ANTES DEL PILOTO. No implementado.**

**Necesario: sí.** No por riesgo alcanzable hoy, sino porque la única protección actual es una convención no escrita, y porque la ausencia de baja de usuario bloquea el uso de `responsible_user_id` en producción.

Debe incluir:

- **baja o desactivación soportada de usuarios**, con exclusión de la autenticación y de los selectores;
- **revisión de las cinco claves foráneas heredadas** de `appointments`;
- **`RESTRICT` como política propuesta** para las cinco, según la sección 4;
- **manejo explícito de los intentos de borrado**, de forma que un padre con citas informe que debe desactivarse en lugar de fallar de manera genérica;
- **pruebas en MySQL**, ya que las acciones referenciales no son verificables en SQLite.

Requiere además, como paso previo, análisis en producción de si existen borrados reales de estas cinco entidades y del estado actual de integridad de los datos.

Fuera de alcance de MVP-1B: cambiar la nulabilidad de columnas heredadas y reescribir los CRUD.

## 6. Pruebas de caracterización añadidas

`tests/Feature/Legacy/ParentDeletionCharacterizationTest.php`, 10 pruebas ejecutables íntegramente en el entorno aislado:

- los cuatro endpoints expuestos solo desactivan y la cita asociada permanece;
- no existe ninguna ruta que elimine un usuario, y `users` no tiene columna de estado;
- eliminar físicamente cualquiera de los cinco padres destruye la cita histórica.

Estas pruebas documentan el comportamiento **actual**, no el deseado, y deben actualizarse cuando MVP-1B corrija las claves foráneas. Se mantienen fuera del commit de MVP-1A a propósito.

## 7. Hallazgo adyacente — CORREGIDO

`DoctorController@delete` devolvía `code => 1` también en su rama de fallo, así que una desactivación fallida se reportaba a la UI como exitosa. Se corrigió a `code => 0`, sin refactorizar el resto del controlador, y quedó cubierto por `tests/Feature/Admin/DoctorDeactivationTest.php`.

### Inconsistencia relacionada detectada al corregir — REPORTADA, NO CORREGIDA

Los diez métodos `delete` de este estilo hacen `Model::find($request->id)` y usan el resultado sin comprobar que exista. Con un `id` inexistente o ausente el método falla de forma no controlada en lugar de responder un fallo legible: no hay `findOrFail`, ni validación del parámetro, ni respuesta de error.

No se corrigió porque afecta a diez controladores y ampliar el alcance aquí contradiría la política de mejora adyacente. Debe resolverse antes del piloto, preferiblemente junto al endurecimiento de los flujos de borrado de MVP-1B, que ya tocará estos mismos métodos.
