# Corrección incremental de los dos P1 del PR58

Base exacta auditada: `72363c3d0bedb0fc801b36a54db3f40951f5bc90`. Rama: `codex/medico-vinculado-fase-a-20261021`. El nuevo SHA y el diff binario se entregan después del commit; los resultados de GitHub Actions se incorporan a la descripción del PR58, sin cambiar el SHA para versionar su propio CI. Los documentos anteriores conservan las evidencias de la entrega original y no representan los resultados de esta corrección.

## P1. Finalizar con indicación pendiente

Antes, MedicalCareService::perform solo exigía EN_ATENCION para finish; MedicalBoardService::detail lo ofrecía aunque AppointmentReevaluationService::pending fuera verdadero. Las nuevas pruebas reprodujeron la oferta indebida y la finalización200 después de retirar listo manual.

Ahora, dentro de la transacción y después de bloquear la cita, finish carga con lecturas actuales bloqueadas la última indicación médica, la última marca manual y el operationalReview. Reutiliza exactamente AppointmentReevaluationService::pending/ready, sin un nuevo estado ni otra regla paralela de reevaluación. Si sigue pendiente, devuelve422 con explicación, revierte la operación y no modifica cita, procedimientos, marcas, historial, dinero ni timestamps.

MedicalBoardService no ofrece finish durante pendiente. medical-board.js conserva el renderizado de las acciones autorizadas por backend y explica cómo resolver la condición: listo conforme a reglas existentes o retirada justificada de la indicación. No elimina atributos disabled para habilitar una transición prohibida.

Se permite finalizar sin indicación pendiente, después de listo automático, listo manual válido o retirada médica con motivo. Si Admisión retira listo o reabre un procedimiento antes de finalizar, vuelve el bloqueo; una revisión obsoleta produce409 y una petición actual todavía pendiente produce422. No se registra salida física ni otra cita/cobro.

## P1. Permisos heredados de roles combinados

ClinicalAccess::actor conserva la revisión de permisos directos y ahora consulta permisos incompatibles en **todos** los roles asignados, eliminando el filtro que solo inspeccionaba MEDICO. Se valida al entrar al board/feed/detalle y en los escritores clínicos, incluida la revalidación transaccional existente. Los perfiles combinados se rechazan también cuando el rol adicional tiene vacía su lista de permisos: las rutas existentes del ERP conceden accesos por el nombre del rol. El acceso clínico requiere un perfil exclusivo MEDICO, además de sus capacidades válidas. MEDICO+ADMINISTRADOR y MEDICO+ADMISION se prueban con y sin permisos explícitos; los permisos directos incompatibles también producen403. No se cambian, eliminan ni sincronizan automáticamente roles, pivotes o permisos de esas cuentas para sortear la restricción.

El editor de usuarios muestra junto a MEDICO los roles actuales que reemplazaría la asignación explícita y recomienda evaluar una cuenta clínica personal separada. Consultar la pantalla no persiste cambios. Se conserva el contrato anterior de asignar un rol mediante el botón del editor: no se introducen nuevas eliminaciones automáticas ni se modifica UserController.

## Archivos de aplicación: necesidad de cada intervención

| Archivo / función | Modificación |
| --- | --- |
| app/Services/Scheduling/MedicalCareService.php ::perform |Lecturas actuales de la reevaluación bajo bloqueo y rechazo422 antes de guardar finish |
| app/Services/Scheduling/MedicalBoardService.php ::detail |Excluye finish si la proyección existente indica pendiente |
| app/Support/Scheduling/ClinicalAccess.php ::actor |Revisa capacidades heredadas de todos los roles; conserva las revisiones directas y el bloqueo transaccional anterior |
| public/js/scheduling/medical-board.js ::render |Explica la restricción y mantiene acciones ofrecidas por backend |
| resources/views/admin/user/edit.blade.php |Advertencia legible sobre reemplazo de roles, sin guardar al consultar |

## Pruebas añadidas, conservando todas las anteriores

- VerifiedMedicalCareTest: dos casos de rechazo pendiente (lista vacía y realización parcial), conservación íntegra de datos/ledger, listo manual retirado/restablecido, procedimiento completado reabierto y cinco variantes de perfiles incompatibles (roles con/sin permisos y permiso directo). Comprueba board/feed/detalle, las cuatro acciones médicas, selección de procedimientos y ClinicalAccess::lock, sin alterar roles/permisos/historial. Los casos existentes de finalización después de listo automático y retirada justificada siguen pasando.
- MedicalAccessCompatibilityTest: advertencia antes de reemplazar ADMISION por MEDICO, sin persistencia al consultar.
- medical-actions.test.js: ausencia de finish y explicación durante pendiente; vuelve la acción cuando el backend reporta listo, sin solicitudes de escritura al renderizar.
- browser-verified-medical-care.cjs: conserva sus once escenarios y agrega la advertencia del editor y el intento directo HTTP422 de finish pendiente, con comparación antes/después de cita, revisión, procedimientos e historial. Se completa posteriormente la misma atención tras procedimientos/listo.

Son10 casos PHP nuevos y1 JavaScript. La selección específica final SQLite pasó36 pruebas/375 aserciones. La selección MariaDB intermedia que incluye las seis carreras anteriores pasó40/406; la regresión completa final incluye además las dos variantes de roles vacíos y se informa por separado abajo. La suite JavaScript completa pasó354 pruebas y la de navegador13 escenarios, sin errores de página. Los registros y las dos capturas nuevas están en evidencias/p1-20261021/. Los resultados finales completos se detallan al final de este documento y en sus XML.

## Diagnóstico de concurrencia y preservación

Una ampliación tentativa de la consulta de bloqueo de roles provocó cuatro fallos1020 en las pruebas MariaDB existentes de revocación/reasignación y finalizaciones simultáneas. Se diagnosticó con un worker exclusivamente privado de QA (restaurado después, sin cambios en el worker versionado). Se retiró esa ampliación y se conservó el bloqueo por clave primaria y orden anteriores; la inspección de todas las capacidades heredadas sigue vigente. Las seis carreras existentes se repitieron sin relajar ni retirar sus aserciones. No se publicará un resultado obtenido ocultando esas regresiones.

No se tocaron reservas, disponibilidad, slots, horarios, doctors.id, appointments.doctor_id, modelos de citas/médicos, flujos ordinarios de Caja/Comercial/Administración, catálogo, migraciones, dependencias ni workflows. Puntos4/5 pendientes. Las fuentes probadas se verifican con821 hashes y la comparación JUnit conserva los1236 casos de la base auditada, además de los nuevos.

## Publicación, instalación y rollback

El responsable confirmó Hostinger con implementación automática desactivada el21/10/2026 a las22:27 Lima; corrigió expresamente el año de su primera respuesta. Esta tarea no entra a Hostinger. Se verifican main1f840e9, base del PR57a8bd76d y SHA remoto del PR58 antes del push normal, sin force. Solo se actualiza la rama del PR58; ambos PR continúan Draft sin merge ni auto-merge.

No hay migraciones nuevas ni cambios en las dos migraciones propuestas anteriormente. Este incremento es de código y documentación; no autoriza la instalación de la release completa ni sus migraciones anteriores. El preflight, respaldo y ensayo de staging del documento DESPLIEGUE_Y_ROLLBACK.md siguen vigentes.

La reversión de este commit no requiere modificar esquema ni eliminar auditoría. Volver a72363c3 reintroduciría ambos hallazgos; preferir corrección hacia adelante o coordinar suspensión del acceso clínico durante una reversión excepcional aprobada. No eliminar procedimientos/marcas para finalizar ni retirar controles para sostener operación. Revisar la cuenta personal y sus permisos efectivos de forma administrativa antes de activarla; no se tocó ninguna cuenta real.

## Matriz de regresión SQLite respecto a72363c3

Se conservaron los1236 casos anteriores y pasaron los10 nuevos. Los grupos se solapan y no se suman.

| Funcionalidad | Casos antes | Casos después | Resultado |
| --- | ---: | ---: | --- |
| 1. Crear y guardar citas | 116 | 117 | SIN REGRESIÓN DETECTADA |
| 2. Editar, confirmar, cancelar y reprogramar | 61 | 61 | SIN REGRESIÓN DETECTADA |
| 3. Disponibilidad, ocupación y horarios | 148 | 148 | SIN REGRESIÓN DETECTADA |
| 4. Crear, editar y desactivar médicos | 19 | 19 | SIN REGRESIÓN DETECTADA |
| 5. Crear, editar, activar y desactivar usuarios | 40 | 41 | SIN REGRESIÓN DETECTADA |
| 6. Roles y permisos existentes | 56 | 56 | SIN REGRESIÓN DETECTADA |
| 7. Perfiles actuales y visibilidad | 64 | 74 | SIN REGRESIÓN DETECTADA |
| 8. Adelantos, descuentos, pagos y Caja | 100 | 100 | SIN REGRESIÓN DETECTADA |
| 9. Llegada, procedimientos y reevaluación | 56 | 65 | SIN REGRESIÓN DETECTADA |
| 10. Históricos y usuarios sin vínculo | 127 | 128 | SIN REGRESIÓN DETECTADA |

## Resultados finales sobre las fuentes congeladas

| Verificación | Resultado |
| --- | --- |
| Específicas SQLite |36 pruebas,375 aserciones PASS |
| SQLite completo |1246 pruebas,11127 aserciones PASS |
| MariaDB11.8.9 completo |1267 pruebas,11327 aserciones PASS, incluidas las seis carreras originales |
| JavaScript completo |354 PASS,0 fallos,0 omitidas |
| JavaScript específico médico |10 PASS |
| Chrome/Playwright |13 escenarios PASS,0 errores de página, escritorio y móvil |
| Lint PHP/Node de los cambios |8 archivos PASS; Blade ejercitado por pruebas HTTP/navegador |
| Comparación de identidades JUnit |SQLite conserva1236/1236; MariaDB conserva1257/1257; se agregan10 casos en cada motor |

Duraciones finales completas: SQLite03:43.783; MariaDB05:04.644. No se retiró ni relajó ninguna prueba anterior. No hay casos omitidos ni fallos en las suites finales. Los informes P1-sqlite/P1-mariadb y P1-regresion.json permiten verificarlo. Las pruebas intermedias y de reproducción de fallos se identifican como tales; no se presentan como la aceptación final.

PHP8.3.35/PHPUnit9.6.34, MariaDB11.8.9 privada en loopback33331, Node26.3.0, Chrome155.0.8059.79. Se mantienen los dos adaptadores privados de aislamiento ya documentados (puerto/schema/identidad de QA); no se copiaron cambios de esos adaptadores al repositorio. Los821 hashes de fuente coinciden con la ejecución final, salvo esos dos adaptadores expresamente identificados. El worker de diagnóstico privado fue restaurado antes de las suites finales.

Todos los datos son ficticios. No se exportan bases, .env, sesiones, credenciales temporales ni archivos privados. Las dos capturas añadidas muestran la advertencia antes de reemplazar ADMISION y la pantalla pendiente sin Finalizar. Los JSON de navegador comprueban el rechazo422 sin cambios de datos, y la finalización posterior legítima de la misma cita conserva un solo pago real y salida nula.

El CI remoto de esta corrección se revisará después del push y quedará enlazado en el PR58, separado de estas pruebas locales. Publicar para revisión no implica aceptación de merge ni instalación productiva.
