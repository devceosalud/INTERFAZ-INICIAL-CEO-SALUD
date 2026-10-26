# API interna de sala de espera

Implementación aditiva local, desactivada por defecto. No es una autorización productiva.

## Archivos

- config/waiting_room.php: flags, TTL, límites y ubicación de configuración privada.
- app/Services/WaitingRoom/InstallationConfiguration.php: instalación y política de identificación.
- app/Services/WaitingRoom/WaitingRoomSnapshot.php: proyección explícita, referencias opacas, filtros reales, asignaciones verificadas, secuencia y orden auditado.
- app/Http/Middleware/WaitingRoomServiceAuth.php: HMAC, timestamp, nonce, límites, alcance y transporte.
- app/Http/Controllers/Integration/WaitingRoomSnapshotController.php: respuesta y error genéricos.
- routes/api.php: GET /api/integration/v1/waiting-room/snapshot.
- tests/Feature/Integration/WaitingRoomSnapshotTest.php: nueve pruebas y 85 aserciones.
- scripts/waiting-room-fixture.php: harness que rechaza bases ajenas al runtime SQLite aislado; nunca borra eventos.
- docs/waiting-room/installations.example.json y .env.waiting-room.example: plantillas sin secretos, cerradas por defecto.

## Contrato

schema=ceo.waiting-room.v1. Raíz: screen_ref, site(ref,label), jornada(day,timezone), generated_at, valid_until, stream_ref, sequence, freshness, rooms y calls. Cada room incluye room_ref, room_label, professional_ref, professional_label y patients. Paciente: subject_ref, display_label, display_parts y state. Call: event_ref, subject_ref, room_ref, display_label, display_parts, speech_label, speech_authorized, room_label, professional_label, professional_speech_label, occurred_at y valid_until.

Solo En espera/Llamando/En atención con llegada auditada, presencial, sede y jornada correctas, agenda CONFIRMADA o LEGADO, paciente/profesional activos y destino configurado. Programados sin llegada, reservas privadas, teleconsultas y episodios terminados/salidos no se publican. Homónimos ambiguos se omiten. Reevaluación indicada sin atención retomada no se representa como presencia física.

No retorna modelos Eloquent, IDs internos, documentos, pagos, clínica ni metadata. El feed administrativo no se reutiliza. La API no modifica citas ni usa updated_at para generar anuncios.

## Autenticación

Configuración privada fuera de public: installations[key_id] contiene active, secret de al menos 32 bytes, site_id y screen_ref. privacy permanece disabled hasta aprobación; fixtures-only exige entorno testing/integration y lista explícita de patient IDs ficticios. Las asignaciones se definen por sede/jornada y doctor, con destino físico verificado. Los números del doctor nunca son consultorios.

Enviar X-WR-Key, X-WR-Timestamp, X-WR-Nonce y X-WR-Signature. Canonicalización exacta:

~~~text
GET
/api/integration/v1/waiting-room/snapshot
<unix-seconds>
<32-64 lowercase hex nonce>
<sha256 of exact request body>
~~~

La firma es HMAC SHA-256 hexadecimal del texto anterior usando el secreto de esa instalación. El cuerpo GET normal está vacío; las pruebas HTTP de Laravel usan [] y firman esos bytes exactos. Timestamp ±30 segundos; nonce no reutilizable durante 90 segundos. Sin parámetros query ni Origin. 60/min por instalación, 180/min por IP. HTTPS; solo se permite HTTP loopback cuando entorno y flag lo autorizan expresamente.

La huella de asignaciones se fija por pantalla/jornada. Una modificación dentro de la jornada produce 503 en vez de cambiar destinos activos silenciosamente. La política de configuración debe definir cómo autorizar una nueva versión de jornada; el endpoint GET no ofrece escrituras para ello.

Errores: 401 no autorizado/replay/transport/origin, 404 integración apagada, 422 query no permitido, 429 límite, 503 configuración/proyección no disponible. Sin datos privados en el error. Caché privada/no-store. La caché técnica no equivale a escrituras de negocio.

## Pruebas locales

~~~powershell
C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit tests/Feature/Integration/WaitingRoomSnapshotTest.php
C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit tests/Feature/Scheduling/AdmissionBoardTest.php
C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit tests/Feature/Scheduling/TeleconsultationCareTest.php
~~~

Las pruebas originales fuerzan SQLite :memory:. El harness de recorrido persistente exige APP_ENV=integration y rutas resueltas dentro del directorio .local-integration/runtime hermano del worktree. Requiere las dependencias de desarrollo para generar fixtures; no se utiliza en producción. Acciones: seed, access, arrive, call, start, finish, reverse, status; renew crea nuevos episodios después de completar los previos; extra añade exclusivamente el cuarto paciente ficticio de la prueba.

No hay migración nueva ni cambios en servicios clínicos, estados, finanzas o Agenda 2.0. El reporte completo del recorrido, archivos del cliente, resultados, accesos locales, riesgos y rollback está en docs/INTEGRACION-LOCAL-ERP.md del worktree del llamador.
