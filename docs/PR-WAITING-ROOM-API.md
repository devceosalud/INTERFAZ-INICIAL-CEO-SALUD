# API del llamador: revisión independiente del 28/10/2026

Rama: `codex/erp-waiting-room-review-20261028`, derivada de la candidata preservada `codex/waiting-room-api-pr-20261027` (5cdf3f40). Base actual `main`: `34f82dd159e8a716f71dcadca0a8ac9d7dec0794`. El mismo HEAD fue leído en Hostinger para el ERP; AdmissionCareService publicado coincide con la base. No se modificó la instalación pública.

Se reutiliza la API existente; no hay una segunda API. Diferencia aditiva: ruta/config/middleware/servicios propios, pruebas y documentación. No cambia servicios clínicos, modelos, estados, Agenda 2.0 ni migraciones.

El endurecimiento añade autorización explícita y caducable por instalación/sede/pantalla/jornada, procedencia synthetic/clinical en 200/304, descarte público de llamados caducados incluso en caché, validación de inicio auditado y rechazo de consultorios físicos duplicados. Conserva HMAC, anti-replay, límites, proyección mínima, homónimos omitidos, teleconsultas y reservas sin llegada excluidas.

Pruebas ejecutadas: 18/145 SQLite, 18/145 MariaDB 10.4.32 aislada; regresión conjunta API/Admisión/teleconsulta 41/509. Dos procesos PHP con el mismo nonce: [200,401]. Recorrido local HTTP con Laravel 13 y Dora real aprobado con datos ficticios. No se considera una prueba clínica productiva.

## Condiciones de publicación

1. Revisar este PR y volver a contrastar main si cambia. No fusionar todavía: el ERP tiene auto-deploy de main habilitado.
2. Obtener aprobación de despliegue, crear respaldo fresco verificable de código/configuración y backup clínico cifrado mediante el custodio autorizado (no descargar pacientes a la laptop), y verificar restauración en staging. No se ha realizado un nuevo respaldo de la base clínica en esta tarea.
3. Instalar únicamente código, con WR_INTEGRATION_ENABLED=false, privacidad disabled e instalaciones inactivas. Sin migrate, seed, composer update ni cambios de paquetes.
4. Verificar endpoint 404, operación normal de Agenda y configuración privada fuera de public_html. Runtime/cache/nonces compartidos y relojes sincronizados deben confirmarse en el alojamiento definitivo.
5. Solo después de una aprobación clínica distinta, provisionar credenciales nuevas, política y asignaciones físicas verificadas y habilitar una instalación/TV/jornada. El llamador tiene un segundo guardián privado; quitar el modo ficticio no concede permiso.

## Reversión

Revocar instalación y apagar WR_INTEGRATION_ENABLED; invalidar caché de configuración si se utiliza. Desactivar aprobación del llamador y pantallas clínicas afectadas. Restaurar artefacto y configuración respaldados si corresponde. No restaurar citas ni bases desde un snapshot antiguo, ni reenviar eventos históricos. No hay esquema nuevo que revertir. Si se requiere revertir código en Git, hacerlo mediante PR revisado sobre el main vigente, preservando cambios posteriores; nunca reset/force-push.

Contrato y campos: [WAITING-ROOM-API.md](WAITING-ROOM-API.md). El despliegue y la habilitación clínica siguen pendientes de autorización.
