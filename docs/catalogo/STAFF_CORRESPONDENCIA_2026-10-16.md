# Correspondencia de especialidades y servicios — 16/10/2026

Fuente íntegra: `STAFF Y SERVICIOS CEO SALUD 2026.xlsx`, hoja `Hoja 1`, A1:T21. Se recorrieron las 1.003 filas y 23 columnas declaradas; hay 21 filas con contenido, una hoja visible, 20 profesionales, 8 especialidades, 39 combinaciones especialidad/servicio y 28 rótulos de servicio distintos. El Excel no contiene precios. No se ha importado ni modificado.

SHA-256: `c7d3fe877b9e2f21e2a7f01a6eee0f272190669ac94222298de24485d11f8e2e`.

Contraste de BD: evidencia aportada `CEO_Salud_Auditoria_Catalogo_Precios_2026-10-16.md` y aclaración expresa de Keylinn `doctor_id=9`, `specialty_id=5`. No hay exportación completa de `doctors`: no se asignan IDs a los otros profesionales por semejanza de nombres.

## Inconsistencias y decisiones de transición

- Keylinn: Excel A10:G10 indica MFR y Consulta Médica, Teleconsulta Médica, Reevaluación. J10:K10 añade Informe y Certificado. Su asignación activa al servicio 1/Traumatología es incompatible y queda pendiente de reconciliación. El código conserva la fila y los históricos, pero impide usarla en nuevas cotizaciones sin excepción administrativa acreditada. No se recomienda aprobar una excepción para ocultar el error.
- Servicio 2/CONSULTA MFR es un candidato inactivo que debe revisarse, sin crear un duplicado ni trasladar automáticamente los precios 100/80 de la asignación equivocada. Faltan precios MFR y TF por 10 sesiones; TF no figura como paquete en este Excel.
- Informe 16 y Certificado 17 son candidatos compartidos entre Traumatología y MFR. Certificado 18 permanece inactivo; no se borra ni se consolida. Compartir especialidades no autoriza automáticamente a sus profesionales.
- Los 16 servicios activos exportados tienen especialidad 1. El Excel ubica Pie Diabético dentro de la oferta de traumatólogos; no se reasigna a otra especialidad por su nombre.
- Permanecen sin autorización de datos los precios/asignaciones faltantes, activaciones, clasificación, especialidades compartidas y enlaces 8→1/10→3. Los enlaces ya están aprobados para desarrollo, pero su aplicación requiere conteos de referencias productivas y revisión de los cambios concretos.

## Matriz completa

| Especialidad | Servicio del Excel | Celdas de evidencia | ID existente candidato | Acción pendiente |
|---|---|---|---|---|
| TRAUMATOLOGÍA | CONSULTA MÉDICA TRAUMATOLÓGICA | E2, E3, E4, E5, E6, E7, E8, E9 | 1 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | CONSULTA MÉDICA PIE DIABÉTICO | F2, F3, F4, F5, F6, F7, F8, F9 | 3 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | REEVALUACIÓN TRAUMATOLOGÍA | G2, G3, G4, G5, G6, G7, G8, G9 | 8 | ENLACE COMERCIAL APROBADO A 1; COMPROBAR REFERENCIAS ANTES DE CONFIGURAR |
| TRAUMATOLOGÍA | REEVALUACIÓN PIE DIABÉTICO | H2, H3, H4, H5, H6, H7, H8, H9 | 10 | ENLACE COMERCIAL APROBADO A 3; COMPROBAR REFERENCIAS ANTES DE CONFIGURAR |
| TRAUMATOLOGÍA | TELECONSULTA MÉDICA TRAUMATOLÓGICA | I2, I3, I4, I5, I6, I7, I8, I9 | 4 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | TELECONSULTA MÉDICA PIE DIABÉTICO | J2, J3, J4, J5, J6, J7, J8, J9 | 5 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | CONTROL POST-CIRUGÍA | K2, K3, K4, K5, K6, K7, K8, K9 | 6 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | INFILTRACIÓN ÁCIDO HIALURÓNICO | L2, L3, L4, L5, L6, L7, L8, L9 | 7 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | INFILTRACIÓN ÁCIDO HIALURÓNICO ECOGUIADA | M2, M3, M4, M5, M6, M7, M8, M9 | 9 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | INFILTRACIÓN PRP | N2, N3, N4, N5, N6, N7, N8, N9 | 11 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | INFILTRACIÓN PRP BIOCELLS | O2, O3, O4, O5, O6, O7, O8, O9 | 12 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | ARTROCENTESIS | P2, P3, P4, P5, P6, P7, P8, P9 | 13 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | COLOCACIÓN YESO/FÉRULA | Q2, Q3, Q4, Q5, Q6, Q7, Q8, Q9 | 14 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | RETIRO DE YESO | R2, R3, R4, R5, R6, R7, R8, R9 | 15 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | INFORME MÉDICO | S2, S3, S4, S5, S6, S7, S8, S9 | 16 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| TRAUMATOLOGÍA | CERTIFICADO MÉDICO | T2, T3, T4, T5, T6, T7, T8, T9 | 17 | EXISTE; REVISAR ASIGNACIÓN INDIVIDUAL Y PRECIO |
| MEDICINA FÍSICA Y REHABILITACIÓN | CONSULTA MÉDICA | E10, E11, E12, E13 | 2 | CANDIDATO HISTÓRICO INACTIVO CONSULTA MFR; VALIDAR ACTIVACIÓN Y PRECIO |
| MEDICINA FÍSICA Y REHABILITACIÓN | TELECONSULTA MÉDICA | F10, F11, F12, F13 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| MEDICINA FÍSICA Y REHABILITACIÓN | REEVALUACIÓN | G10, G11, G12, G13 | Sin confirmar | MODALIDAD: ENLAZAR A CONSULTA CANÓNICA DE SU ESPECIALIDAD; NO CREAR ELEGIBILIDAD AUTOMÁTICA |
| MEDICINA FÍSICA Y REHABILITACIÓN | INFORME MÉDICO | J10, J11, J12, J13 | 16 | COMPARTIR REGISTRO EXISTENTE CON ESPECIALIDAD; ASIGNAR EXPRESAMENTE |
| MEDICINA FÍSICA Y REHABILITACIÓN | CERTIFICADO MÉDICO | K10, K11, K12, K13 | 17 | COMPARTIR REGISTRO EXISTENTE CON ESPECIALIDAD; ASIGNAR EXPRESAMENTE |
| MEDICINA FÍSICA Y REHABILITACIÓN | TERAPIA NEURAL | H11 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| MEDICINA FÍSICA Y REHABILITACIÓN | DESGATILLAMIENTO MUSCULAR | I11 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| ENDOCRINOLOGÍA | CONSULTA MÉDICA | E14 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| ENDOCRINOLOGÍA | TELECONSULTA MÉDICA | F14 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| ENDOCRINOLOGÍA | REEVALUACIÓN | G14 | Sin confirmar | MODALIDAD: ENLAZAR A CONSULTA CANÓNICA DE SU ESPECIALIDAD; NO CREAR ELEGIBILIDAD AUTOMÁTICA |
| CARDIOLOGÍA | RIESGO CARDIOLÓGICO | E15, E16 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| NEUROCIRUGÏA | CONSULTA MÉDICA | E17 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| NEUROCIRUGÏA | TELECONSULTA MÉDICA | F17 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| NEUROCIRUGÏA | REEVALUACIÓN | G17 | Sin confirmar | MODALIDAD: ENLAZAR A CONSULTA CANÓNICA DE SU ESPECIALIDAD; NO CREAR ELEGIBILIDAD AUTOMÁTICA |
| RADIOLOGÍA | ECOGRAFÍA CADERA LACTANTE | E18 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| RADIOLOGÍA | ECODOPPLER ARTERIAL VENOSO UNILATERAL MMI | F18 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| RADIOLOGÍA | ECODOPPLER ARTERIAL VENOSO BILATERAL MMI | G18 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| RADIOLOGÍA | ECOGRAFÍA DE RODILLA | H18 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| RADIOLOGÍA | ECOGRAFÏA DE HOMBRO | I18 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| NEUMOLOGÍA | RIESGO NEUMOLÓGICO | E19, E20 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| MEDICINA INTERNA | CONSULTA MÉDICA | E21 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| MEDICINA INTERNA | TELECONSULTA MÉDICA | F21 | Sin confirmar | SIN CORRESPONDENCIA CONFIRMADA |
| MEDICINA INTERNA | REEVALUACIÓN | G21 | Sin confirmar | MODALIDAD: ENLAZAR A CONSULTA CANÓNICA DE SU ESPECIALIDAD; NO CREAR ELEGIBILIDAD AUTOMÁTICA |

## Precios comerciales declarados, configurables

| Profesional | Consulta Trauma | Reevaluación Trauma | Consulta Pie Diabético | Reevaluación Pie Diabético |
|---|---:|---:|---:|---:|
| Dr. Quiroz | 150 | 120 | 150 | 120 |
| Otros profesionales autorizados para esas prestaciones | 100 | 80 | 150 | 120 |

Estos importes provienen de la aclaración del usuario, no del Excel. No son constantes del motor ni se aplican mediante migración/seeder. No establecen precios MFR ni autorizan prestaciones incompatibles. Referidos acreditados de Quiroz conservan su política especial 250/220 y gratuidad clínica del mismo día.

## Revisión segura antes de configurar en producción

Ejecutar únicamente consultas de conteo/catálogo; no incluir pacientes ni comprobantes en el resultado. Conservar la exportación como evidencia.

```sql
SELECT ds.id AS assignment_id, ds.doctor_id, d.specialty_id AS professional_specialty,
       ds.service_id, s.specialty_id AS service_specialty, ds.estado,
       ds.precio_primera_consulta, ds.precio_reconsulta, ds.dias_reconsulta
FROM doctor_services ds
JOIN doctors d ON d.id=ds.doctor_id JOIN services s ON s.id=ds.service_id
WHERE ds.estado='ACTIVO' ORDER BY ds.doctor_id, ds.service_id, ds.id;

SELECT service_id, COUNT(1) AS references_count
FROM appointments WHERE service_id IN (8,10) GROUP BY service_id;
SELECT item_type, item_id, COUNT(1) AS references_count
FROM voucher_items WHERE item_id IN (8,10)
AND item_type IN ('servicio','service','App\\Models\\Service') GROUP BY item_type,item_id;
SELECT service_id, COUNT(1) AS references_count
FROM appointment_reevaluation_indications WHERE service_id IN (8,10) GROUP BY service_id;
```

Después de las migraciones aprobadas, `php artisan catalog:services-audit --assignments` añade conteos, enlaces y compatibilidad de todas las asignaciones. Es solo lectura. No genera grants, precios ni servicios.
