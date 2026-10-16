# PR #35 — cierre de revisión técnica

Comentario revisado: https://github.com/devceosalud/INTERFAZ-INICIAL-CEO-SALUD/pull/35#pullrequestreview-5542307852

- **A / corregido:** `updated_by_user_id` del POST de alta permite relacionar el ID con el creador mediante el catálogo. Se omite para no ADMIN, igual que `creator_user_id`. Se conserva en BD y en auditoría; no hay consumidor JS de ese campo. Tests ADMISION/COMERCIAL/RECEPCION sin ambos campos; ADMIN conserva atribución. La política de reservas comerciales permanece intacta.
- **B / comprobación añadida:** CANCELADO y RETIRO con una cita CONFIRMADA y pagada en exactamente el mismo intervalo. Feed Día/Semana conserva ambos eventos; Mes agrega ambos sin identidades. Solo la activa ocupa. Prueba JS de grilla conserva ambas filas y no ofrece disponibilidad falsa; prueba real de navegador verifica ambas tarjetas de Semana lado a lado.
- **C / diagnóstico, sin grants:** Registrar salida requiere sesión vigente y usuario activo, módulo habilitado, `appointment.mvp.access`, `appointment.view`, `appointment.update`, visibilidad de cita, ATENDIDO y salida todavía no registrada. No requiere permiso financiero. Roles por sí solos no autorizan ni se concedió UPDATE automáticamente.
- **Presentación:** etiquetas de Agenda usan PROFESIONAL/Profesionales; no cambian doctor_id, rutas, variables, nombres del catálogo o expresiones clínicas como médico tratante.

## Diagnóstico Hostinger solo lectura

No ejecutado por el agente. Responsable debe identificar privadamente el ID de la cuenta operativa, sin publicar DNI/email/nombre. Reemplazar `<ID_USUARIO_VALIDADO>`; usar usuario SQL de solo lectura y transacción READ ONLY. No mostrar tablas completas, sesiones/tokens ni contraseñas. Los SELECT no asignan permisos ni normalizan guards.

```sql
SELECT id,is_active FROM users WHERE id=<ID_USUARIO_VALIDADO>;
SELECT r.id,r.name,r.guard_name,LENGTH(r.guard_name) AS guard_length
FROM roles r JOIN model_has_roles m ON m.role_id=r.id
WHERE m.model_type='App\\Models\\User' AND m.model_id=<ID_USUARIO_VALIDADO>;
WITH required AS (
 SELECT 'appointment.mvp.access' capability UNION ALL SELECT 'appointment.view'
 UNION ALL SELECT 'appointment.update'
), granted AS (
 SELECT p.name capability,'DIRECTO' source FROM permissions p
 JOIN model_has_permissions m ON m.permission_id=p.id
 WHERE m.model_type='App\\Models\\User' AND m.model_id=<ID_USUARIO_VALIDADO> AND p.guard_name='web'
 UNION
 SELECT p.name,'ROL' FROM permissions p
 JOIN role_has_permissions rp ON rp.permission_id=p.id
 JOIN model_has_roles m ON m.role_id=rp.role_id
 WHERE m.model_type='App\\Models\\User' AND m.model_id=<ID_USUARIO_VALIDADO> AND p.guard_name='web'
)
SELECT r.capability,COUNT(g.capability)>0 AS granted_in_relations,
 GROUP_CONCAT(DISTINCT g.source) AS source
FROM required r LEFT JOIN granted g ON g.capability=r.capability GROUP BY r.capability;
```

Verificar `model_type` real con `SELECT DISTINCT model_type FROM model_has_roles` y de `model_has_permissions` antes del SELECT: el SQL usa App\Models\User, sin cambiar morph maps. Comparar con `config/auth.php` y `config/permission.php` del commit desplegado (no imprimir `.env`). Spatie puede leer relaciones de roles heredados con guard vacío ya asignados; ese hallazgo no debe ocultarse ni corregirse por este diagnóstico. SQL muestra relaciones; además comprobar caché de permisos vigente, cuenta activa, guard web y posibles restricciones de sesión/estado/visibilidad. Si falta una capacidad, pedir decisión explícita para esa cuenta/rol: no ejecutar grants, seeders o limpieza de caché en este preflight.

Resultado esperado a entregar: ID opaco, activo sí/no, roles/guards y tres booleanos de capacidad. No datos de pacientes. Con capacidades presentes, smoke con datos ficticios en staging: ATENDIDO sin salida ofrece la acción; salida requiere motivo y no registra dinero. No probar escribiendo pacientes reales en producción.

## Publicación y reversión

Mantener Draft hasta revisión. Confirmar main/HEAD/commit instalado y autodespliegue antes de cualquier merge autorizado. Respaldar y verificar código/configuración privada/BD/adjuntos; ensayar restauración en aislamiento. Aplicar en staging, en orden, las migraciones `2026_10_15_230000_add_explicit_diabetic_wound_flag` y `2026_10_15_231000_add_explicit_appointment_departure`; ninguna hace backfill. Registrar tiempos y validar ambos campos nullable. No ejecutar `migrate:fresh` fuera de QA.

En ventana autorizada: migraciones aditivas compatibles antes de activar el código/assets nuevo; comprobar `migrate:status`, columnas y SHA, luego smoke privado de reserva/confirmación, semáforo, creador, horario reutilizado y salida con cuenta autorizada. Privacidad/admisibilidad de Clarity, respaldo restaurable, permisos efectivos Nicol y configuración real Hostinger permanecen condiciones de publicación, no certificadas por suites locales.

Rollback preferido: volver código/assets al baseline revisado, **conservar ambas columnas y auditoría**. No `migrate:rollback` para borrar información registrada y no restaurar una BD antigua sobre cobros posteriores. Si hubo citas/pagos nuevos, mantenerlos, detener nuevas operaciones y corregir hacia adelante o revertir únicamente código compatible. Una interfaz anterior no muestra HERIDA/salida: suspender operaciones que dependan de esas señales hasta resolverlo.

UX compacta/OCR/validación #33 se entrega en PR dependiente separado; no amplía contabilidad ni issue #28.

## Evidencia final de revisión

Código `29ba5df02d811377bfad1204392268fb2051f7b4`: PHP completa **924/7.471**, MariaDB aislada **211/2.048**, JavaScript **280**, navegador **11 escenarios**, todos aprobados. [Resultados](qa/agenda-etapa-a/revision-final.json). La evidencia anterior de 920/207/279/10 corresponde a su commit anterior y no sustituye esta ejecución. UI de pagos/OCR/#33 se valida adicionalmente en el PR dependiente.
