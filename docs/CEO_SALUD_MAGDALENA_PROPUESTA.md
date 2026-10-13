# Magdalena — propuesta de alta controlada, pendiente de ejecución autorizada

Evidencia del operador: sites devuelve cero filas. El código no tiene un seeder de sedes. La tabla existente permite codigo único, nombre y estado ACTIVO/INACTIVO. appointments y doctor_schedules tienen FK nullable con RESTRICT; no hay vinculación trabajador-sede. DoctorAvailabilityService incluye site_id NULL al solicitar una sede durante la transición.

1. Backup verificado y SELECT de sites + conteos agrupados por site_id de appointments y doctor_schedules. Ninguna identificación de pacientes.
2. Propuesta de clave estable: MAGDALENA; nombre Magdalena del Mar; estado ACTIVO. Validar clave con responsable antes de aplicar; no inventar un id.
3. Mecanismo por preparar en un PR de catálogo/panel: preview sin escrituras, hash del catálogo relevante, --apply con confirmación del hash y actor identificado; revalidación dentro de transacción, insert solo si no existe; si coincide código/nombre reutilizar ID real, si hay ambigüedad/otra sede o registro incompatible abortar. Índice unique(codigo) evita doble inserción; recibo privado y auditoría.
4. No modificar site_id de 14 citas, 2 pagos ni horarios existentes. Para históricos NULL, mostrarlos como Sin sede asignada dentro del alcance transitorio autorizado; nunca afirmar que tienen una sede persistida.
5. Postcomprobación: una sola fila del código aprobado, ID generado por la BD, conteos/históricos intactos. Repetición debe ser no-op con mismo ID.
6. Reversión: conservar fila si ya tiene relaciones. Si no tiene relaciones ni hubo uso, retirar únicamente mediante procedimiento autorizado y recibo de alta; no borrar ni reasignar históricos. El rollback de código no requiere borrar una sede real.

El panel predeterminado queda bloqueado por el alta pendiente del catálogo, no por una decisión de nombre. Figma design node24:2 ya inspeccionado visualmente: sidebar de navegación, KPIs superiores, filtros, tabla amplia y detalle lateral; sin acceso de edición. Las columnas clínicas sugeridas por el prototipo no autorizan exponer diagnósticos.
