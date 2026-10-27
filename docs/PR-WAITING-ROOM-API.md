# PR preparado — API protegida del llamador

Estado: **rama local revisable; sin push y sin PR publicado**. No habilita pacientes reales.

Base remota verificada el 27/10/2026: `origin/main`, `34f82dd159e8a716f71dcadca0a8ac9d7dec0794`. Incluye el trabajo reciente de exoneraciones e interconsultas. Rama: `codex/waiting-room-api-pr-20261027`.

## Título propuesto

Agregar API interna de sala de espera con autenticación por instalación y proyección mínima

## Descripción propuesta

El llamador independiente necesita leer los cambios autorizados de Admisión sin reutilizar el feed administrativo ni acceder a la base del ERP. Se agrega `GET /api/integration/v1/waiting-room/snapshot`, cerrado por defecto, con autenticación HMAC, timestamp, nonce de un solo uso, límites de solicitudes y alcance de sede/pantalla.

La proyección usa eventos auditados de llegada y llamado, jornada válida y destinos configurados explícitamente. Solo entrega referencias opacas, etiquetas públicas y de voz autorizadas, profesional, destino, estados operativos y vigencia/secuencia. Excluye reservas sin llegada, teleconsultas, episodios terminados, homónimos ambiguos y datos clínicos, económicos o de contacto. No usa `updated_at` como evento ni modifica citas, estados existentes o Agenda 2.0.

La caché corta evita repetir la proyección completa sin desactivar autenticación, alcance, vencimiento ni invalidación por eventos. Las respuestas sin cambios mantienen el contrato de frescura entre servidores.

**Validación:** 12 pruebas focalizadas de `WaitingRoomSnapshotTest` aprobadas sobre esta base, usando SQLite en memoria y datos ficticios. El recorrido HTTP real con el cliente y Dora está verificado en el laboratorio local del llamador con la rama de integración original; todavía debe repetirse en staging con este PR y el motor de base de datos definitivo. No equivale a verificación productiva.

## Alcance y revisión

- Dos commits aditivos de implementación/optimización; no cambian servicios clínicos.
- No se agrega ninguna migración ni se cambian tablas existentes.
- Archivos y contrato: [WAITING-ROOM-API.md](WAITING-ROOM-API.md).
- Configuración inicial: `.env.waiting-room.example` y `docs/waiting-room/installations.example.json`, sin credenciales funcionales.
- La configuración privada define claves individuales revocables, sede, pantalla, asignaciones de jornada y política de identificación. Mantener `privacy=disabled` hasta aprobación de negocio/privacidad.
- Los scripts de fixtures rechazan entornos/bases fuera del laboratorio aislado. No forman parte del flujo productivo.
- No instalar secretos ni activar la API mediante este PR. Habilitación separada después de revisión.

## Pendiente antes de publicación

1. Revisar diferencia contra `main` nuevamente si recibe otros cambios; actualizar esta rama sin tocar worktrees ajenos.
2. Revisión técnica/privacidad de la proyección y configuración de destinos físicos.
3. Ejecutar pruebas en staging con el motor/versiones usados por el ERP, incluida concurrencia del caché/nonce. Sin datos reales.
4. Revisar soporte de PHP/Laravel del ERP con su responsable. Este cambio no migra su framework.
5. Autorizar push y creación del PR. No se han realizado.

## Publicación y rollback propuestos

Publicar el código primero con integración desactivada; respaldar la versión y configuración anteriores. Provisionar claves nuevas solo en archivos privados de ambos servidores, verificar HTTPS, reloj y permisos. Activar únicamente la instalación ficticia de staging y comprobar aislamiento de sedes y no repetición de eventos. La habilitación clínica necesita autorización distinta.

Rollback inmediato: desactivar la integración y revocar la instalación. El cliente mostrará estado seguro sin pacientes vencidos. Restaurar el artefacto/configuración previa si hace falta; no hay esquema nuevo que revertir. No restaurar una cola histórica de anuncios.
