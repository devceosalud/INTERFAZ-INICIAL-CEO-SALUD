# MVP HCE administrativa de paciente

## Gate técnico

Clasificación **B — compatible con cambios pequeños y localizados de código**.

`patients.historia_clinica` es `varchar(255) nullable` tanto en la migration versionada como en
la MariaDB local. No tiene cast Eloquent, valor por defecto ni índice único. Los consumidores
operativos la leen como texto. No se requiere migration.

El único supuesto numérico encontrado estaba en el alta heredada de Admisión: obtenía el último
paciente por `id`, convertía su HCE a entero y sumaba uno. Ese cálculo no es compatible con el
formato nuevo y fue sustituido por la misma fuente utilizada por el módulo Pacientes y Agenda.

## Evidencia local anonimizada

Consulta agregada, solo lectura, sobre `ERPCEOSALUD`:

- 4,796 filas;
- 0 HCE nulas y 0 vacías;
- 4,793 HCE compuestas solo por dígitos;
- 3 HCE con otro formato;
- longitud no vacía entre 1 y 5 caracteres;
- 0 HCE con el formato prefijado propuesto;
- un valor HCE duplicado en dos filas;
- `historia_clinica_nueva` no tiene valores poblados.

No se consultaron ni documentaron nombres, documentos o valores HCE reales.

## Fuente de verdad

`PatientClinicalHistoryNumber` asigna la HCE únicamente a un modelo `Patient` todavía no
persistido. Nunca modifica un paciente existente y no hace backfill de una fila heredada nula.

Mapping aprobado para esta fase:

| Tipo real del sistema | Código |
|---|---:|
| DNI | 01 |
| CARNET EXTRANJERIA | 02 |
| PASAPORTE | 03 |
| PTP | 04 |
| TAM | 05 |
| SALVOCONDUCTO | 06 |

El número se recorta únicamente en sus extremos. Se conservan letras, guiones y espacios
internos válidos. La HCE resultante se mantiene como string.

## Tipos pendientes

- `RUC` existe en la UI y en datos heredados, pero no tiene código aprobado en el mapping
  suministrado. Puede seguir registrándose y queda sin HCE hasta una decisión de negocio.
- `SIN DOCUMENTOS` continúa bloqueado en las superficies operativas. No se inventa correlativo,
  timestamp, UUID visible ni `99-NULL`.
- La BD heredada contiene además `Cédula Diplomática de identidad` y
  `DOC.TRIB.NO.DOM.SIN.RUC`; no forman parte del catálogo actual de altas y no se les asignó un
  código retrospectivo.

## Concurrencia

`numero_identidad` tiene UNIQUE global. Para los tipos mapeados la HCE es determinista a partir
del mismo documento, por lo que el constraint existente impide dos altas concurrentes con el
mismo número. `historia_clinica` no es UNIQUE: la base heredada ya contiene un duplicado y no se
crea una migration en esta tarea.

## UI

El frontend ya no calcula ni muestra una HCE propuesta como si fuera definitiva. Durante una
alta compatible muestra “Se asignará al guardar”. Después del POST utiliza exclusivamente
`patient.historia_clinica` retornada por backend para el listado, la ficha y el contexto de
Agenda.
